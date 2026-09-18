#!/usr/bin/env python3
"""Exercise the real PHP HTTP transport against an API 2.0 localhost fixture."""
from __future__ import annotations
import json, subprocess, threading, time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
seen=[]
class Handler(BaseHTTPRequestHandler):
    protocol_version='HTTP/1.1'
    def log_message(self,*args): pass
    def reply(self,status,payload,ctype='application/json',tracking=''):
        if isinstance(payload,dict) and tracking and 'tracking_id' not in payload: payload=dict(payload,tracking_id=tracking)
        raw=payload if isinstance(payload,bytes) else json.dumps(payload,ensure_ascii=False).encode()
        self.send_response(status);self.send_header('Content-Type',ctype);
        if tracking:self.send_header('X-Sokna-Tracking-ID',tracking)
        self.send_header('Content-Length',str(len(raw)));self.end_headers()
        try:self.wfile.write(raw)
        except BrokenPipeError:pass
    def do_GET(self):
        seen.append({'method':'GET','path':self.path,'authorization':self.headers.get('Authorization',''),'tracking':self.headers.get('X-Tracking-ID','')})
        if self.headers.get('Authorization')!='Bearer integration-secret': return self.reply(401,{'ok':False,'error':'unauthorized'})
        if 'action=slow' in self.path: time.sleep(2); return self.reply(200,{'ok':True,'transaction_id':'LATE'})
        if 'action=invalid_json' in self.path:return self.reply(200,b'not-json','text/plain')
        if 'action=capabilities' in self.path:return self.reply(200,{'ok':True,'api_version':'2.0','capabilities':{'unified_search':True,'invoice_snapshot':True,'idempotent_charge':True,'charge_void':True}})
        if 'action=reservation' in self.path:return self.reply(200,{'ok':True,'reservation':{'reservation_code':'SK-HTTP-1','guest_name':'مهمان تست','room_names':['سیف'],'check_in':'2026-08-05','check_out':'2026-08-07','masked_mobile':'0912***1234','charge_allowed':True,'charge_block_reason':None}})
        return self.reply(200,{'ok':True,'reservations':[{'reservation_code':'SK-HTTP-1','guest_name':'مهمان تست','room_names':['سیف'],'check_in':'2026-08-05','check_out':'2026-08-07','masked_mobile':'0912***1234','charge_allowed':True,'charge_block_reason':None}]})
    def do_POST(self):
        raw=self.rfile.read(int(self.headers.get('Content-Length','0') or 0)).decode();data=json.loads(raw or '{}')
        seen.append({'method':'POST','path':self.path,'authorization':self.headers.get('Authorization',''),'tracking':self.headers.get('X-Tracking-ID',''),'body':data})
        if self.headers.get('Authorization')!='Bearer integration-secret':return self.reply(401,{'ok':False,'error':'unauthorized'})
        if 'action=charge' in self.path:
            if data.get('external_order_id')=='CAFE-S-SCHEMA': return self.reply(503,{'ok':False,'error':'schema_not_ready','message':'repair schema'},tracking='HOUSE-HTTP-SCHEMA')
            return self.reply(200,{'ok':True,'status':'posted','transaction_id':'TX-HTTP-1','external_order_id':data.get('external_order_id'),'idempotent':True},tracking='HOUSE-HTTP-POSTED')
        if 'action=void' in self.path:return self.reply(200,{'ok':True,'status':'voided','transaction_id':'VOID-HTTP-1','original_transaction_id':'TX-HTTP-1','external_order_id':data.get('external_order_id'),'idempotent':True})
        return self.reply(404,{'ok':False,'error':'unknown_action'})
server=ThreadingHTTPServer(('127.0.0.1',0),Handler);threading.Thread(target=server.serve_forever,daemon=True).start();port=server.server_address[1]
php=f'''<?php
declare(strict_types=1);
function setting(string $key,string $default=''): string {{ return $default; }}
function setting_bool(string $key,bool $default=false): bool {{ return $default; }}
function bool_from_mixed(mixed $v,bool $d=false): bool {{ if(is_bool($v))return $v; if(is_int($v))return $v!==0; $s=strtolower(trim((string)$v)); if(in_array($s,['1','true','yes','on'],true))return true; if(in_array($s,['0','false','no','off',''],true))return false; return $d; }}
function text_substr(string $v,int $s,int $l): string {{ return function_exists('mb_substr')?mb_substr($v,$s,$l):substr($v,$s,$l); }}
$GLOBALS['SOKNA_ACCOMMODATION_CONFIG_OVERRIDE']=['enabled'=>true,'base_url'=>'http://127.0.0.1:{port}','api_key'=>'integration-secret'];
require {json.dumps(str(ROOT/'includes/accommodation.php'))};
$search=accommodation_search_active('SK-HTTP-1');
$exact=accommodation_reservation_exact('SK-HTTP-1');
$snapshot=['version'=>1,'number'=>'S-77','issued_at'=>date(DATE_ATOM),'table_name'=>'میز ۱','subtotal'=>850000,'discount'=>0,'total'=>850000,'items'=>[['name'=>'غذا','quantity'=>1,'unit_price'=>850000,'line_total'=>850000,'note'=>null]]];
$transfer=['external_order_id'=>'CAFE-S-77','reservation_code'=>'SK-HTTP-1','amount'=>850000,'invoice_snapshot_json'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
$charge=accommodation_charge_remote($transfer);
$schemaTransfer=$transfer;$schemaTransfer['external_order_id']='CAFE-S-SCHEMA';
$schema=accommodation_charge_remote($schemaTransfer);
$void=accommodation_void_remote($transfer,'ابطال تست');
$invalid=accommodation_result_from_response(accommodation_http_request('invalid_json','GET',[],2),'charge');
$slow=accommodation_result_from_response(accommodation_http_request('slow','GET',[],1),'charge');
echo json_encode(['search'=>$search,'exact'=>$exact,'charge'=>$charge,'schema'=>$schema,'void'=>$void,'invalid'=>$invalid,'slow'=>$slow],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
'''
proc=subprocess.run(['php'],input=php,text=True,capture_output=True,timeout=15)
server.shutdown();server.server_close()
if proc.returncode!=0: raise AssertionError(proc.stderr)
out=json.loads(proc.stdout)
assert out['search']['success'] and out['search']['reservations'][0]['reservation_code']=='SK-HTTP-1'
assert out['exact']['success'] and out['exact']['reservation']['charge_allowed'] is True
assert out['charge']['success'] and out['charge']['idempotent'] is True and out['charge']['transaction_id']=='TX-HTTP-1' and out['charge']['tracking_id']=='HOUSE-HTTP-POSTED'
assert out['schema']['success'] is False and out['schema']['ambiguous'] is False and out['schema']['code']=='schema_not_ready' and out['schema']['tracking_id']=='HOUSE-HTTP-SCHEMA'
assert out['void']['success'] and out['void']['original_transaction_id']=='TX-HTTP-1'
assert out['invalid']['ambiguous'] is True and out['slow']['ambiguous'] is True
search_req=next(x for x in seen if x['method']=='GET' and 'action=search' in x['path'])
assert 'query=SK-HTTP-1' in search_req['path'] and 'type=' not in search_req['path']
charge_req=next(x for x in seen if x['method']=='POST' and 'action=charge' in x['path'])
assert charge_req['body']['currency']=='TOMAN' and charge_req['body']['invoice']['items'][0]['name']=='غذا'
assert all(x['authorization']=='Bearer integration-secret' for x in seen)
assert all(x['tracking'].startswith('CAFE-') for x in seen)
print('Accommodation API 2.0 real HTTP transport passed: unified search, exact reservation, snapshot charge, idempotent void and ambiguous transport handling.')
