# قالب رسمی بسته انتشار Sokna

فرمت: `sokna-release-v2`

Manifest باید شامل این موارد باشد:

- `version`
- `from_versions`
- `min_php`
- `required_extensions`
- `files` با `path`, `size`, `sha256`
- `delete`
- `migration` در صورت نیاز
- `updater_engine`

فایل‌ها در `files/<relative-path>` قرار می‌گیرند. مسیرهای تنظیمات، storage، uploads و فایل‌های موتور فعال محافظت می‌شوند. موتور مقصد در پوشه نسخه‌دار خودش حمل می‌شود و فقط پس از Health Check فعال می‌شود.
