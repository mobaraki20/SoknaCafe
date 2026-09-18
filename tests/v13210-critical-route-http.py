#!/usr/bin/env python3
import os, sys, urllib.request, urllib.error
base=os.getenv('SOKNA_STAGING_BASE_URL','').rstrip('/')
cookie=os.getenv('SOKNA_STAGING_COOKIE','')
sub=os.getenv('SOKNA_STAGING_SUBSCRIBER_ID','')
strict=os.getenv('SOKNA_REQUIRE_HTTP_ROUTE_GATE')=='1'
if not (base and cookie and sub):
    print('UAT_REQUIRED: staging URL/session/subscriber id missing; authenticated route smoke not executed.')
    sys.exit(2 if strict else 0)
for route,needle in [(f'/admin/subscribers.php?view={sub}','پرونده مشترک'),('/admin/subscribers.php','مشترکین'),('/admin/invoices.php','فاکتور')]:
    req=urllib.request.Request(base+route,headers={'Cookie':cookie,'User-Agent':'Sokna-Release-Gate/1.32.14'})
    try:
        with urllib.request.urlopen(req,timeout=15) as r:
            body=r.read().decode('utf-8','replace'); status=r.status
    except urllib.error.HTTPError as e:
        print('HTTP route FAILED:',route,e.code);sys.exit(1)
    if status!=200 or needle not in body or 'Internal Server Error' in body:
        print('HTTP route FAILED:',route,status,needle);sys.exit(1)
print('1.32.14 authenticated critical-route HTTP PASS')
