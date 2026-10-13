#!/usr/bin/env bash
# Apply web-readable modes only to release files and public blog uploads.
# Private configuration, application data and SQL backups stay owner-only.
set -Eeuo pipefail
[[ $# == 3 ]] || exit 2
ENV_ROOT=$(realpath -e "$1")
RELEASE_ID=$2
TARGET=$3
[[ "$ENV_ROOT" != / && "$ENV_ROOT" != */public_html* ]] || exit 2
[[ "$RELEASE_ID" =~ ^[a-zA-Z0-9_-]+$ ]] || exit 2
[[ "$TARGET" == staging || "$TARGET" == production ]] || exit 2
DEST="$ENV_ROOT/releases/$RELEASE_ID"
for path in releases shared shared/data shared/blog shared/projects backups "releases/$RELEASE_ID"; do
  [[ -d "$ENV_ROOT/$path" && ! -L "$ENV_ROOT/$path" ]] || { echo 'Unexpected directory mapping; permissions unchanged.' >&2; exit 1; }
done
[[ -f "$ENV_ROOT/shared/.env" && ! -L "$ENV_ROOT/shared/.env" ]] || exit 1
[[ -L "$DEST/.env" && $(readlink -f "$DEST/.env") == "$ENV_ROOT/shared/.env" ]] || exit 1
if [[ "$TARGET" == staging ]]; then
  [[ -f "$ENV_ROOT/shared/staging.htpasswd" && ! -L "$ENV_ROOT/shared/staging.htpasswd" ]] || exit 1
fi
# find does not follow the .env, data or media symlinks in the release.
find "$DEST" -type d -exec chmod 755 {} +
find "$DEST" -type f -exec chmod 644 {} +
chmod 711 "$ENV_ROOT" "$ENV_ROOT/releases" "$ENV_ROOT/shared"
chmod 700 "$ENV_ROOT/shared/data" "$ENV_ROOT/shared/projects" "$ENV_ROOT/backups"
chmod 600 "$ENV_ROOT/shared/.env"
find "$ENV_ROOT/shared/blog" -type d -exec chmod 755 {} +
find "$ENV_ROOT/shared/blog" -type f -exec chmod 644 {} +
if [[ "$TARGET" == staging ]]; then
  # Web-server authentication needs this hash file; it is outside the document root.
  chmod 644 "$ENV_ROOT/shared/staging.htpasswd"
fi
echo 'Web permissions configured; environment, application data and backups remain private.'
