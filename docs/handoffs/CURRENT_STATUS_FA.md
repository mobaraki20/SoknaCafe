# SOKNA — Current Project Status

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

---

Updated: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Current completed release checkpoint: `1.36.4-dev.38`

## Completed through
- Phase 0–7 complete.
- Phase 8A — Installation Identity + Recovery Set Metadata. PR #16.

## Latest verified checkpoint
- merge: `485db60b71b40c475c931a9d6d056ce8a07d0df2`
- post-merge CI: `35442209529` — SUCCESS
- Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS.

Latest handoff: `docs/handoffs/PHASE8A_HANDOFF_FA.md`

## Frozen Phase 8A behavior
- machine installation identity is separate from portable `app.key`.
- installation private key lives only under private data root and is never copied into backups.
- Backup v3 remains canonical and gains safe recovery metadata only.
- mature restore/rollback/encryption engines were not rewritten.

## Active next work
Phase 8B — Windows New / Recover Setup Orchestration.

First audit/compose:
- existing `install.php` fresh install contract.
- Runtime Windows service + `provision-local-https.ps1`.
- Print Agent stable Setup contract.
- backup import/restore and updater recovery owners.
- optional Public pairing, off-server backup and Push setup.

8B must create an orchestration layer, not duplicate installer/updater/backup state machines.
After 8B, Phase 8C handles machine replacement + Public takeover and the restore/takeover drill.
