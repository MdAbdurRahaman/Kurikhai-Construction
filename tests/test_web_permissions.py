"""Exercise real chmod operations on disposable releases, never hosting files."""
from pathlib import Path
import os, stat, subprocess, tempfile

script = Path(__file__).resolve().parents[1] / 'ops/web_permissions.sh'
passed = 0
def check(condition):
    global passed
    assert condition
    passed += 1
def mode(path):
    return stat.S_IMODE(path.stat().st_mode)
with tempfile.TemporaryDirectory(prefix='tabeeb-web-permissions-') as temp:
    root = Path(temp) / 'environment'
    release = root / 'releases/test-release'
    for directory in [release, root/'shared/data', root/'shared/blog', root/'shared/projects', root/'backups']:
        directory.mkdir(parents=True, exist_ok=True)
        directory.chmod(0o700)
    env = root / 'shared/.env'; env.write_text('fixture-only'); env.chmod(0o600)
    auth = root / 'shared/staging.htpasswd'; auth.write_text('fixture-only'); auth.chmod(0o640)
    secret = root / 'shared/data/private.json'; secret.write_text('fixture-only'); secret.chmod(0o600)
    backup = root / 'backups/test.sql'; backup.write_text('fixture-only'); backup.chmod(0o600)
    asset = release / 'style.css'; asset.write_text('body {}'); asset.chmod(0o600)
    blog = root / 'shared/blog/image.webp'; blog.write_bytes(b'fixture'); blog.chmod(0o600)
    (release/'.env').symlink_to(env)
    (release/'data').symlink_to(root/'shared/data')
    outside = Path(temp)/'outside'; outside.mkdir(); outside.chmod(0o700)
    outside_file = outside/'private'; outside_file.write_text('fixture-only'); outside_file.chmod(0o600)
    (release/'unrelated-link').symlink_to(outside)
    result = subprocess.run(['bash', str(script), str(root), 'test-release', 'staging'], capture_output=True)
    check(result.returncode == 0)
    check(mode(asset) == 0o644 and mode(release) == 0o755)
    check(all(mode(root/p) == 0o711 for p in ['.', 'releases', 'shared']))
    check(mode(env) == 0o600)
    check(mode(secret) == 0o600 and mode(secret.parent) == 0o700)
    check(mode(backup) == 0o600 and mode(backup.parent) == 0o700)
    check(mode(blog) == 0o644 and mode(blog.parent) == 0o755)
    check(mode(auth) == 0o644 and not auth.is_relative_to(release))
    check(mode(outside) == 0o700 and mode(outside_file) == 0o600)
    for release_id, target in [('../outside', 'staging'), ('test-release', 'unexpected')]:
        result = subprocess.run(['bash', str(script), str(root), release_id, target], capture_output=True)
        check(result.returncode != 0)
    asset.chmod(0o600)
    (release/'.env').unlink(); (release/'.env').write_text('bad mapping')
    result = subprocess.run(['bash', str(script), str(root), 'test-release', 'staging'], capture_output=True)
    check(result.returncode != 0 and mode(asset) == 0o600)
print(f'Web permissions: {passed} isolation and release checks passed.')
