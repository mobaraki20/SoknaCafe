#!/usr/bin/env python3
from http.server import BaseHTTPRequestHandler, HTTPServer
import base64, hashlib, hmac, json, subprocess, threading, time, urllib.parse
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
SECRET='CenterPairSecret-EXACT_abcdefghijklmnopqrstuvwxyz-0123456789'
seen={}

def b64u_decode(s):
    return base64.urlsafe_b64decode(s + '='*((4-len(s)%4)%4))

class H(BaseHTTPRequestHandler):
    def log_message(self,*args): pass
    def do_POST(self):
        length=int(self.headers.get('Content-Length','0'))
        body=self.rfile.read(length).decode()
        fields=urllib.parse.parse_qs(body, keep_blank_values=True)
        token=(fields.get('token') or [''])[0]
        try:
            h,p,s=token.split('.')
            header=json.loads(b64u_decode(h))
            payload=json.loads(b64u_decode(p))
            sig=b64u_decode(s)
            expected=hmac.new(SECRET.encode(),f'{h}.{p}'.encode(),hashlib.sha256).digest()
            assert hmac.compare_digest(sig,expected), 'HANDOFF_SIGNATURE'
            assert header=={'alg':'HS256','typ':'SOKNA-HANDOFF','v':1}, header
            assert payload['iss']=='cafe' and payload['sub']=='17'
            assert payload['context']=='CAFE' and payload['purpose']=='pair'
            assert payload['aud']==f'http://127.0.0.1:{self.server.server_port}'
            assert 0 < int(payload['exp'])-int(payload['iat']) <= 60
            assert 'issuer' not in payload and 'local_user_id' not in payload and 'audience' not in payload
            seen['ok']=True
            out={'ok':True,'data':{'context':'CAFE','issuer':'cafe','origin':'https://cafe.example.com','return_url':'https://cafe.example.com/Menu/center_return.php','version':'mock-1'}}
            raw=json.dumps(out).encode(); self.send_response(200)
        except Exception as e:
            out={'ok':False,'error':{'code':str(e)}}; raw=json.dumps(out).encode(); self.send_response(401)
        self.send_header('Content-Type','application/json'); self.send_header('Content-Length',str(len(raw))); self.end_headers(); self.wfile.write(raw)

srv=HTTPServer(('127.0.0.1',0),H)
th=threading.Thread(target=srv.serve_forever,daemon=True); th.start()
port=srv.server_port
php=f'''<?php
$config=['app'=>['key'=>str_repeat('a',64),'url'=>'https://cafe.example.com/Menu','trust_proxy_headers'=>false]];
require {str(ROOT/'includes/functions.php')!r};
require {str(ROOT/'includes/sokna_center.php')!r};
$GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE']=[
 'enabled'=>true,
 'base_url'=>'http://127.0.0.1:{port}',
 'secret'=>str_repeat('O',64),
 'origin'=>'https://cafe.example.com',
 'return_url'=>'https://cafe.example.com/Menu/center_return.php',
];
$r=sokna_center_pair_remote('http://127.0.0.1:{port}',{SECRET!r},17);
echo json_encode($r,JSON_UNESCAPED_SLASHES);
?>'''
proc=subprocess.run(['php'],input=php,text=True,capture_output=True,cwd=ROOT,timeout=15)
srv.shutdown(); th.join(timeout=2)
if proc.returncode!=0:
    raise SystemExit(proc.stderr or proc.stdout)
out=json.loads(proc.stdout)
assert seen.get('ok') and out.get('version')=='mock-1', (seen,out)
print('1.31.7 Sokna Center real HTTP pair signature contract passed.')
