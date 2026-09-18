#!/usr/bin/env python3
from pathlib import Path
import os,sys,time,json
ROOT=Path(__file__).resolve().parents[1]
try:
    from playwright.sync_api import sync_playwright
except Exception as e:
    if os.getenv('SOKNA_RELEASE_GATE')=='1': print('notification runtime browser FAILED: Playwright unavailable',e);sys.exit(1)
    print('SKIP: Playwright unavailable');sys.exit(0)

def fail(msg): print('notification runtime browser FAILED:',msg);sys.exit(1)

with sync_playwright() as p:
    try: b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    except Exception as e:
        if os.getenv('SOKNA_RELEASE_GATE')=='1': fail('Chromium launch failed: '+str(e))
        print('SKIP: Chromium unavailable');sys.exit(0)
    pg=b.new_page(viewport={'width':390,'height':844})
    seen={'kick':[],'drain':[]}
    def route_handler(route, request):
        body=request.post_data or ''
        target='kick' if 'push_kick.php' in request.url else 'drain'
        seen[target].append({'url':request.url,'body':body})
        route.fulfill(status=200,content_type='application/json',body='{"success":true}')
    pg.route('**/api/push_kick.php',route_handler)
    pg.route('**/api/push_drain.php',route_handler)
    pg.set_content('<html><head><meta name="csrf-token" content="csrf-test"></head><body></body></html>')
    pg.evaluate("window.SOKNA_PUSH_DRAIN_URL='https://sokna.test/api/push_drain.php'")
    pg.add_script_tag(path=str(ROOT/'assets/js/push-runtime.js'))
    pg.evaluate("window.SoknaPushRuntime.handleResponse({_push:{kick:{url:'https://sokna.test/api/push_kick.php',queue_id:77,token:'signed-token'}}})")
    pg.wait_for_timeout(300)
    if not seen['kick']: fail('signed response kick did not issue a separate request')
    payload=json.loads(seen['kick'][0]['body'])
    if payload != {'queue_id':77,'token':'signed-token'}: fail('kick request leaked/changed event payload')
    # Initial authenticated drain is scheduled and must carry CSRF.
    pg.wait_for_timeout(900)
    if not seen['drain']: fail('authenticated opportunistic drain did not run')
    dp=json.loads(seen['drain'][0]['body'])
    if dp.get('csrf_token')!='csrf-test': fail('opportunistic drain missing CSRF')
    # handleResponse must be safe on unrelated payloads.
    pg.evaluate("window.SoknaPushRuntime.handleResponse({success:true})")
    pg.close();b.close()
print('notification runtime browser PASS')
