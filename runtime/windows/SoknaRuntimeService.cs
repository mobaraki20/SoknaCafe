using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.ServiceProcess;
using System.Threading;

public sealed class SoknaRuntimeService : ServiceBase
{
    private Process child;
    private Timer monitor;
    private volatile bool stopping;
    private string phpExe;
    private string appRoot;
    private string dataRoot;
    private string runtimeScript;

    public SoknaRuntimeService(string[] args)
    {
        ServiceName = "SoknaRuntime";
        CanStop = true;
        CanShutdown = true;
        AutoLog = false;
        Parse(args);
    }

    private void Parse(string[] args)
    {
        var values = new Dictionary<string,string>(StringComparer.OrdinalIgnoreCase);
        for (int i = 0; i < args.Length; i++)
        {
            if (!args[i].StartsWith("--") || i + 1 >= args.Length) continue;
            values[args[i].Substring(2)] = args[++i];
        }
        phpExe = values.ContainsKey("php") ? values["php"] : "";
        appRoot = values.ContainsKey("app-root") ? values["app-root"] : "";
        dataRoot = values.ContainsKey("data-root") ? values["data-root"] : "";
        runtimeScript = Path.Combine(appRoot, "runtime", "sokna-runtime.php");
    }

    private void Validate()
    {
        if (String.IsNullOrWhiteSpace(phpExe) || !File.Exists(phpExe)) throw new InvalidOperationException("PHP executable was not found.");
        if (String.IsNullOrWhiteSpace(appRoot) || !Directory.Exists(appRoot)) throw new InvalidOperationException("Application root was not found.");
        if (!File.Exists(runtimeScript)) throw new InvalidOperationException("SOKNA Runtime entrypoint was not found.");
        if (String.IsNullOrWhiteSpace(dataRoot)) throw new InvalidOperationException("SOKNA data root is required.");
        Directory.CreateDirectory(dataRoot);
        Directory.CreateDirectory(Path.Combine(dataRoot, "logs"));
    }

    private static string Quote(string value)
    {
        return """ + value.Replace(""", "\"") + """;
    }

    private void Log(string message)
    {
        try
        {
            File.AppendAllText(
                Path.Combine(dataRoot, "logs", "runtime-service-host.log"),
                DateTime.UtcNow.ToString("o") + " " + message + Environment.NewLine
            );
        }
        catch { }
    }

    private void StartChild()
    {
        if (stopping) return;
        Validate();

        var info = new ProcessStartInfo();
        info.FileName = phpExe;
        info.Arguments = Quote(runtimeScript);
        info.WorkingDirectory = appRoot;
        info.UseShellExecute = false;
        info.CreateNoWindow = true;
        info.EnvironmentVariables["SOKNA_DATA_DIR"] = dataRoot;

        var process = new Process();
        process.StartInfo = info;
        if (!process.Start()) throw new InvalidOperationException("SOKNA Runtime child process could not start.");
        child = process;
        Log("runtime child started pid=" + process.Id);
    }

    private void Monitor(object state)
    {
        if (stopping) return;
        try
        {
            var current = child;
            if (current == null || current.HasExited)
            {
                if (current != null) Log("runtime child exited code=" + current.ExitCode);
                StartChild();
            }
        }
        catch (Exception ex)
        {
            Log("runtime restart failed: " + ex.GetType().Name + ": " + ex.Message);
        }
    }

    protected override void OnStart(string[] args)
    {
        stopping = false;
        StartChild();
        monitor = new Timer(Monitor, null, 5000, 5000);
    }

    protected override void OnStop()
    {
        stopping = true;
        if (monitor != null)
        {
            monitor.Dispose();
            monitor = null;
        }

        var current = child;
        if (current != null && !current.HasExited)
        {
            try
            {
                current.Kill();
                current.WaitForExit(5000);
            }
            catch { }
        }
        child = null;
        Log("service stopped");
    }

    protected override void OnShutdown()
    {
        OnStop();
        base.OnShutdown();
    }

    public static int Main(string[] args)
    {
        bool selfTest = false;
        var filtered = new List<string>();
        foreach (var arg in args)
        {
            if (String.Equals(arg, "--self-test", StringComparison.OrdinalIgnoreCase)) selfTest = true;
            else filtered.Add(arg);
        }

        var service = new SoknaRuntimeService(filtered.ToArray());
        if (selfTest)
        {
            try
            {
                service.Validate();
                Console.WriteLine("SoknaRuntimeService self-test PASS");
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine(ex.Message);
                return 2;
            }
        }

        ServiceBase.Run(service);
        return 0;
    }
}
