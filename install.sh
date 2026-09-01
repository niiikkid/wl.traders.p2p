#!/usr/bin/env bash
set -Eeuo pipefail

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
if [[ ${ID:-} != "ubuntu" ]]; then
    echo "Этот установщик рассчитан на Ubuntu. Обнаружено: ${PRETTY_NAME:-unknown}"
    exit 1
fi

command -v python3 >/dev/null 2>&1 || {
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y python3
}

PORT="${WLPAY_INSTALLER_PORT:-8787}"
TOKEN="$(python3 -c 'import secrets; print(secrets.token_urlsafe(24))')"
SERVER_IP="$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -E '^[0-9]+\.' | head -n1 || true)"
SERVER_IP="${SERVER_IP:-127.0.0.1}"

printf '\nWLPay Installer запущен.\n'
printf 'Откройте в браузере:\n\n  http://%s:%s/?token=%s\n\n' "$SERVER_IP" "$PORT" "$TOKEN"
printf 'Ссылка одноразовая. После установки панель автоматически остановится.\n'
printf 'Чтобы отменить установку до нажатия кнопки, нажмите Ctrl+C.\n\n'

exec python3 "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/installer/server.py" \
    --host 0.0.0.0 \
    --port "$PORT" \
    --token "$TOKEN"
