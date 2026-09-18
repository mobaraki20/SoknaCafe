#!/usr/bin/env python3
from pathlib import Path
import json
R=Path(__file__).resolve().parents[1]
def t(p): return (R/p).read_text(encoding='utf-8')
def need(x,msg):
    if not x: raise AssertionError(msg)
reg=json.loads(t('tests/defect_class_registry.json')); ids={x['id'] for x in reg['classes']}
version=(R/'VERSION.txt').read_text().strip(); need(reg['version']==version,'registry version')
for i in ['supply_need_receipt_integrity','supply_need_duplicate_guard','fulfillment_eligibility_parity','push_action_commit_claim','fresh_install_fk_dependency_order']:
    need(i in ids,'missing defect class '+i)
schema=t('database/schema.sql'); supply=t('modules/Supply/domain.php'); policy=t('assets/js/fulfillment-policy.js'); push=t('api/push_action.php')
need('GENERATED ALWAYS' not in schema,'generated column escaped install compatibility')
need(schema.index('CREATE TABLE IF NOT EXISTS inventory_movements (') < schema.index('CREATE TABLE IF NOT EXISTS inventory_supply_receipts ('),'FK dependency order regression')
need('open_item_guard VARCHAR(255) NULL' in schema and 'supply_need_guard' in supply,'supply duplicate guard missing')
need("'movement_type'=>'purchase_receive'" in supply and 'request_token' in supply and 'expected_preparing_quantity_base' in supply,'receipt integrity/idempotency/stale-state guard missing')
need('inventory_supply_receipt_allocations' in schema,'aggregated receipt allocation audit missing')
need('window.SoknaFulfillment' in policy and 'takeaway_allowed' in schema,'fulfillment parity owner missing')
need(push.index('$pdo->beginTransaction()') < push.index('push_action_claim_once($claims)'),'push token claim before transaction')
print(f'{version} defect-class gate PASS.')
