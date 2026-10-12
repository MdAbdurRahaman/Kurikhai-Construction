"""Transfer a verified release via pinned SSH; stage and production config stays on host."""
import argparse,json,os,re,shlex,subprocess,tempfile
from pathlib import Path

def required(name):
    value=os.environ.get(name,'')
    if not value:raise ValueError('Missing GitHub setting: '+name)
    return value

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--bundle',required=True);args=parser.parse_args()
    bundle=Path(args.bundle).resolve()
    if not bundle.is_file():raise ValueError('Deployment ZIP is missing')
    host=required('CPANEL_HOST');user=required('CPANEL_USER');root=required('CPANEL_ACCOUNT_ROOT')
    port=int(required('CPANEL_SSH_PORT'));php=required('CPANEL_PHP_BIN')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) or not re.fullmatch(r'[A-Za-z0-9_]+',user):raise ValueError('Invalid SSH host/user')
    for path in [root,php]:
        if not re.fullmatch(r'/[A-Za-z0-9_./-]+',path) or '..' in Path(path).parts or path=='/' or 'public_html' in Path(path).parts:raise ValueError('Invalid private account root or PHP path')
    if not 1<=port<=65535:raise ValueError('Invalid SSH port')
    sha=required('GITHUB_SHA');run=required('GITHUB_RUN_ID');attempt=os.environ.get('GITHUB_RUN_ATTEMPT','1')
    if not re.fullmatch(r'[0-9a-f]{40,64}',sha) or not run.isdigit() or not attempt.isdigit():raise ValueError('Invalid run identity')
    private_key=required('CPANEL_SSH_PRIVATE_KEY');hosts=required('CPANEL_KNOWN_HOSTS')
    smoke_user=required('STAGING_SMOKE_USER');smoke_password=required('STAGING_SMOKE_PASSWORD')
    incoming=root+'/tabeeb-ci-incoming/'+sha+'-'+run+'-'+attempt
    destination=user+'@'+host
    with tempfile.TemporaryDirectory(prefix='tabeeb-ssh-') as tmp:
        key=Path(tmp)/'key';key.write_text(private_key.rstrip()+'\n');key.chmod(0o600)
        known=Path(tmp)/'known_hosts';known.write_text(hosts.rstrip()+'\n');known.chmod(0o600)
        options=['-i',str(key),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','ConnectTimeout=20','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=3']
        ssh=['ssh','-p',str(port),*options,destination]
        subprocess.run([*ssh,'umask 077 && mkdir -p '+shlex.quote(incoming)],check=True,timeout=60)
        subprocess.run(['scp','-P',str(port),*options,str(bundle),destination+':'+incoming+'/release.zip'],check=True,timeout=180)
        subprocess.run([*ssh,'unzip -q '+shlex.quote(incoming+'/release.zip')+' -d '+shlex.quote(incoming+'/bundle')],check=True,timeout=60)
        payload={'account_root':root,'php_bin':php,'smoke_user':smoke_user,'smoke_password':smoke_password}
        # Password is stdin JSON, never a command argument or displayed command.
        command=shlex.quote(php)+' '+shlex.quote(incoming+'/bundle/ops/ci_release.php')
        subprocess.run([*ssh,command],input=json.dumps(payload).encode(),check=True,timeout=900)
    print('Staging and production activation checks completed.')

if __name__=='__main__':
    try:main()
    except (ValueError,OSError,subprocess.SubprocessError) as error:
        # Do not print stdin payloads, passwords or private key contents.
        print('Deployment failed: '+str(error),file=__import__('sys').stderr);raise SystemExit(1)
