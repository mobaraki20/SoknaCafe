# Windows / Printer Production Gate — Print Agent 6

وضعیت فعلی: **PENDING — PRODUCTION GATE**

این محیط Linux است و Windows Service/Winspool/Printer فیزیکی ندارد؛ .NET SDK نیز در Runtime اولیه نصب نبود. بنابراین Source و Installer تولید شده‌اند ولی اجرای واقعی زیر تأیید نشده است:

- نصب Windows clean
- Service Automatic Delayed Start / Recovery
- DPAPI و ACL ProgramData
- SQLite WAL/FULL روی NTFS
- Queue machine-wide
- Persian/RTL physical rendering
- 58/80mm driver scale
- 50 receipt sequential
- Printer offline / Paper Out / Queue removed
- Spooler stop/start
- Service kill at recovery boundaries
- Windows restart
- Internet disconnect/restore
- 24–72h soak

هیچ Release تا پاس‌شدن این Gate نباید Production-ready نامیده شود.
