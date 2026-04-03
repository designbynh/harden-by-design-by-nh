#!/usr/bin/env bash
# Optional: fetch harden_by_nh_options row using mysql client + local-db.env
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${PLUGIN_DIR}/local-db.env"

if [[ ! -f "${ENV_FILE}" ]]; then
	echo "Missing ${ENV_FILE}. Copy local-db.env.example to local-db.env" >&2
	exit 1
fi

# shellcheck source=/dev/null
set -a
source "${ENV_FILE}"
set +a

if ! command -v mysql >/dev/null 2>&1; then
	echo "mysql client not found in PATH." >&2
	exit 1
fi

PREFIX="${TABLE_PREFIX:-wp_}"
SQL="SELECT option_id, autoload, option_value FROM ${PREFIX}options WHERE option_name = 'harden_by_nh_options' LIMIT 1\\G"

MYSQL_PWD="${DB_PASSWORD}" mysql -h "${DB_HOST}" -u "${DB_USER}" "${DB_NAME}" -e "${SQL}"
