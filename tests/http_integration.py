"""Real PHP HTTP handlers with a disposable SQL fixture. Does not send real email."""
import os,sys,re,json,time,uuid,sqlite3,urllib.request,urllib.error,http.cookiejar
base=os.environ.get('TEST_ORIGIN','http://127.0.0.1:8086');db=os.environ['TEST_SQLITE_PATH']
assert 'test' in db
jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar));passed=[]
def request(path,payload=None,origin=None):
    headers={'Origin':origin or base,'Content-Type':'application/json'}
    req=urllib.request.Request(base+path,data=None if payload is None else json.dumps(payload).encode(),headers=headers)
    try:r=client.open(req)
    except urllib.error.HTTPError as e:r=e
    return r.status,r.read().decode(),dict(r.headers)
def check(condition,name):
    assert condition,name
    passed.append(name)
def clear_rate():
    with sqlite3.connect(db)as c:c.execute('DELETE FROM rate_limits')
status,html,headers=request('/contact');check(status==200,'contact renders')
csrf=re.search(r'name="csrf_token" value="([^"]+)"',html).group(1);timing=re.search(r'name="form_loaded_ts" value="([^"]+)"',html).group(1)
time.sleep(2.1)
base_payload={'csrf_token':csrf,'form_loaded_ts':timing,'name':'HTTP Fixture','phone':'81234567','email':'first@example.invalid','service':'tiling-works','message':'Same project details','budget':'Under $5,000','property_type':'HDB Flat','preferred_contact':'email','source':'website_contact_page','consent':'on','idempotency_key':'req_'+uuid.uuid4().hex}
def post(p,expected):
    clear_rate();status,text,_=request('/api/leads',p);check(status==expected,'Expected HTTP '+str(expected)+' received '+str(status) if status==expected else 'HTTP failure '+text);return json.loads(text)
r=post(base_payload,201);check(r['success'] and not r.get('is_duplicate'),'first lead persisted')
r=post(base_payload,200);check(r.get('is_duplicate'),'unchanged replay')
for field,val in [('email','corrected@example.invalid'),('budget','$5,000 - $20,000'),('property_type','Condominium')]:
    p=dict(base_payload);p[field]=val;post(p,409)
    p['idempotency_key']='req_'+uuid.uuid4().hex;r=post(p,201);check(not r.get('is_duplicate'),'new request keeps changed '+field)
p=dict(base_payload);p['source']='website_quote_form';p.pop('consent');p['consent_mechanism']='submission_notice_consent';p['idempotency_key']='req_'+uuid.uuid4().hex;r=post(p,201);check(r['success'],'popup notice form persisted')
p=dict(base_payload);p['idempotency_key']='bad';post(p,422)
p=dict(base_payload);p['idempotency_key']='req_'+uuid.uuid4().hex;p['name']=['invalid'];post(p,422)
p=dict(base_payload);p['csrf_token']='invalid';post(p,403)
p=dict(base_payload);p['consent']='false';post(p,422)
clear_rate();status,_,_=request('/api/leads',base_payload,origin='https://other.invalid');check(status==403,'cross origin rejected')
for path in ['/','/services','/privacy','/terms','/about','/sitemap.xml']:
    status,body,headers=request(path);check(status==200,'public route '+path);check('noindex' in headers.get('X-Robots-Tag',''),'testing noindex '+path)
    for block in re.findall(r'<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>',body,re.S):json.loads(block)
status,_,_=request('/projects/serangoon-avenue-3-flooring-works');check(status==404,'draft project private')
status,_,_=request('/images/projects/serangoon-before.webp');check(status==404,'draft project image private')
with sqlite3.connect(db)as c:
    rows=c.execute('SELECT email,estimated_budget,property_type FROM leads').fetchall();check(len(rows)==5,'five different enquiries stored');c.execute('ALTER TABLE leads RENAME TO leads_offline')
clear_rate();p=dict(base_payload);p['idempotency_key']='req_'+uuid.uuid4().hex;status,_,_=request('/api/leads',p);check(status==503,'database read failure fails closed')
with sqlite3.connect(db)as c:c.execute('ALTER TABLE leads_offline RENAME TO leads')
clear_rate()
for i in range(6):status,_,_=request('/api/leads',base_payload)
check(status==429,'rate limit enforced')
print(json.dumps({'passed':len(passed),'checks':passed,'engine':'real PHP HTTP, disposable SQLite schema fixture; no provider sends'}))
