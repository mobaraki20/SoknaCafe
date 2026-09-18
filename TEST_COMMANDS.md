# TEST_COMMANDS — Sokna Web Print dev.20

Source root: `Sokna-1.36.4-dev.19/` با target version `1.36.4-dev.20`.

## Runner رسمی این تحویل

```bash
php tests/print_acceptance.php --case B01 --results artifacts/web-print-dev20/acceptance-runner
php tests/print_acceptance.php --suite web --results artifacts/web-print-dev20/acceptance-runner-web
php tests/print_acceptance.php --suite integration --results artifacts/web-print-dev20/acceptance-runner-integration
```

Runner برای case نامعتبر، zero-test یا محیط ناقص exit-success نمی‌دهد. suite وب در این sandbox به دلیل نبود `pdo_mysql/MariaDB` برای DB cases با exit code 3 و status `NOT_RUN` پایان می‌یابد؛ این رفتار عمدی است.

## Static/regression واقعی

```bash
find . -name '*.php' -type f -print0 | xargs -0 -n1 php -l
node --check assets/js/push-runtime.js
node --check assets/js/print-template-designer.js
python3 tests/print-dev20-contract.py
python3 tests/printing-settings-browser.py
python3 tests/print-template-v2-browser.py
```

## Browser acceptance واقعی Chromium

```bash
for c in B33 B34 B37 B39 B46; do
  python3 tests/print-dev20-browser.py --case "$c" --results artifacts/web-print-dev20/browser
done
```

## Agent real integration

طبق `agent/docs/SERVER_DEPENDENCIES.md` در remediation branch، روی Windows و با acceptance server واقعی:

```powershell
./scripts/Test-Agent-Acceptance.ps1 -CaseId A49 -ResultsDirectory ./artifacts/A49
```

این command در sandbox فعلی اجرا نشده است و B49 را PASS نمی‌کند.
