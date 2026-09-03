#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_PATH="${BASH_SOURCE[0]:-}"
SCRIPT_DIR=""
if [[ -n "$SCRIPT_PATH" ]]; then
    SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_PATH")" && pwd)"
fi
INSTALLER_SERVER="$SCRIPT_DIR/installer/server.py"
INSTALLER_PAGE="$SCRIPT_DIR/installer/page.html"
SOURCE_ARCHIVE_URL="${WL_TRADERS_SOURCE_ARCHIVE_URL:-https://codeload.github.com/niiikkid/wl.traders.p2p/tar.gz/refs/heads/main}"

# This branch makes `curl -fsSL .../install.sh | sudo bash` self-contained:
# the small bootstrap script downloads the matching project source without Git.
if [[ ! -f "$INSTALLER_SERVER" || ! -f "$INSTALLER_PAGE" ]]; then
    if [[ ${EUID} -ne 0 ]]; then
        echo "Запустите одной командой от root: curl -fsSL https://raw.githubusercontent.com/niiikkid/wl.traders.p2p/main/install.sh | sudo bash"
        exit 1
    fi

    command -v curl >/dev/null 2>&1 || {
        apt-get update
        DEBIAN_FRONTEND=noninteractive apt-get install -y curl ca-certificates
    }
    command -v tar >/dev/null 2>&1 || {
        apt-get update
        DEBIAN_FRONTEND=noninteractive apt-get install -y tar gzip
    }

    SOURCE_DIR="$(mktemp -d /tmp/wl-traders-source.XXXXXX)"
    trap 'rm -rf "$SOURCE_DIR"' EXIT
    echo "Скачиваю исходный код WL Traders…"
    curl --fail --location --retry 3 --retry-delay 2 "$SOURCE_ARCHIVE_URL" -o "$SOURCE_DIR/source.tar.gz"
    tar -xzf "$SOURCE_DIR/source.tar.gz" -C "$SOURCE_DIR"
    PROJECT_SOURCE="$(find "$SOURCE_DIR" -mindepth 1 -maxdepth 1 -type d -name 'wl.traders.p2p-*' -print -quit)"

    if [[ -z "$PROJECT_SOURCE" || ! -f "$PROJECT_SOURCE/installer/server.py" || ! -f "$PROJECT_SOURCE/installer/page.html" ]]; then
        echo "Не удалось получить полный архив исходного кода WL Traders."
        exit 1
    fi

    exec bash "$PROJECT_SOURCE/install.sh"
fi

if [[ ${EUID} -ne 0 ]]; then
    echo "Запустите установщик от root: sudo ./install.sh"
    exit 1
fi

if [[ ! -f /etc/os-release ]]; then
    echo "Не удалось определить операционную систему. Нужна Ubuntu."
    exit 1
fi

# shellcheck disable=SC1091
source /etc/os-release
if [[ ${ID:-} != "ubuntu" || ${VERSION_ID:-} != "26.04" ]]; then
    echo "Этот установщик рассчитан только на Ubuntu 26.04. Обнаружено: ${PRETTY_NAME:-unknown}"
    exit 1
fi

install -d -m 0755 /run/lock
exec 9>/run/lock/wl-traders-installer.lock
if ! flock -n 9; then
    echo "Установщик WL Traders уже запущен в другом процессе."
    exit 1
fi

command -v python3 >/dev/null 2>&1 || {
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y python3
}

PORT="${WL_TRADERS_INSTALLER_PORT:-8787}"
if [[ ! "$PORT" =~ ^[0-9]+$ ]] || (( PORT < 1024 || PORT > 65535 )); then
    echo "Порт установщика должен быть числом от 1024 до 65535."
    exit 1
fi
if ss -H -ltn "sport = :$PORT" 2>/dev/null | grep -q .; then
    echo "Порт $PORT уже занят. Завершите старый установщик или задайте WL_TRADERS_INSTALLER_PORT."
    exit 1
fi
TOKEN="$(python3 -c 'import secrets; print(secrets.token_urlsafe(24))')"
SERVER_IP="$(curl -4fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
if [[ ! "$SERVER_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    SERVER_IP="$(curl -4fsS --max-time 5 https://checkip.amazonaws.com 2>/dev/null || true)"
fi
if [[ ! "$SERVER_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    SERVER_IP="$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -E '^[0-9]+\.' | head -n1 || true)"
fi
SERVER_IP="${SERVER_IP:-127.0.0.1}"
export WL_TRADERS_PUBLIC_IP="$SERVER_IP"

printf '\nWL Traders Installer запущен.\n'
printf 'Откройте в браузере:\n\n  http://%s:%s/?token=%s\n\n' "$SERVER_IP" "$PORT" "$TOKEN"
printf 'Ссылка одноразовая. После установки панель автоматически остановится.\n'
printf 'Если установка не начнётся, ссылка перестанет работать через 45 минут.\n'
printf 'Чтобы отменить установку до нажатия кнопки, нажмите Ctrl+C.\n\n'

exec python3 "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/installer/server.py" \
    --host 0.0.0.0 \
    --port "$PORT" \
    --token "$TOKEN" \
    --expires-in 2700
