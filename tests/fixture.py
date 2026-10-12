"""Portable schema adapter for PHP application tests. SQL migrations are tested separately by MariaDB."""
from pathlib import Path
import re,sqlite3,json,sys
root=Path(__file__).resolve().parents[1]
def statements(s):
    s=re.sub(r'--[^\n]*','',s);out=[];buf=[];quote=None;escape=False
    for c in s:
        buf.append(c)
        if escape:escape=False;continue
        if quote:
            if c=='\\':escape=True
            elif c==quote:quote=None
        elif c in "'\"`":quote=c
        elif c==';':out.append(''.join(buf));buf=[]
    return out
path=Path(sys.argv[1]);path.unlink(missing_ok=True);conn=sqlite3.connect(path)
files=[root/'config/schema.sql',root/'config/migrations/002_repair_core_and_projects.sql',root/'config/migrations/004_production_contracts.sql']
for f in files:
    for st in statements(f.read_text(encoding='utf-8')):
        if not st.strip().startswith('CREATE TABLE'):continue
        st=re.sub(r'\)\s*ENGINE=.*?;',');',st,flags=re.S)
        st=re.sub(r"ENUM\(\s*(?:'[^']*'\s*,?\s*)+\)", 'TEXT', st)
        st=st.replace('INT UNSIGNED AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT').replace('BIGINTEGER','INTEGER')
        st=re.sub(r'\b(?:UNSIGNED|AUTO_INCREMENT)\b','',st)
        st=re.sub(r'ON UPDATE CURRENT_TIMESTAMP','',st)
        st=re.sub(r'^\s*INDEX[^\n]*\n','',st,flags=re.M)
        st=re.sub(r'UNIQUE KEY\s+`?\w+`?\s*\(', 'UNIQUE (',st)
        st=re.sub(r',\s*\)',')',st)
        try:conn.execute(st)
        except sqlite3.OperationalError:
            print(st);raise
conn.execute('CREATE UNIQUE INDEX uniq_rate_key_action ON rate_limits(rate_key,action_type)')
# Columns added by versioned ALTER statements.
for table,col,typ in [('leads','payload_hash','TEXT'),('users','session_version','INTEGER DEFAULT 1')]:
    cols=[x[1] for x in conn.execute(f'PRAGMA table_info({table})')]
    if col not in cols:conn.execute(f'ALTER TABLE {table} ADD COLUMN {col} {typ}')
for f in files[:2]:
    for st in statements(f.read_text(encoding='utf-8')):
        if not re.match(r'\s*INSERT(?: IGNORE)? INTO `?(roles|permissions|role_permissions|marketing_baseline|integration_verifications|projects|project_media)`?\b',st):continue
        st=st.replace('INSERT IGNORE','INSERT OR IGNORE');st=re.sub(r'ON DUPLICATE KEY UPDATE.*?;',';',st,flags=re.S)
        try:conn.execute(st)
        except sqlite3.IntegrityError:pass
# Runtime seed services and posts.
for item in json.loads((root/'config/bootstrap/services.json').read_text(encoding='utf-8')):
    fields={'id':item['id'],'slug':item['slug'],'name':item['name'],'category_name':item.get('category_name','Contracting'),'overview':item.get('overview',''),'image':item.get('image',''),'is_published':1}
    for k in ['features','process','faqs']:fields[k+'_json']=json.dumps(item.get(k,[]))
    conn.execute('INSERT INTO services('+','.join(fields)+') VALUES ('+','.join('?' for _ in fields)+')',list(fields.values()))
conn.commit();print(json.dumps({'sqlite_fixture':str(path),'tables':len(list(conn.execute("SELECT name FROM sqlite_master WHERE type='table'")))}))
