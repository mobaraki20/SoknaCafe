from pathlib import Path
root=Path(__file__).resolve().parents[1]
admin=(root/'admin/printing.php').read_text()
printing=(root/'includes/printing.php').read_text()
api=(root/'print-agent/v4/api.php').read_text()
css=(root/'assets/css/panel-components.css').read_text()
checks={
 'custom_destination_delete_action': "delete_destination" in admin and "مقصدهای سیستمی قابل حذف نیستند" in admin,
 'delete_preserves_audit_history': "SELECT COUNT(*) FROM print_jobs WHERE destination_key=?" in admin and "سابقه چاپ دارد" in admin,
 'fifo_blocker_visible': 'print5-activity-blocker' in admin and 'صف این مقصد توسط' in admin and 'queueBlockers' in admin,
 'fallback_claim_query': 'd.agent_id=? OR d.fallback_agent_id=?' in api,
 'fallback_route_uses_fallback_queue': "'route_role'=>'fallback'" in api and "fallback_windows_queue_name" in api,
 'fallback_only_when_primary_unready': "&& !$primaryReady && $fallbackReady" in api,
 'block_reason_accepts_fallback': "$fallbackMapped" in printing and "fallback_agent_active" in printing,
 'operational_test_print': 'print_destination_operational_route($pdo,$key)' in admin,
 'blocker_visual_contract': '.print5-activity-blocker.is-problem' in css,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    raise SystemExit('Print dev14 reliability contract FAIL: '+', '.join(failed))
print('Print dev14 reliability contract PASS:', ', '.join(checks))
