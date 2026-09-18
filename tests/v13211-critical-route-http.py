#!/usr/bin/env python3
import os, sys, urllib.request, urllib.error
base=os.getenv('SOKNA_STAGING_BASE_URL','').rstrip('/')
cookie=os.getenv('SOKNA_STAGING_COOKIE','')
sub=os.getenv('SOKNA_STAGING_SUBSCRIBER_ID','')
strict=os.getenv('SOKNA_REQUIRE_HTTP_ROUTE_GATE')=='1'
def block(msg,code=2):
    print(('BLOCKED: ' if strict else 'UAT_REQUIRED: ')+msg)
    sys.exit(code if strict else 0)
if not (base and cookie and sub): block('staging URL/session/subscriber id missing; authenticated route smoke not executed.')
checks=[(f'/admin/subscribers.php?view={sub}','پرونده مشترک'),('/admin/subscribers.php','مشترکین'),('/admin/invoices.php','فاکتور')]
for route,needle in checks:
    req=urllib.request.Request(base+route,headers={'Cookie':cookie,'User-Agent':'Sokna-Release-Gate/1.32.14','Cache-Control':'no-cache'})
    try:
        with urllib.request.urlopen(req,timeout=20) as r:
            body=r.read().decode('utf-8','replace'); status=r.status; final=r.geturl()
    except Exception as e:
        print('HTTP route FAILED:',route,type(e).__name__,str(e));sys.exit(1)
    if status!=200 or needle not in body or 'Internal Server Error' in body or 'Fatal error' in body:
        print('HTTP route FAILED:',route,status,needle,final);sys.exit(1)
print('1.32.14 authenticated critical-route HTTP PASS')
