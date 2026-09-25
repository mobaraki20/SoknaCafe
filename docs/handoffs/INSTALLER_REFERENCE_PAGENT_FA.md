# مرجع نصب Pagent

Release فعلی مشاهده‌شده v6.2.5 در commit `11708956df922e02ad8067ad281950dae33bab64` است؛ در شاخه‌های مشاهده‌شده 6.3.5 دیده نشد.
https://github.com/mobaraki20/Pagent/releases/tag/v6.2.5

`agent/src/Sokna.PrintAgent.Setup/Sokna.PrintAgent.Setup.csproj` از Windows Forms و net10.0-windows10.0.19041.0 با WinExe، win-x64، SelfContained و PublishSingleFile استفاده می‌کند. PayloadZip به صورت EmbeddedResource داخل exe است. Build-Agent.ps1 ساخت و آزمون را هماهنگ می‌کند.

خواسته قطعی مالک: یک Setup.exe با رابط نصب قابل اعتماد؛ PowerShell پشت صحنه مجاز است. اجرای دستی ps1، تنظیم ExecutionPolicy یا سرهم‌کردن بسته‌ها توسط کاربر پذیرفته نیست. بازنویسی صرفاً برای حذف PowerShell لازم نیست.

الگوی قابل استفاده، رابط هدایت‌شده و payload همراه است؛ نیازهای کافه با Pagent یکسان نیست. Inno و Setup Host/UI موجود باید با preflight، seed، worker، repair و recover یکپارچه و طبق WIN-01..12 آزموده شوند. بدون شاهد Windows صرف شباهت ابزار تأیید نصب نیست. شرط بدون هزینه اجباری پابرجاست.
