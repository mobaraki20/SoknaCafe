#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding="utf-8")
admin=read("admin/printing.php"); css=read("assets/css/panel-components.css"); printing=read("includes/printing.php"); js=read("assets/js/printing-settings.js")
def need(cond,msg):
    if not cond: raise AssertionError(msg)
need('/releases/latest' in printing and 'print_agent_release_metadata' in printing, 'Agent recommended version is not resolved from stable GitHub release metadata')
need("/releases/download/" in printing and "Sokna-Print-Agent-" in printing and 'latest/download' not in printing, "Agent download is not resolved to a versioned GitHub Release asset")
for token in ["tab=overview","tab=settings","tab=diagnostics","نیازمند رسیدگی","زیرساخت چاپ","تست مسیرهای چاپ","تنظیمات پیشرفته چاپ"]: need(token in admin, f"printing operational console missing: {token}")
need("print5-hero" not in admin, "daily printing hero still consumes operational space")
need("panel-diagnostic-grid" in admin and "is-diagnostic" in admin, "technical diagnostics are not isolated to diagnostics view")
need("print5-destination-editor" in admin and "data-print-editor-close" in admin, "destinations are not edit-on-demand")
need("[data-print-editor-close]" in js, "destination editor close behavior is not owned by printing settings JS")
need(css.count(".print5-job-row{")==1, "printing job rows have stacked CSS owners")
need(".print5-overview-grid" in css and ".print5-status-strip" in css, "operational overview layout is missing")
print("PASS v1363 printing operations contract")
