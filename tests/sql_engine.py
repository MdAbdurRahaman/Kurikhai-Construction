"""Execute actual migrations in MariaDB's native bootstrap engine, without listeners.
Requires an EMPTY disposable initialized datadir and explicit MARIADB_BIN / MARIADB_BASE.
Never point this at a production datadir.
"""
from pathlib import Path
import os,subprocess,sys,json
root=Path(__file__).resolve().parents[1]
binary=os.environ['MARIADB_BIN'];basedir=os.environ['MARIADB_BASE'];datadir=os.environ['TEST_MARIADB_DATADIR']
assert 'test' in Path(datadir).name or 'production_work/mysql-data' in datadir
schema=(root/'config/schema.sql').read_text();migrations=list(sorted((root/'config/migrations').glob('*.sql')))
old=os.environ.get('OLD_002_FILE')
def run(label,sql):
    r=subprocess.run([binary,'--no-defaults','--bootstrap','--basedir='+basedir,'--datadir='+datadir,'--user=root','--skip-log-bin','--sql-mode=STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'],input=sql,text=True,capture_output=True)
    Path(os.environ.get('TEST_LOG_DIR','/tmp'),label+'.log').write_text(r.stderr)
    if r.returncode:print(r.stderr[-2000:]);raise SystemExit(r.returncode)
def db(name):return 'DROP DATABASE IF EXISTS '+name+';\nCREATE DATABASE '+name+';\nUSE '+name+';\n'
checks="""
CREATE TABLE IF NOT EXISTS engine_assertions (ok INT CHECK(ok=1));
INSERT INTO engine_assertions SELECT IF(COUNT(*)=1,1,0) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='payload_hash';
INSERT INTO engine_assertions SELECT IF(COUNT(*)=0,1,0) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=6 AND p.slug='baseline.manage';
INSERT INTO engine_assertions SELECT IF(COUNT(*)=1,1,0) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limits' AND INDEX_NAME='uniq_rate_key_action' AND SEQ_IN_INDEX=1 AND NON_UNIQUE=0;
"""
fresh=db('tabeeb_test_fresh')+schema+'\n'+''.join(f.read_text()+'\n' for f in migrations)+checks
run('sql-fresh',fresh)
run('sql-repeat','USE tabeeb_test_fresh;\n'+''.join(f.read_text()+'\n' for f in migrations)+checks)
if old:
    upgrade=db('tabeeb_test_upgrade')+schema+'\n'+Path(old).read_text()+'\n'
    upgrade+="INSERT INTO projects(id,slug,title,location_label,public_location,factual_summary,featured_image) VALUES ('unrelated','unrelated','Existing project','private','Singapore','Existing client content','/images/existing.webp');\n"
    for i in range(1,6):upgrade+=f"INSERT INTO project_media(id,project_id,media_stage,file_path,alt_text) VALUES ({i},'unrelated','gallery','/images/existing-{i}.webp','Existing {i}');\n"
    upgrade+='\n'.join(f.read_text() for f in migrations if not f.name.startswith('002_'))+'\n'+checks+"INSERT INTO engine_assertions SELECT IF(COUNT(*)=5,1,0) FROM project_media WHERE project_id='unrelated' AND id BETWEEN 1 AND 5;\n"
    run('sql-upgrade',upgrade)
print(json.dumps({'mariadb_engine_cases':['fresh','repeat']+(['previous-002-upgrade-existing-media']if old else []),'strict_mode':True,'network_listeners':False,'php_driver_or_mysql_8_test':False}))
