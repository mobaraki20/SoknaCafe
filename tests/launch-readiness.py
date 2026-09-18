#!/usr/bin/env python3
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
read = lambda path: (ROOT / path).read_text(encoding="utf-8")
errors: list[str] = []

install = read("install.php")
help_page = read("help.php")
help_topics = read("includes/help_topics.php")
layout = read("includes/panel_layout.php")

if "function install_text_length" not in install:
    errors.append("Fresh-install Unicode length helper is missing.")
if "if (text_length($adminPass)" in install or "if (text_length($operatorPass)" in install:
    errors.append("Fresh installer still calls bootstrap-only text_length().")
if "require_login();" not in help_page:
    errors.append("Help center is not available to every authenticated staff account.")
for required in ["'floor'", "'preparation'", "id'=>'first-shift'", "id'=>'server-outage'"]:
    if required not in help_topics:
        errors.append(f"Help center launch coverage is missing: {required}")
if 'data-topic=' not in help_page:
    errors.append('Help center topic renderer is missing data-topic markers.')
if 'helpTopicMap' in layout or 'panel-context-help' in layout:
    errors.append('Retired contextual-help shortcut still remains in panel headers.')
if "assets/css/panel.css" not in layout:
    errors.append("Launch-readiness UI layer is not loaded.")

# Execute the installer POST validation path without config.php. Before the fix this path fatals
# on an undefined text_length() call, even before a database connection is attempted.
runner = r'''
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['SCRIPT_NAME']='/install.php';
$_SERVER['SERVER_PORT']='80';
$_POST=['csrf_token'=>'','db_host'=>'localhost','db_port'=>'3306','db_name'=>'','db_user'=>'','db_pass'=>'','app_url'=>'http://localhost','cafe_name'=>'Sokna','tagline'=>'','admin_user'=>'admin','admin_pass'=>'123','table_count'=>'30'];
include 'install.php';
'''
result = subprocess.run(["php", "-d", "display_errors=1", "-r", runner], cwd=ROOT, capture_output=True, text=True)
combined = result.stdout + result.stderr
if result.returncode != 0 or "Call to undefined function" in combined or "Fatal error" in combined:
    errors.append("Fresh installer validation path still crashes: " + combined[-500:])
if "اطلاعات پایگاه داده کامل نیست" not in combined:
    errors.append("Fresh installer validation did not reach the expected safe error state.")

if errors:
    raise SystemExit("\n".join(errors))
print("Launch readiness regressions passed: fresh installer, all-role help, contextual guidance, and emergency playbook.")
