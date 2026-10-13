"""Safety branches in the actual API provisioner; controlled responses, no provider calls."""
from pathlib import Path
import contextlib,importlib.util,io
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('provision',root/'ops/provision_databases.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class Fake:
    def __init__(self,existing=False,owned=True):self.calls=[];self.existing=existing;self.owned=owned
    def call(self,mod,function,**params):
        self.calls.append((mod,function,params))
        if function=='list_domains':return {'main_domain':'tabeebgroup.com' if self.owned else 'other.invalid','addon_domains':[],'parked_domains':[],'sub_domains':[]}
        if function=='list_databases':return [{'database':'tabegomk_'+x}for x in ['tbprod','tbstg']] if self.existing else []
        if function=='list_users':return [{'user':'tabegomk_'+x}for x in ['tbprod','tbstg']] if self.existing else []
        return None
passwords={'tbprod':'fixture-production-password-1','tbstg':'fixture-staging-password-2'}
checks=0
for existing in [False,True]:
    fake=Fake(existing=existing);capture=io.StringIO()
    with contextlib.redirect_stdout(capture):module.provision(fake,'tabegomk',passwords)
    assert all(p not in capture.getvalue() for p in passwords.values());checks+=1
    creates=[c for c in fake.calls if c[1] in ['create_database','create_user']]
    assert len(creates)==(0 if existing else 4);checks+=1
    grants=[c for c in fake.calls if c[1]=='set_privileges_on_database']
    assert len(grants)==2 and all(c[2]['user']==c[2]['database'] for c in grants);checks+=1
fake=Fake(owned=False)
try:module.provision(fake,'tabegomk',passwords);raise AssertionError('Unowned domain accepted')
except RuntimeError:assert len(fake.calls)==1;checks+=1
try:module.provision(Fake(),'tabegomk',{'tbprod':'same-password-fixture-123','tbstg':'same-password-fixture-123'});raise AssertionError('Same passwords accepted')
except ValueError:checks+=1
try:module.names([{'unexpected':'shape'}],'database');raise AssertionError('Unexpected list accepted')
except RuntimeError:checks+=1
print(f'API provisioner: {checks} safety checks passed; controlled responses, no provider calls.')
