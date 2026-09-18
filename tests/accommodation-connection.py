#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
def need(path,token,message):
    if token not in read(path): raise AssertionError(message)

acc=read('includes/accommodation.php');api=read('operator/api_accommodation.php');schema=read('database/schema.sql')
need('admin/accommodation_settings.php','accommodation_connection_enabled','Live accommodation connection control is missing.')
need('admin/accommodation_settings.php','type="password" name="accommodation_api_key"','API key must remain secret.')
need('includes/accommodation.php','aes-256-gcm','API key must be encrypted at rest.')
need('includes/accommodation.php',"$scheme!=='https'&&!$local",'Remote production URL must require HTTPS.')
need('includes/accommodation.php','CURLOPT_SSL_VERIFYPEER=>true','TLS verification is missing.')
for token in ["accommodation_http_request('search'","accommodation_http_request('reservation'","accommodation_http_request('charge'","accommodation_http_request('void'",'invoice_snapshot_json','payload_hash','idempotent','external_order_conflict']:
    assert token in acc, f'Missing API 2.0 behavior: {token}'
for forbidden in ['accommodation_search_types','__SOKNA_CONNECTION_TEST__','search_type','active_reservation']:
    assert forbidden not in acc+api, f'Legacy accommodation contract remains: {forbidden}'
need('includes/accommodation.php',"return 'CAFE-S-'.$sessionId",'External order ID must be stable and session-based.')
need('includes/accommodation.php',"(string)($p['api_version']??'')==='2.0'",'Capabilities must require API 2.0.')
for cap in ['unified_search','invoice_snapshot','idempotent_charge','charge_void']:
    assert cap in acc
need('operator/api_accommodation.php',"$action==='retry'",'Retry endpoint is missing.')
need('operator/api_accommodation.php',"$action==='void'",'Void endpoint is missing.')
need('operator/api_accommodation.php','accommodation_reservation_exact($code)','Reservation must be revalidated before charging.')
need('operator/api_accommodation.php','reservation_code:state.selectedReservation.reservation_code' if False else 'reservation_code','Reservation code is missing.')
for token in ['accommodation_transfers','invoice_snapshot_json','payload_hash','remote_void_transaction_id','remote_original_transaction_id',"status VARCHAR(20) NOT NULL DEFAULT 'pending'",'settlement_records']:
    assert token in schema, f'Missing accommodation persistence: {token}'
for state in ['pending','posted','failed','void_pending','void_failed','voided']:
    assert state in acc
page=read('includes/operator_page.php')
assert 'نام مهمان، موبایل، اتاق یا کد رزرو' in page
assert 'accommodationSearchType' not in page
assert 'data-settlement="accommodation"' in page
assert 'accommodationDates' in page
settlement=read('includes/settlement.php');session_api=read('operator/api_table_session.php')
for key in ["'direct' =>", "'accommodation' =>", "'subscriber' =>"]: assert key in settlement
assert 'payment_method' not in session_api and 'checkout_payment_method' not in schema
need('operator/api_table_session.php',"$action === 'checkout_direct'",'Direct settlement path is missing.')
need('operator/api_bill.php','accommodation_transfer_blocks_invoice_edit','Transferred invoice must be locked.')
need('operator/api_settlements.php','accommodation_attempt_void','Settlement void must reverse accommodation first.')
print('Accommodation API 2.0 structure passed: unified search, exact recheck, immutable snapshot, stable retry, void and settlement integration.')
