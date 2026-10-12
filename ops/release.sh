#!/usr/bin/env bash
# Run from an unpacked release bundle on the target host. No web-accessible extractor.
set -Eeuo pipefail
umask 077
if [[ $# -lt 3 ]]; then echo 'Usage: bash ops/release.sh prepare|activate|rollback /absolute/environment-root production|staging [release-id]' >&2; exit 2; fi
ACTION=$1
ENV_ROOT=$(realpath -m "$2")
TARGET=$3
[[ "$TARGET" == production || "$TARGET" == staging ]] || exit 2
[[ "$ENV_ROOT" == /* && "$ENV_ROOT" != / && "$ENV_ROOT" != */public_html* ]] || { echo 'Use a private root outside public_html.' >&2; exit 2; }
PHP_BIN=${PHP_BIN:-php}
command -v "$PHP_BIN" >/dev/null
mkdir -p "$ENV_ROOT/releases" "$ENV_ROOT/shared/data" "$ENV_ROOT/shared/blog" "$ENV_ROOT/shared/projects" "$ENV_ROOT/backups"
exec 9>"$ENV_ROOT/.release-lock"
flock -n 9 || { echo 'Another release is active.' >&2; exit 1; }
ENV_FILE="$ENV_ROOT/shared/.env"
[[ -f "$ENV_FILE" ]] || { echo 'Provision shared/.env using the environment template first.' >&2; exit 1; }
export APP_ENV_FILE="$ENV_FILE"
if [[ "$ACTION" == prepare ]]; then
  SOURCE=$(cd "$(dirname "$0")/.." && pwd -P)
  RELEASE_ID=$(date -u +%Y%m%dT%H%M%SZ)-$(openssl rand -hex 4)
  DEST="$ENV_ROOT/releases/$RELEASE_ID"
  mkdir "$DEST"
  # Package application/ contains only allowlisted immutable files.
  [[ -d "$SOURCE/application" ]] || { echo 'Use the generated release bundle, not an arbitrary source tree.' >&2; exit 1; }
  "$PHP_BIN" "$SOURCE/ops/verify_package.php"
  cp -a "$SOURCE/application/." "$DEST/"
  ln -s "$ENV_FILE" "$DEST/.env"
  ln -s "$ENV_ROOT/shared/data" "$DEST/data"
  mkdir -p "$DEST/images"
  ln -s "$ENV_ROOT/shared/blog" "$DEST/images/blog"
  printf '%s\n' '<FilesMatch "\.(php[0-9]?|phtml|phar|cgi|pl)$">' 'Require all denied' '</FilesMatch>' > "$ENV_ROOT/shared/blog/.htaccess"
  if [[ -d "$DEST/images/projects" ]]; then
    cp -an "$DEST/images/projects/." "$ENV_ROOT/shared/projects/"
    rm -rf "$DEST/images/projects" # only the new inactive release copy
  fi
  ln -s "$ENV_ROOT/shared/projects" "$DEST/images/projects"
  "$PHP_BIN" "$DEST/scripts/preflight.php" --expect="$TARGET"
  # Backup uses a private temporary client file; passwords never appear in argv/logs.
  CLIENT=$(mktemp "$ENV_ROOT/.db-client.XXXXXX")
  trap 'rm -f "$CLIENT"' EXIT
  "$PHP_BIN" "$SOURCE/ops/db_client.php" > "$CLIENT"
  DUMP_BIN=${DUMP_BIN:-mysqldump}
  command -v "$DUMP_BIN" >/dev/null
  "$DUMP_BIN" --defaults-extra-file="$CLIENT" --single-transaction --routines --triggers --events --databases "$("$PHP_BIN" "$SOURCE/ops/db_client.php" --name)" > "$ENV_ROOT/backups/$RELEASE_ID.sql"
  [[ -s "$ENV_ROOT/backups/$RELEASE_ID.sql" ]] || exit 1
  "$PHP_BIN" "$DEST/scripts/migrate.php" "${SEED_FLAG:---no-seed}"
  "$PHP_BIN" "$SOURCE/ops/configure_release.php" "$DEST" "$ENV_ROOT" "$TARGET"
  printf '%s\n' "$RELEASE_ID" > "$ENV_ROOT/prepared"
  echo "Prepared $RELEASE_ID. Site remains unchanged. Provision admin/recipients, test this candidate on staging, then activate."
elif [[ "$ACTION" == activate ]]; then
  RELEASE_ID=${4:-$(cat "$ENV_ROOT/prepared")}
  [[ "$RELEASE_ID" =~ ^[a-zA-Z0-9_-]+$ ]] || exit 2
  DEST="$ENV_ROOT/releases/$RELEASE_ID"
  "$PHP_BIN" "$DEST/scripts/preflight.php" --expect="$TARGET" --schema --release-check
  if [[ -L "$ENV_ROOT/current" ]]; then readlink -f "$ENV_ROOT/current" > "$ENV_ROOT/previous"; fi
  ln -s "$DEST" "$ENV_ROOT/current.next"
  mv -Tf "$ENV_ROOT/current.next" "$ENV_ROOT/current"
  SOURCE=$(cd "$(dirname "$0")/.." && pwd -P)
  if ! "$PHP_BIN" "$SOURCE/ops/smoke.php" "$RELEASE_ID"; then
    if [[ -f "$ENV_ROOT/previous" ]]; then
      ln -s "$(cat "$ENV_ROOT/previous")" "$ENV_ROOT/current.next"
      mv -Tf "$ENV_ROOT/current.next" "$ENV_ROOT/current"
    else rm "$ENV_ROOT/current"; fi
    echo 'HTTP check failed; activation reverted. Database backup remains available for reviewed recovery.' >&2
    exit 1
  fi
  echo 'Activated and HTTP-validated.'
elif [[ "$ACTION" == rollback ]]; then
  PREVIOUS=$(cat "$ENV_ROOT/previous")
  [[ "$PREVIOUS" == "$ENV_ROOT/releases/"* && -d "$PREVIOUS" ]] || exit 1
  ln -s "$PREVIOUS" "$ENV_ROOT/current.next"
  mv -Tf "$ENV_ROOT/current.next" "$ENV_ROOT/current"
  echo 'Previous code release restored. Additive database schema was retained; database restore is a separate reviewed recovery operation.'
else exit 2; fi
