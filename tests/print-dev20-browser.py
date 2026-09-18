#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
import argparse, json, os, time

ROOT=Path(__file__).resolve().parents[1]
PUSH=ROOT/'assets/js/push-runtime.js'
DESIGNER=ROOT/'assets/js/print-template-designer.js'
SETTINGS=ROOT/'assets/js/printing-settings.js'

parser=argparse.ArgumentParser()
parser.add_argument('--case',dest='case_id',required=True,choices=['B33','B34','B37','B39','B46'])
parser.add_argument('--results',default=str(ROOT/'artifacts/web-print-dev20/browser'))
args=parser.parse_args()
outdir=Path(args.results); outdir.mkdir(parents=True,exist_ok=True)
assertions=[]; evidence={}
def check(cond,name,detail=None):
    assertions.append({'name':name,'pass':bool(cond),'detail':detail})
    if not cond: raise AssertionError(f'{name}: {detail}')

def base_designer_html():
    samples={'default':{'document_kind':'customer_final','title':'کافه سکنا','invoice_number':'فاکتور ۲۸','table_name':'میز ۸','display_date':'۱۶ مرداد · ۱۴:۳۶','actor_name':'مدیر','settlement_label':'تسویه مستقیم','sections':[{'items':[{'name':'پاستا','quantity':2,'unit_price':580000,'line_total':1160000}]}],'subtotal':1160000,'discount':0,'total':1160000}}
    destinations=[{'destination_key':'customer_receipt','label':'صندوق','paper_width_mm':80,'printable_width_mm':72.0}]
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body>
<form data-print-template-form>
<input name="template_key" value="customer"><input name="name" value="پیش‌نویس">
<input name="base_font_size" value="23"><input name="title_font_size" value="30"><input name="table_font_size" value="28"><input name="line_spacing" value="5"><input name="margin" value="9"><input name="footer" value="سپاس">
<input name="show_time" type="checkbox" checked><input name="show_actor" type="checkbox"><input name="show_section_titles" type="checkbox" checked>
<select name="design[density]"><option value="compact" selected>compact</option></select>
<select name="design[header_alignment]"><option value="center" selected>center</option></select>
<select name="design[item_layout]"><option value="columnar" selected>columnar</option></select>
<select name="design[separator_style]"><option value="solid" selected>solid</option></select>
<input data-reorder-output value='["brand","meta","items","summary","settlement","footer"]'>
<input name="labels[subtotal]" value="جمع اقلام"><input name="labels[discount]" value="تخفیف"><input name="labels[total]" value="جمع نهایی"><input name="labels[settlement]" value="نحوه ثبت">
</form>
<select data-preview-destination><option value="customer_receipt" selected>صندوق</option></select>
<select data-preview-width><option value="80" selected>80</option></select>
<select data-preview-scenario><option value="default" selected>default</option></select>
<div data-preview-mode></div><div data-thermal-preview></div>
<script id="print-template-samples" type="application/json">{json.dumps(samples,ensure_ascii=False)}</script>
<script id="print-template-preview-destinations" type="application/json">{json.dumps(destinations,ensure_ascii=False)}</script>
</body></html>'''

with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    try:
        if args.case_id=='B33':
            page=browser.new_page(viewport={'width':900,'height':700})
            page.set_content('<!doctype html><html><body><pre id="result">RUNNING</pre></body></html>')
            page.evaluate("""() => {
              window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL='https://sokna.test/cap';
              window.__wakeCalls=[]; window.__firstWake=true;
              window.fetch=(url,options={})=>{
                if(String(url).startsWith('https://sokna.test/cap')) return Promise.resolve({ok:true,json:async()=>({success:true,bridges:[
                  {agent_id:7,destination_key:'customer_receipt',port:17777,pairing_id:'PAIRING_0123456789_ABCDEFGH'},
                  {agent_id:7,destination_key:'prep_shared',port:17777,pairing_id:'PAIRING_0123456789_ABCDEFGH'}
                ]})});
                if(String(url).includes('/v1/wake')){
                  window.__wakeCalls.push(JSON.parse(options.body));
                  if(window.__firstWake){window.__firstWake=false;return new Promise(resolve=>setTimeout(()=>resolve({ok:true,json:async()=>({success:true})}),300));}
                  return Promise.resolve({ok:true,json:async()=>({success:true})});
                }
                return Promise.resolve({ok:true,json:async()=>({})});
              };
            }""")
            page.add_script_tag(path=str(PUSH))
            page.evaluate("""() => {
              const exp=new Date(Date.now()+60000).toISOString();
              const first={protocol_version:1,request_id:'wake-browser-0001',expires_at:exp,jobs:[
                {job_id:1,destination_key:'customer_receipt',status:'pending'},
                {job_id:2,destination_key:'prep_shared',status:'pending'}]};
              const second={protocol_version:1,request_id:'wake-browser-0002',expires_at:exp,jobs:[{job_id:3,destination_key:'customer_receipt',status:'pending'}]};
              void window.SoknaPushRuntime.printWake(first);
              setTimeout(()=>void window.SoknaPushRuntime.printWake(second),40);
              setTimeout(()=>{
                const a=window.__wakeCalls[0]?.job_ids||[], b=window.__wakeCalls[1]?.job_ids||[];
                const pass=window.__wakeCalls.length===2 && a.join(',')==='1,2' && b.join(',')==='3';
                document.getElementById('result').textContent=pass?'PASS B33 wake coalescing/in-flight preservation':'FAIL '+JSON.stringify(window.__wakeCalls);
              },900);
            }""")
            page.wait_for_function("document.getElementById('result').textContent !== 'RUNNING'",timeout=5000)
            text=page.locator('#result').inner_text()
            check(text.startswith('PASS'),'two wakes during in-flight are both delivered',text)
            evidence['result_text']=text
            evidence['wake_calls']=page.evaluate('window.__wakeCalls')
            page.screenshot(path=str(outdir/'B33-wake.png'))
            page.close()

        elif args.case_id=='B34':
            page=browser.new_page(viewport={'width':900,'height':700})
            page.set_content('<!doctype html><html><body><div id="status">ready</div></body></html>')
            page.evaluate("""() => {
              window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL='https://sokna.test/cap';
              window.__capCalls=0;
              window.fetch=(url,opts={})=>{
                if(String(url).startsWith('https://sokna.test/cap')){
                  window.__capCalls++;
                  if(window.__capCalls===1) return new Promise((resolve,reject)=>{
                    const sig=opts.signal; const abort=()=>reject(new DOMException('aborted','AbortError'));
                    if(sig?.aborted) abort(); else sig?.addEventListener('abort',abort,{once:true});
                  });
                  return Promise.resolve({ok:true,json:async()=>({success:true,bridges:[]})});
                }
                return Promise.resolve({ok:true,json:async()=>({success:true})});
              };
            }""")
            page.add_script_tag(path=str(PUSH))
            elapsed=page.evaluate("""async()=>{
              const exp=new Date(Date.now()+60000).toISOString(); const start=performance.now();
              await window.SoknaPushRuntime.printWake({protocol_version:1,request_id:'wake-timeout-0001',expires_at:exp,jobs:[{job_id:11,destination_key:'customer_receipt',status:'pending'}]});
              const first=performance.now()-start;
              await window.SoknaPushRuntime.printWake({protocol_version:1,request_id:'wake-timeout-0002',expires_at:exp,jobs:[{job_id:12,destination_key:'customer_receipt',status:'pending'}]});
              return {first,calls:window.__capCalls};
            }""")
            check(elapsed['first']<2600,'capability fetch has bounded deadline',elapsed)
            check(elapsed['calls']==2,'in-flight lock is released after timeout',elapsed)
            evidence.update(elapsed)
            page.close()

        elif args.case_id in ('B37','B39'):
            page=browser.new_page(viewport={'width':900,'height':900})
            page.set_content(base_designer_html())
            tiny_png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nE0AAAAASUVORK5CYII='
            if args.case_id=='B37':
                page.evaluate("""(png)=>{
                  window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL='https://sokna.test/cap'; window.__previewCalls=[];
                  window.fetch=(url,opts={})=>{
                    if(String(url).startsWith('https://sokna.test/cap')) return Promise.resolve({ok:true,json:async()=>({success:true,bridges:[{agent_id:7,destination_key:'customer_receipt',destination_label:'صندوق',paper_width_mm:80,printable_width_mm:72,port:17777,pairing_id:'PAIRING_0123456789_ABCDEFGH',exact_preview_ready:true,render_profile:{dpi_x:203,dpi_y:203}}]})});
                    if(String(url).includes('/v1/preview')){
                      const body=JSON.parse(opts.body); window.__previewCalls.push(body);
                      const delay=body.revision===1?700:70;
                      return new Promise((resolve,reject)=>{
                        const timer=setTimeout(()=>resolve({ok:true,json:async()=>({success:true,revision:body.revision,session_id:body.session_id,image_base64:png,width:body.revision===1?111:222,height:333,dpi_x:203,dpi_y:203})}),delay);
                        const sig=opts.signal; const abort=()=>{clearTimeout(timer);reject(new DOMException('aborted','AbortError'));};
                        if(sig?.aborted)abort(); else sig?.addEventListener('abort',abort,{once:true});
                      });
                    }
                    return Promise.reject(new Error('unexpected fetch '+url));
                  };
                }""",tiny_png)
                page.add_script_tag(path=str(DESIGNER))
                page.wait_for_function('window.__previewCalls.length >= 1',timeout=3000)
                page.locator('input[name="base_font_size"]').fill('24')
                page.wait_for_function('window.__previewCalls.length >= 2',timeout=3000)
                page.wait_for_timeout(300)
                mode=page.locator('[data-preview-mode]').inner_text()
                calls=page.evaluate('window.__previewCalls.map(x=>({revision:x.revision,session_id:x.session_id,dpi_x:x.dpi_x,dpi_y:x.dpi_y}))')
                check(len(calls)>=2,'new revision is sent after form change',calls)
                check(calls[-1]['revision']>calls[0]['revision'],'revision increases before next response',calls)
                check(len({c['session_id'] for c in calls})==1,'designer session is stable across revisions',calls)
                check('222×333px' in mode and '111×333px' not in mode,'stale response cannot overwrite latest preview',mode)
                evidence['calls']=calls; evidence['final_mode']=mode
                page.screenshot(path=str(outdir/'B37-preview-revision.png'))
            else:
                # Three independent failure modes: busy, timeout-like abort, invalid image.
                modes=[]
                for behavior in ['busy','abort','invalid']:
                    page.set_content(base_designer_html())
                    page.evaluate("""([behavior,png])=>{
                      window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL='https://sokna.test/cap'; window.__previewCount=0;
                      window.fetch=(url,opts={})=>{
                        if(String(url).startsWith('https://sokna.test/cap')) return Promise.resolve({ok:true,json:async()=>({success:true,bridges:[{agent_id:7,destination_key:'customer_receipt',destination_label:'صندوق',paper_width_mm:80,printable_width_mm:72,port:17777,pairing_id:'PAIRING_0123456789_ABCDEFGH',exact_preview_ready:true,render_profile:{dpi_x:203,dpi_y:203}}]})});
                        if(String(url).includes('/v1/preview')){
                          window.__previewCount++;
                          const body=JSON.parse(opts.body);
                          if(behavior==='busy')return Promise.resolve({ok:false,status:429,json:async()=>({success:false,code:'preview_busy',revision:body.revision,session_id:body.session_id})});
                          if(behavior==='abort')return Promise.reject(new DOMException('aborted','AbortError'));
                          return Promise.resolve({ok:true,json:async()=>({success:true,revision:body.revision,session_id:body.session_id,image_base64:'bm90LXBuZw==',width:10,height:10,dpi_x:203,dpi_y:203})});
                        }
                        return Promise.reject(new Error('unexpected'));
                      };
                    }""",[behavior,tiny_png])
                    page.add_script_tag(path=str(DESIGNER))
                    page.wait_for_timeout(900)
                    mode=page.locator('[data-preview-mode]').inner_text()
                    count=page.evaluate('window.__previewCount')
                    form_val=page.locator('input[name="base_font_size"]').input_value()
                    check(count==1,f'{behavior}: preview request is bounded/no retry storm',{'count':count,'mode':mode})
                    check('پیش‌نمایش دقیق' not in mode,f'{behavior}: failure never receives exact label',mode)
                    check(form_val=='23',f'{behavior}: form remains intact',form_val)
                    modes.append({'behavior':behavior,'count':count,'mode':mode})
                evidence['modes']=modes
                page.screenshot(path=str(outdir/'B39-preview-fail-closed.png'))
            page.close()

        elif args.case_id=='B46':
            page=browser.new_page(viewport={'width':390,'height':844})
            page.set_content('''<!doctype html><html><body style="height:2000px"><section data-print-live-status data-snapshot-url="/snapshot"><strong data-print-live-label>old</strong><b data-print-live-agents>0/1</b><b data-print-live-destinations>0/1</b><b data-print-live-problems>1</b></section><form data-print-destination><input id="unsaved" value="original"><select data-print-agent-select="primary"><option value="0">none</option></select><select data-print-printer-select="primary"></select></form><script id="printing-printers-data" type="application/json">{}</script></body></html>''')
            page.evaluate("""() => {
              window.__intervals=[]; window.setInterval=(fn,ms)=>{window.__intervals.push(fn);return 1;};
              window.__fetchCount=0;
              window.fetch=async()=>{window.__fetchCount++; await new Promise(r=>setTimeout(r,80)); return {ok:true,json:async()=>({success:true,overall:{label:'new',healthy:true},online_agents:1,agent_count:1,ready_destinations:1,destination_count:1,problem_count:0})};};
            }""")
            page.add_script_tag(path=str(SETTINGS))
            page.locator('#unsaved').fill('unsaved-change'); page.locator('#unsaved').focus(); page.evaluate('window.scrollTo(0,400)')
            before=page.evaluate('({value:document.querySelector("#unsaved").value,active:document.activeElement.id,scrollY:window.scrollY})')
            # Fire two refreshes in the same task; the second must see busy=true and return.
            page.evaluate('void window.__intervals[0](); void window.__intervals[0]();')
            page.wait_for_timeout(180)
            after=page.evaluate('({value:document.querySelector("#unsaved").value,active:document.activeElement.id,scrollY:window.scrollY,label:document.querySelector("[data-print-live-label]").textContent,fetchCount:window.__fetchCount})')
            check(after['value']==before['value']=='unsaved-change','unsaved form input is preserved',{'before':before,'after':after})
            check(after['active']==before['active']=='unsaved','focus is preserved',{'before':before,'after':after})
            check(abs(after['scrollY']-before['scrollY'])<=1,'scroll position is preserved',{'before':before,'after':after})
            check(after['label']=='new','read-only snapshot still refreshes status',after)
            check(after['fetchCount']==1,'out-of-order refresh is prevented by single in-flight owner',after)
            evidence={'before':before,'after':after}
            page.screenshot(path=str(outdir/'B46-refresh-mobile.png'),full_page=False)
            page.set_viewport_size({'width':1440,'height':900}); page.screenshot(path=str(outdir/'B46-refresh-desktop.png'),full_page=False)
            page.close()
    finally:
        browser.close()

result={'case_id':args.case_id,'status':'PASS','assertions':assertions,'evidence':evidence,'browser':'Chromium /usr/bin/chromium','run_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime())}
(outdir/f'{args.case_id}.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
print(f"PASS {args.case_id} assertions={len(assertions)}")
