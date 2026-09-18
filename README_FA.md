# Sokna Cafe — 1.36.4-dev.31

این checkpoint اجرای **Phase 5 — Deferred-safe + Financial Reconciliation** از Handover R2 است. Local همچنان تنها Business Authority است؛ Public فقط Relay/Projection/Pending Work محدود را نگه می‌دارد.

- نسخه جاری Web/PWA: `1.36.4-dev.31`
- Updater Engine: `1.5.3`
- predecessor پذیرفته‌شده این checkpoint: `1.36.4-dev.30`
- Realtime و Deferred-safe دو queue/store مستقل‌اند و هیچ state machine مشترکی ندارند.
- Deferred-safe: خرید/تأمین، دریافت فیزیکی، ضایعات، پیش‌نویس شمارش، پرداخت مشترک در انتظار commit Local و هزینه عمومی.
- `occurred_at` مستقل از زمان commit است؛ Local هنگام sync مجوز، state/version و دوره مالی را دوباره بررسی می‌کند.
- رخداد مربوط به دوره بسته هرگز silent back-post نمی‌شود؛ یک review یکتا می‌سازد و فقط با تصمیم صریح مدیر قابل اعمال است.
- بستن عادی دوره با Pending/Needs-review یا Public paired-but-unknown مسدود است؛ override فقط مدیر + دلیل + audit.
- ماژول Expenses owner مستقل دارد و خرید انبار به‌عنوان هزینه عمومی دوباره‌شماری نمی‌شود.
- Remote Staff در 4G وضعیت `pending_sync / needs_review / committed / rejected` را صریح می‌بیند.
- Guest/Public Snapshot و Remote Read Modelهای Phase 3/4 حفظ شده‌اند.
- Windows Print Agent و state machine چاپ در Phase 5 تغییر نکرده‌اند.
- Status: **Pre-Operational / UAT required**؛ Production Go-Live فقط پس از UAT واقعی DB/HTTP/Windows/Printer/Device.

مرجع توسعه: `DEVELOPER_READ_FIRST_FA.md`، Handover R2 و اسناد جاری `docs/architecture-migration-r2/`.
