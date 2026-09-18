#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
page=(ROOT/'admin/accommodation.php').read_text(encoding='utf-8')
acc=(ROOT/'includes/accommodation.php').read_text(encoding='utf-8')
layout=(ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
css=(ROOT/'assets/css/panel-components.css').read_text(encoding='utf-8')
assert "redirect('accommodation_settings.php')" not in page, 'History page must remain reachable while live integration is disabled.'
assert 'accommodation_attention_rows(10)' in page, 'Admin attention preview must be independent from history pagination.'
assert 'array_filter($rows' not in page, 'Actionable issues must not derive from paginated history rows.'
assert '$perPage=25;' in page and "LIMIT '.$perPage.' OFFSET '.$offset" in page, 'History must use server-side pagination.'
assert 'SELECT COUNT(DISTINCT at.id)' in page and '$totalRows=(int)$count->fetchColumn()' in page, 'History pagination requires a durable total count.'
assert "LEFT JOIN settlement_records sr ON sr.accommodation_transfer_id=at.id AND sr.status='completed'" in page, 'Human-facing invoice number should come from the completed settlement record.'
assert 'همه سفارش‌های اقامت با موفقیت منتقل شده‌اند' not in page, 'All-clear success banner must remain exception-only.'
assert 'از تسویه‌های امروز' not in page, 'History must not claim old rows are actionable from today settlements.'
assert 'accommodation-history-list financial-list' in page and 'accommodation-history-item financial-row' in page, 'History must use the single responsive financial-list presentation.'
assert 'function accommodation_attention_rows(?int $limit=50): array' in acc
assert 'function accommodation_attention_count(): int' in acc
assert 'function accommodation_history_exists(): bool' in acc
assert "($a.status='posted' AND NOT $completed)" in acc and "($a.status='voided' AND $completed AND NOT $reversed)" in acc
assert "accommodation_live_operations_enabled() || accommodation_history_exists()" in layout, 'Admin navigation must preserve access to history/recovery while live connection is disabled.'
assert 'accommodation_attention_count()' in layout, 'Admin badge must surface remote and local financial recovery issues while disabled.'
assert '.financial-list' in css and '.financial-row' in css, 'Accommodation list must inherit the shared Financial Composition owner.'
print('Accommodation history contract passed: durable history, independent attention queue, disabled-live access, local financial recovery, pagination, and shared responsive financial composition.')
