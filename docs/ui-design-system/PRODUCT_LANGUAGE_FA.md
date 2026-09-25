# SOKNA Cafe — زبان محصول فارسی

**مالک:** SCDS-CANONICAL-2026-R1
**قاعده:** نام فنی backend/API/schema می‌تواند انگلیسی/legacy بماند؛ متن قابل‌مشاهده کاربر باید از واژگان canonical فارسی استفاده کند.

## واژگان قطعی
| مفهوم فنی | UI canonical | ممنوع در UI همان مفهوم |
|---|---|---|
| subscriber / cafe credit customer | مشتری / مشتریان / حساب مشتری | مشترک / مشترکین / حساب مشترک |
| direct settlement | تسویه | تسویه مستقیم |
| shared draft/team-shared | پیش‌نویس مشترک | —؛ «مشترک» اینجا معنای shared دارد و مجاز است |
| shared inventory department | مشترک | —؛ این واژه entity مشتری نیست |
| shared cryptographic secret | کلید مشترک (فقط متن فنی/خطا در صورت نیاز) | نباید به customer تبدیل شود |

## قواعد
- تغییر vocabulary به معنی rename schema/API نیست؛ `subscriber`, `subscriber_id` و table/routeهای موجود برای compatibility حفظ می‌شوند.
- «مشترک» فقط وقتی معنای واقعی shared/common دارد مجاز است.
- Search placeholder، audit presentation، receipt/print label، errorهای قابل‌نمایش و Help همگی باید همان vocabulary را مصرف کنند.
- اصطلاح فنی انگلیسی در UI عادی فقط با whitelist و دلیل مجاز است.
