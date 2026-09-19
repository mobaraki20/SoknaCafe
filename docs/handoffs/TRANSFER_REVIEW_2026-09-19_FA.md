# بازبینی انتقال پروژه و الزامات نصب

## انتقال و بازبینی 2026-09-19 — شاخه فعال را از نو نسازید
- main مشاهده‌شده: `98607d87d50c7913a1143d621e60f807965bae53` (checkpoint محصول همچنان Phase 8A / dev.38).
- شاخه فعال موجود: `phase/8b-windows-setup`.
- head بررسی‌شده: `6a3e183ca0ea544046c09bf3b9c8de7ab038ca5b`.
- CI `35445104275`: completed/success؛ هر سه job Windows runtime، Public+Local MariaDB و Linux regression موفق‌اند.
- هنگام بازبینی PR باز وجود نداشت؛ 8B merge نشده و COMPLETE نیست.
- کد نصب مشترک، machine recovery، service host واقعی C# و PowerShell orchestration موجود است. هنداور اولیه شاخه درباره WinSW قدیمی شده؛ کد C# منبع فعلی است.
- الزامات مالک، انتخاب فنی پیشنهادی، شکاف‌های واقعی و معیارهای پذیرش: `docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md`.
- اقدام بعدی: همان شاخه فعال را fetch و بررسی کن؛ قبل از ادامه تغییرات احتمالی جدید را بخوان. نصب‌کننده نهایی هنوز تأیید نشده است.
- این checkpoint فقط بررسی و مستندسازی است؛ هیچ تغییر runtime یا ارتقای نسخه‌ای انجام نشده و CI فوق متعلق به head کد 8B است، نه commit مستندات جدید.

### قرارداد تحویل هر مرحله
پیش از پایان هر گام، تغییرات را در GitHub ثبت کن؛ CURRENT_STATUS و هنداور فاز باید شامل branch/head، کار انجام‌شده، تست واقعی و run ID، موارد باز و اولین اقدام بعدی باشند. نقطه شروع root و MASTER باید به شاخه فعال اشاره کنند. شاخه‌ای با CI سبز اما بدون merge/post-merge CI را COMPLETE ننام. بسته ZIP تاریخی را بر GitHub فعلی مقدم ندان.
