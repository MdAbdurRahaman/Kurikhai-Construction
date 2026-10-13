"""One-time cPanel database provisioning using the existing API token; never logs secrets."""
import json,os,re,ssl,sys,urllib.parse,urllib.request,urllib.error

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None

def setting(name):
    value=os.environ.get(name,'')
    if not value:raise ValueError('Missing GitHub secret/setting: '+name)
    return value

class Cpanel:
    def __init__(self,host,user,token):
        if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) or re.fullmatch(r'[0-9.]+',host):raise ValueError('Use the verified cPanel server hostname, not an IP address')
        if not re.fullmatch(r'[A-Za-z0-9_]{1,16}',user):raise ValueError('Invalid cPanel username')
        if any(c in token for c in ['\r','\n','\x00']):raise ValueError('Invalid API token formatting')
        self.origin='https://'+host+':2083';self.authorization='cpanel '+user+':'+token
        self.opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPSHandler(context=ssl.create_default_context()))
    def call(self,module,function,**parameters):
        request=urllib.request.Request(self.origin+'/execute/'+module+'/'+function,data=urllib.parse.urlencode(parameters).encode(),headers={'Authorization':self.authorization,'Content-Type':'application/x-www-form-urlencoded'},method='POST')
        try:
            with self.opener.open(request,timeout=60)as response:payload=json.load(response)
        except urllib.error.HTTPError as error:raise RuntimeError('cPanel request failed with HTTP '+str(error.code))from None
        result=payload.get('result',payload)
        if result.get('status')!=1:raise RuntimeError('cPanel rejected '+module+'.'+function+'; check account, token permissions and hosting support')
        return result.get('data')

def names(rows,field):
    if not isinstance(rows,list):raise RuntimeError('Unexpected cPanel '+field+' list response; no provisioning performed')
    values=set()
    for row in rows:
        if isinstance(row,str):values.add(row)
        elif isinstance(row,dict):
            value=row.get(field,row.get('name'))
            if not isinstance(value,str):raise RuntimeError('Unexpected cPanel '+field+' row; no provisioning performed')
            values.add(value)
        else:raise RuntimeError('Unexpected cPanel list entry')
    return values

def provision(api,user,passwords):
    owned=api.call('DomainInfo','list_domains')
    if not isinstance(owned,dict):raise RuntimeError('Unexpected domain-ownership response')
    domains={owned.get('main_domain','')}
    for key in ['addon_domains','parked_domains','sub_domains']:
        domains.update(owned.get(key) or [])
    if 'tabeebgroup.com' not in domains:raise RuntimeError('This cPanel account does not list tabeebgroup.com; refusing database changes')
    existing=names(api.call('Mysql','list_databases'),'database')
    users=names(api.call('Mysql','list_users'),'user')
    for password in passwords.values():
        if len(password)<20 or any(c in password for c in ['\r','\n','\x00']):raise ValueError('Use distinct 20+ character database passwords stored as GitHub secrets')
    if passwords['tbprod']==passwords['tbstg']:raise ValueError('Production and staging passwords must differ')
    privileges='ALTER,ALTER ROUTINE,CREATE,CREATE ROUTINE,CREATE TEMPORARY TABLES,CREATE VIEW,DELETE,DROP,EVENT,EXECUTE,INDEX,INSERT,LOCK TABLES,REFERENCES,SELECT,SHOW VIEW,TRIGGER,UPDATE'
    for suffix in ['tbprod','tbstg']:
        name=user+'_'+suffix
        if name not in existing:api.call('Mysql','create_database',name=name)
        if name not in users:api.call('Mysql','create_user',name=name,password=passwords[suffix])
        # Existing passwords and existing rows are not replaced.
        api.call('Mysql','set_privileges_on_database',user=name,database=name,privileges=privileges)
        print('Database and matching user prepared: '+name)
    print('No website files, existing passwords, records or domain mappings were replaced. Configure the private server .env files with the matching credentials next.')

def main():
    host=setting('CPANEL_API_HOST');user=setting('CPANEL_USERNAME');token=setting('CPANEL_API_TOKEN')
    passwords={'tbprod':setting('PRODUCTION_DB_PASSWORD'),'tbstg':setting('STAGING_DB_PASSWORD')}
    provision(Cpanel(host,user,token),user,passwords)

if __name__=='__main__':
    try:main()
    except (ValueError,RuntimeError,OSError)as error:print('Database setup stopped: '+str(error),file=sys.stderr);raise SystemExit(1)
