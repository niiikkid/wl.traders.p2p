#!/usr/bin/env bash
# WL Traders: a portable launcher. Application services run only in Docker.
set -Eeuo pipefail
umask 077

fail() { printf '\n%s\n' "$*" >&2; exit 1; }
if [[ ${1:-} == --help || ${1:-} == -h ]]; then
    printf '%s\n' 'WL Traders — установка через Docker' \
        'Запуск: bash install.sh' \
        'macOS: запустите Docker Desktop; нужен Python 3.10+.' \
        'Ubuntu/Debian: от root, отсутствующие Docker и Python устанавливаются автоматически.' \
        'Другой Linux: заранее установите Docker Engine с Compose и Python 3.10+.' \
        'Панель: 127.0.0.1:8787. Для сервера используйте SSH-туннель.' \
        'WL_TRADERS_INSTALLER_PORT — другой порт панели (1024–65535).' \
        'WL_TRADERS_INSTALL_DIR — папка приложения; существующие данные сохраняются.'
    exit 0
fi
[[ $# -eq 0 ]] || fail 'Неизвестный аргумент. Справка: bash install.sh --help'
PORT="${WL_TRADERS_INSTALLER_PORT:-8787}"
[[ "$PORT" =~ ^[0-9]{4,5}$ ]] && (( 10#$PORT >= 1024 && 10#$PORT <= 65535 )) || fail 'Порт установщика должен быть числом от 1024 до 65535.'
PORT=$((10#$PORT))
HOST="${WL_TRADERS_INSTALLER_HOST:-127.0.0.1}"
[[ "$HOST" == 127.0.0.1 || "$HOST" == 0.0.0.0 ]] || fail 'Адрес панели: только 127.0.0.1 или 0.0.0.0.'
OS="$(uname -s)"
case "$OS" in Darwin|Linux) ;; *) fail 'Для Windows используйте install.ps1 в PowerShell.' ;; esac

APT_READY=0
apt_packages() {
    [[ "$OS" == Linux && ${EUID} -eq 0 ]] || fail 'Для установки системных пакетов на Linux нужен root. На Mac установите Python 3.10+ с python.org и Docker Desktop.'
    command -v apt-get >/dev/null 2>&1 || fail 'Установите Python 3.10+, curl и Docker Engine с Compose средствами вашей ОС и повторите запуск.'
    if [[ "$APT_READY" == 0 ]]; then
        DEBIAN_FRONTEND=noninteractive apt-get -o Acquire::Retries=3 -o DPkg::Lock::Timeout=180 update
        APT_READY=1
    fi
    DEBIAN_FRONTEND=noninteractive apt-get -o Acquire::Retries=3 -o DPkg::Lock::Timeout=180 install -y --no-install-recommends "$@"
}
command -v curl >/dev/null 2>&1 || apt_packages curl ca-certificates
command -v python3 >/dev/null 2>&1 || apt_packages python3
python3 -c 'import sys; sys.exit(0 if sys.version_info >= (3, 10) else 1)' || fail 'Нужен Python 3.10 или новее: https://www.python.org/downloads/'

if ! command -v docker >/dev/null 2>&1; then
    [[ "$OS" == Linux && ${EUID} -eq 0 ]] || fail 'Установите и запустите Docker Desktop: https://www.docker.com/products/docker-desktop/ — затем повторите эту команду.'
    [[ -f /etc/os-release ]] || fail 'Не удалось определить Linux. Установите Docker Engine и Compose вручную.'
    # shellcheck disable=SC1091
    . /etc/os-release
    case "${ID:-}" in ubuntu|debian) ;; *) fail 'Автоустановка Docker доступна для Ubuntu/Debian. Для этой ОС установите Docker Engine с Compose: https://docs.docker.com/engine/install/' ;; esac
    CODENAME="${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}"
    [[ "$CODENAME" =~ ^[a-z]+$ ]] || fail 'Не удалось определить выпуск Linux для репозитория Docker.'
    # Never replace an existing container runtime silently.
    if command -v containerd >/dev/null 2>&1 || command -v podman >/dev/null 2>&1; then
        fail 'Обнаружен другой контейнерный движок. Установите совместимый Docker с Compose самостоятельно; существующие службы не изменены.'
    fi
    printf '%s\n' 'Устанавливаю Docker из официального репозитория…'
    apt_packages ca-certificates curl
    install -d -m 0755 /etc/apt/keyrings
    curl --fail --show-error --location --retry 3 --connect-timeout 15 --max-time 120 "https://download.docker.com/linux/$ID/gpg" -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    printf 'Types: deb\nURIs: https://download.docker.com/linux/%s\nSuites: %s\nComponents: stable\nArchitectures: %s\nSigned-By: /etc/apt/keyrings/docker.asc\n' "$ID" "$CODENAME" "$(dpkg --print-architecture)" > /etc/apt/sources.list.d/wl-traders-docker.sources
    chmod 0644 /etc/apt/sources.list.d/wl-traders-docker.sources
    APT_READY=0
    apt_packages docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
fi
# Backend inspects the engine OS/version/resources in its preflight.
docker info >/dev/null 2>&1 || fail 'Docker установлен, но недоступен. Запустите Docker Desktop (Mac) или службу Docker (Linux). На Linux проверьте права пользователя. Затем повторите команду.'
docker compose version >/dev/null 2>&1 || fail 'Нужен Docker Compose v2.30+ (или v5). Обновите Docker Desktop / docker-compose-plugin.'

SCRIPT_DIR=''
if [[ -n ${BASH_SOURCE[0]:-} && -f ${BASH_SOURCE[0]} ]]; then
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
fi
TEMP_SOURCE=''
cleanup() { if [[ -n "$TEMP_SOURCE" && -d "$TEMP_SOURCE" ]]; then rm -rf -- "$TEMP_SOURCE"; fi; }
trap cleanup EXIT
if [[ -z "$SCRIPT_DIR" || ! -f "$SCRIPT_DIR/installer/server.py" || ! -f "$SCRIPT_DIR/installer/page.html" ]]; then
    TEMP_SOURCE="$(mktemp -d "${TMPDIR:-/tmp}/wl-traders-source.XXXXXX")"
    SOURCE_ARCHIVE_URL="${WL_TRADERS_SOURCE_ARCHIVE_URL:-https://codeload.github.com/niiikkid/wl.traders.p2p/tar.gz/refs/heads/main}"
    printf '%s\n' 'Скачиваю WL Traders…'
    curl --fail --show-error --location --retry 3 --retry-delay 2 --connect-timeout 20 --max-time 600 "$SOURCE_ARCHIVE_URL" -o "$TEMP_SOURCE/source.tar.gz"
    mkdir "$TEMP_SOURCE/project"
    tar -xzf "$TEMP_SOURCE/source.tar.gz" --strip-components=1 -C "$TEMP_SOURCE/project"
    SCRIPT_DIR="$TEMP_SOURCE/project"
    [[ -f "$SCRIPT_DIR/installer/server.py" && -f "$SCRIPT_DIR/installer/page.html" && -f "$SCRIPT_DIR/compose.yaml" ]] || fail 'Архив проекта неполный. Повторите загрузку.'
fi
if [[ "$HOST" == 0.0.0.0 ]]; then
    printf '%s\n' 'ВНИМАНИЕ: панель доступна по сети без HTTPS. Рекомендуется SSH-туннель и адрес 127.0.0.1.'
fi
# Do not exec: the EXIT trap must remove downloaded source after the panel closes.
python3 "$SCRIPT_DIR/installer/server.py" --host "$HOST" --port "$PORT" --expires-in 2700
