"""Run checks from an extracted release bundle or a source tree; no provider sends."""
from pathlib import Path
import argparse,json,os,shutil,socket,subprocess,sys,tempfile,time,urllib.request

parser=argparse.ArgumentParser()
parser.add_argument('--php',default=shutil.which('php'))
args=parser.parse_args()
if not args.php:raise SystemExit('PHP CLI is required; use --php /absolute/path/to/php')
bundle=Path(__file__).resolve().parents[1]
app=bundle/'application' if (bundle/'application').is_dir() else bundle
env=dict(os.environ)
env.pop('APP_ENV_FILE',None)
env.update(APP_ENV='testing',DB_NAME='tabeeb_test_local',HMAC_SECRET='fixture-only-random-material-32-characters')
def run(command,**kw):
    result=subprocess.run(command,cwd=bundle,env=env,text=True,capture_output=True,timeout=120,**kw)
    if result.returncode:
        raise RuntimeError('Check failed: '+str(command)+'\n'+result.stdout+'\n'+result.stderr)
    if result.stdout.strip():print(result.stdout.strip(),flush=True)
    return result

php_files=[p for p in app.rglob('*.php') if 'vendor' not in p.relative_to(app).parts]
for p in php_files:
    result=subprocess.run([args.php,'-l',str(p)],capture_output=True,text=True)
    if result.returncode:raise RuntimeError(result.stdout+result.stderr)
print(f'Application PHP syntax: {len(php_files)} files passed.',flush=True)
for p in (bundle/'tests').glob('*.php'):run([args.php,'-l',str(p)])
for p in (bundle/'ops').glob('*.php'):run([args.php,'-l',str(p)])
if shutil.which('bash'):run(['bash','-n',str(bundle/'ops/release.sh')])
if shutil.which('bash'):
    run(['bash','-n',str(bundle/'ops/web_permissions.sh')])
    run([sys.executable,str(bundle/'tests/test_web_permissions.py')])
if shutil.which('node'):run(['node','--check',str(app/'app.js')])
if (bundle/'tests/test_provision_databases.py').is_file():
    run([sys.executable,str(bundle/'tests/test_provision_databases.py')])
result=run([args.php,str(bundle/'tests/contracts.php')])
if json.loads(result.stdout.strip().splitlines()[-1]).get('passed')!=23:raise RuntimeError('Missing contract completion evidence')
with tempfile.TemporaryDirectory(prefix='tabeeb-test-local-') as temp:
    for role in ['reader','editor']:
        db=Path(temp)/(role+'.sqlite');env['TEST_SQLITE_PATH']=str(db)
        run([sys.executable,str(bundle/'tests/fixture.py'),str(db)])
        result=run([args.php,str(bundle/'tests/baseline_admin.php'),role])
        evidence=json.loads(result.stdout.strip().splitlines()[-1])
        if evidence.get('role')!=role or evidence.get('passed')!=(8 if role=='reader' else 7):raise RuntimeError('Missing baseline completion evidence')
    for page, role in [('index','sales_rep'),('leads','sales_rep'),('lead-detail','sales_rep'),('users','super_admin')]:
        db=Path(temp)/(page+'-'+role+'.sqlite');env['TEST_SQLITE_PATH']=str(db)
        run([sys.executable,str(bundle/'tests/fixture.py'),str(db)])
        run([args.php,str(bundle/'tests/admin_roles.php'),page,role])
    for page in ['leads','lead-detail','business-info','services','post-edit']:
        db=Path(temp)/(page+'-denied.sqlite');env['TEST_SQLITE_PATH']=str(db)
        run([sys.executable,str(bundle/'tests/fixture.py'),str(db)])
        run([args.php,str(bundle/'tests/admin_roles.php'),page,'sales_rep','deny'])
    for mode in ['unit','dashboard','posts','blocked']:
        db=Path(temp)/('user-access-'+mode+'.sqlite');env['TEST_SQLITE_PATH']=str(db)
        run([sys.executable,str(bundle/'tests/fixture.py'),str(db)])
        run([args.php,str(bundle/'tests/user_access.php'),mode])
    db=Path(temp)/'http.sqlite';env['TEST_SQLITE_PATH']=str(db)
    run([sys.executable,str(bundle/'tests/fixture.py'),str(db)])
    with socket.socket() as s:
        s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    origin=f'http://127.0.0.1:{port}';env['APP_URL']=origin;env['TEST_ORIGIN']=origin
    with (Path(temp)/'http.log').open('w+') as log:
        server=subprocess.Popen([args.php,'-S',f'127.0.0.1:{port}','-t',str(app),str(bundle/'tests/router.php')],cwd=app,env=env,stdout=log,stderr=log)
        try:
            for _ in range(60):
                if server.poll() is not None:break
                try:
                    with urllib.request.urlopen(origin+'/contact',timeout=.5):break
                except OSError:time.sleep(.1)
            else:raise RuntimeError('Local PHP server did not become ready')
            if server.poll() is not None:raise RuntimeError('Local PHP server exited early')
            run([sys.executable,str(bundle/'tests/http_integration.py')])
        except Exception:
            log.flush();log.seek(0);print(log.read(),file=sys.stderr);raise
        finally:
            server.terminate()
            try:server.wait(timeout=5)
            except subprocess.TimeoutExpired:server.kill();server.wait()
print('Local checks passed. Hosting database migrations, web-server rules, SMTP and Google setup still require server verification.')
