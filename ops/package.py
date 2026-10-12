#!/usr/bin/env python3
"""Explicit release inputs; generated runtime JSON, accounts, logs and originals are never copied."""
from pathlib import Path
import zipfile,argparse,hashlib,json
bundle=Path(__file__).resolve().parents[1]
p=bundle/'application' if (bundle/'application').is_dir() else bundle
a=argparse.ArgumentParser();a.add_argument('--output',required=True);args=a.parse_args()
public=['index.php','media.php','about.php','services.php','service.php','contact.php','blog.php','blog-post.php','projects.php','project.php','privacy.php','terms.php','404.php','sitemap.php','robots.txt','app.js','style.css','favicon.ico','.htaccess','googlea9c88d1e38989379.html']
files=[p/n for n in public if (p/n).is_file()]
for directory in ['admin','api','includes','config','vendor','fonts','css','js','images']:
 d=p/directory
 if not d.exists():continue
 for f in d.rglob('*'):
  if not f.is_file():continue
  rel=f.relative_to(p)
  if any(part in ['client-media','blog','legacy_html'] for part in rel.parts):continue
  if f.suffix in ['.log','.bak','.tmp','.zip','.sqlite','.rar'] or f.name=='.env' or f.is_symlink():continue
  files.append(f)
for n in ['migrate.php','preflight.php','create_admin.php','notification_worker.php','export_baseline.php','resolve_media_duplicates.php','configure_recipient.php','verify_smtp.php']:
 files.append(p/'scripts'/n)
manifest={f.relative_to(p).as_posix():hashlib.sha256(f.read_bytes()).hexdigest() for f in sorted(set(files))}
with zipfile.ZipFile(args.output,'w',zipfile.ZIP_DEFLATED)as z:
 for f in sorted(set(files)):z.write(f,'application/'+f.relative_to(p).as_posix())
 z.writestr('MANIFEST.json',json.dumps(manifest,indent=2))
 for d in ['ops','tests']:
  for f in sorted((bundle/d).rglob('*')):
   if f.is_file() and f.suffix in ['.php','.py','.sh','.txt','.js']:z.write(f,f.relative_to(bundle).as_posix())
 for n in ['START_HERE.txt','PRODUCTION_SETUP.txt','TEST_RESULTS.txt','.env.example','.env.staging.example']:
  if (bundle/n).exists():z.write(bundle/n,n)
print(json.dumps({'package':str(Path(args.output).resolve()),'immutable_application_files':len(manifest),'runtime_data_included':False}))
