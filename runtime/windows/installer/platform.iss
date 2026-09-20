; Platform lifecycle preview only. No active application files belong here.
#ifndef SourceRoot
  #error SourceRoot is required
#endif
#define ProductVersion "0.1.0"

[Setup]
AppId={{D577EAA8-1B19-45C6-9FB0-91008FD349E3}
AppName=SOKNA Platform Preview
AppVersion={#ProductVersion}
AppPublisher=SOKNA
DefaultDirName={autopf}\SOKNA Platform Preview
DefaultGroupName=SOKNA
DisableProgramGroupPage=yes
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0
OutputBaseFilename=SOKNA-Platform-Preview-{#ProductVersion}-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
SetupLogging=yes
UninstallLogging=yes
SetupIconFile={#IconFile}
UninstallDisplayIcon={app}\sokna.ico
AppModifyPath="{app}\maintenance\Setup.exe" /REPAIR
InfoBeforeFile={#SourceRoot}\installer\PREVIEW.txt
CloseApplications=no
RestartApplications=no

[Files]
Source: "{#SourceRoot}\setup-sokna.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourceRoot}\setup-support.psm1"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourceRoot}\provision-local-https.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourceRoot}\installer\package-bridge.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#HostFile}"; DestDir: "{app}\bin"; Flags: ignoreversion
Source: "{#IconFile}"; DestDir: "{app}"; DestName: "sokna.ico"; Flags: ignoreversion
Source: "{#SourceRoot}\installer\PREVIEW.txt"; DestDir: "{app}"; Flags: ignoreversion
Source: "{srcexe}"; DestDir: "{app}\maintenance"; DestName: "Setup.exe"; Flags: external; Check: CacheSetup

[Icons]
Name: "{commondesktop}\SOKNA"; Filename: "https://{code:LocalHost}/"; IconFilename: "{app}\sokna.ico"
Name: "{group}\SOKNA"; Filename: "https://{code:LocalHost}/"; IconFilename: "{app}\sokna.ico"
Name: "{group}\SOKNA Platform Repair"; Filename: "{app}\maintenance\Setup.exe"; Parameters: "/REPAIR"; IconFilename: "{app}\sokna.ico"

[Registry]
Root: HKLM; Subkey: "SOFTWARE\SOKNA\PlatformPreview"; ValueType: string; ValueName: "AppRoot"; ValueData: "{code:TargetValue|0}"; Flags: uninsdeletekey
Root: HKLM; Subkey: "SOFTWARE\SOKNA\PlatformPreview"; ValueType: string; ValueName: "DataRoot"; ValueData: "{code:TargetValue|1}"
Root: HKLM; Subkey: "SOFTWARE\SOKNA\PlatformPreview"; ValueType: string; ValueName: "PhpExe"; ValueData: "{code:TargetValue|2}"
Root: HKLM; Subkey: "SOFTWARE\SOKNA\PlatformPreview"; ValueType: string; ValueName: "OpenSslExe"; ValueData: "{code:TargetValue|3}"
Root: HKLM; Subkey: "SOFTWARE\SOKNA\PlatformPreview"; ValueType: string; ValueName: "Hostname"; ValueData: "{code:TargetValue|4}"

[Code]
var
  Target: TInputQueryWizardPage;
  OwnerResult: String;
  OwnerCode: Integer;

function Q(Value: String): String;
begin
  { Paths cannot contain quotes; remove trailing slash ambiguity for native argv. }
  Result := '"' + RemoveBackslashUnlessRoot(Value) + '"';
end;

function TargetValue(Param: String): String;
begin
  Result := Target.Values[StrToInt(Param)];
end;

function LocalHost(Param: String): String;
begin
  Result := Target.Values[4];
end;

function CacheSetup: Boolean;
begin
  Result := CompareText(ExpandConstant('{srcexe}'), ExpandConstant('{app}\maintenance\Setup.exe')) <> 0;
end;

procedure InitializeWizard;
var
  Names: TArrayOfString;
  I: Integer;
  Saved: String;
begin
  Target := CreateInputQueryPage(wpSelectDir, 'Existing SOKNA installation',
    'Test package: Windows platform maintenance',
    'Select the configured application and installed prerequisites. This preview does not install PHP, a web server or a database.');
  Target.Add('Application folder:', False);
  Target.Add('Private data folder:', False);
  Target.Add('PHP executable:', False);
  Target.Add('OpenSSL executable:', False);
  Target.Add('Local hostname:', False);
  SetArrayLength(Names, 5);
  Names[0] := 'AppRoot'; Names[1] := 'DataRoot'; Names[2] := 'PhpExe';
  Names[3] := 'OpenSslExe'; Names[4] := 'Hostname';
  for I := 0 to 4 do begin
    if RegQueryStringValue(HKLM64, 'SOFTWARE\SOKNA\PlatformPreview', Names[I], Saved) then begin
      Target.Values[I] := Saved;
      Target.Edits[I].Enabled := False;
    end else
      Target.Values[I] := ExpandConstant('{param:' + Names[I] + '|}');
  end;
  if Target.Values[4] = '' then Target.Values[4] := 'sokna.local';
  OwnerCode := 0;
end;

function ReadResult(FileName: String): String;
var
  Lines: TArrayOfString;
  I: Integer;
begin
  Result := 'No diagnostic result was returned.';
  if LoadStringsFromFile(FileName, Lines) then begin
    Result := '';
    for I := 0 to GetArrayLength(Lines) - 1 do Result := Result + Lines[I] + #13#10;
  end;
end;

function RunOwner(Base, Operation, Extra, ResultFile: String; var ExitCode: Integer): Boolean;
begin
  Result := Exec(ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'),
    '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File ' + Q(Base + '\package-bridge.ps1') +
    ' -Operation ' + Operation + ' -ResultFile ' + Q(ResultFile) + Extra,
    '', SW_HIDE, ewWaitUntilTerminated, ExitCode);
end;

function Overlaps(A, B: String): Boolean;
begin
  A := Lowercase(AddBackslash(ExpandFileName(A)));
  B := Lowercase(AddBackslash(ExpandFileName(B)));
  Result := (Pos(A, B) = 1) or (Pos(B, A) = 1);
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  I, Code: Integer;
  TempBase, Extra, ResultFile: String;
begin
  Result := '';
  if Pos(Lowercase(AddBackslash(ExpandConstant('{autopf}'))), Lowercase(AddBackslash(ExpandFileName(WizardDirValue)))) <> 1 then begin
    Result := 'Platform tools must be installed under Program Files.'; Exit;
  end;
  for I := 0 to 4 do
    if (Target.Values[I] = '') or (Pos('"', Target.Values[I]) > 0) or
       (Pos(#13, Target.Values[I]) > 0) or (Pos(#10, Target.Values[I]) > 0) then begin
      Result := 'Provide valid existing application paths and hostname.'; Exit;
    end;
  if Overlaps(WizardDirValue, Target.Values[0]) or Overlaps(WizardDirValue, Target.Values[1]) or
     Overlaps(Target.Values[0], Target.Values[1]) then begin
    Result := 'Platform, application and private data folders must be separate.'; Exit;
  end;
  ExtractTemporaryFile('setup-sokna.ps1');
  ExtractTemporaryFile('setup-support.psm1');
  ExtractTemporaryFile('provision-local-https.ps1');
  ExtractTemporaryFile('package-bridge.ps1');
  ExtractTemporaryFile('SoknaRuntimeService.exe');
  TempBase := ExpandConstant('{tmp}');
  ForceDirectories(TempBase + '\bin');
  if not FileCopy(TempBase + '\SoknaRuntimeService.exe', TempBase + '\bin\SoknaRuntimeService.exe', False) then begin
    Result := 'Cannot prepare prerequisite validation.'; Exit;
  end;
  Extra := ' -AppRoot ' + Q(Target.Values[0]) + ' -DataRoot ' + Q(Target.Values[1]) +
    ' -PhpExe ' + Q(Target.Values[2]) + ' -OpenSslExe ' + Q(Target.Values[3]) + ' -Hostname ' + Q(Target.Values[4]);
  ResultFile := TempBase + '\preflight-result.txt';
  if not RunOwner(TempBase, 'Validate', Extra, ResultFile, Code) then Code := 2;
  OwnerResult := ReadResult(ResultFile);
  Log('SOKNA preflight: ' + OwnerResult);
  if Code <> 0 then Result := 'Prerequisite check failed. No platform files were installed.' + #13#10 + OwnerResult;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultFile: String;
begin
  if CurStep = ssPostInstall then begin
    ResultFile := ExpandConstant('{tmp}\platform-result.txt');
    if not RunOwner(ExpandConstant('{app}'), 'Repair', '', ResultFile, OwnerCode) then OwnerCode := 2;
    OwnerResult := ReadResult(ResultFile);
    Log('SOKNA platform result: ' + OwnerResult);
    if OwnerCode <> 0 then begin
      SuppressibleMsgBox('Platform setup did not complete. Preserve your data and run Repair after fixing the reported error.' + #13#10 + OwnerResult, mbError, MB_OK, IDOK);
      WizardForm.FinishedLabel.Caption := 'Platform setup did not complete. Run Repair after fixing the reported error. See the setup log for diagnostics.';
    end;
  end;
  if CurStep = ssDone then begin
    if OwnerCode = 0 then
      WizardForm.FinishedLabel.Caption := 'Windows platform maintenance completed. HTTP/database/printer acceptance is still required. This is not the complete SOKNA installer.';
  end;
end;

function GetCustomSetupExitCode: Integer;
begin
  Result := OwnerCode;
end;

function InitializeUninstall: Boolean;
var
  Code: Integer;
  ResultFile, Report: String;
begin
  ResultFile := ExpandConstant('{tmp}\sokna-remove-result.txt');
  Result := RunOwner(ExpandConstant('{app}'), 'RemovePlatform', '', ResultFile, Code);
  Report := ReadResult(ResultFile);
  Log('SOKNA removal: ' + Report);
  Result := Result and (Code = 0);
  if not Result then
    SuppressibleMsgBox('Platform removal stopped. Files and data have been preserved. Run Repair if a maintenance file is missing.' + #13#10 + Report, mbError, MB_OK, IDOK);
end;
