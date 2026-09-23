#!/usr/bin/env bash
# Instalación y puesta a punto de El Hornero en Linux (CachyOS/Arch, Debian/Ubuntu, Fedora).
#
#   ./setup.sh              instala dependencias, crea la BD si no existe y prepara backend/frontend
#   ./setup.sh --reset-db   borra la base de datos el_hornero y la vuelve a crear desde bd.txt
#   ./setup.sh --skip-deps  no instala paquetes del sistema (útil si ya tienes todo)
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$ROOT_DIR/scripts/common.sh"

RESET_DB=0
SKIP_DEPS=0
for arg in "$@"; do
    case "$arg" in
        --reset-db) RESET_DB=1 ;;
        --skip-deps) SKIP_DEPS=1 ;;
        -h|--help) sed -n '2,6p' "$0"; exit 0 ;;
        *) die "Opción desconocida: $arg" ;;
    esac
done

# ── 1. Paquetes del sistema ───────────────────────────────────────────────
install_system_packages() {
    local pm
    pm="$(detect_package_manager)"
    info "Gestor de paquetes detectado: $pm"

    case "$pm" in
        pacman)
            local pkgs=(php php-gd php-intl php-sqlite composer nodejs npm unzip)
            has_command mysql || has_command mariadb || pkgs+=(mariadb)
            sudo pacman -S --needed --noconfirm "${pkgs[@]}"
            ;;
        apt)
            sudo apt-get update
            sudo apt-get install -y php-cli php-mysql php-sqlite3 php-mbstring php-xml \
                php-curl php-zip php-gd php-bcmath php-intl composer nodejs npm unzip mysql-server
            ;;
        dnf)
            sudo dnf install -y php-cli php-mysqlnd php-pdo php-mbstring php-xml php-json \
                php-gd php-bcmath php-intl composer nodejs npm unzip mariadb-server
            ;;
        *)
            warn "Distribución no reconocida: instala manualmente php>=8.2, composer, node>=20, npm y mysql/mariadb."
            ;;
    esac
}

if [[ $SKIP_DEPS -eq 0 ]]; then
    install_system_packages
else
    info "Se omite la instalación de paquetes del sistema (--skip-deps)."
fi

for cmd in php composer node npm; do
    has_command "$cmd" || die "Falta '$cmd' en el PATH. Instálalo y vuelve a ejecutar ./setup.sh"
done

require_php_version
enable_php_extensions
ensure_mysql_running

# ── 2. Base de datos ──────────────────────────────────────────────────────
if [[ $RESET_DB -eq 1 ]]; then
    info "Eliminando la base de datos '$DB_NAME' (--reset-db)"
    mysql_root -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;"
fi

if database_has_tables; then
    info "La base de datos '$DB_NAME' ya tiene tablas; no se reimporta bd.txt (usa --reset-db para recrearla)."
else
    info "Importando esquema y datos iniciales desde bd.txt"
    mysql_root < "$ROOT_DIR/bd.txt"
fi

# ── 3. Backend (Laravel) ──────────────────────────────────────────────────
info "Instalando dependencias PHP"
cd "$ROOT_DIR/backend"
composer install --no-interaction

if [[ ! -f .env ]]; then
    info "Creando backend/.env a partir de .env.example"
    cp .env.example .env
fi
set_env_var .env DB_DATABASE "$DB_NAME"
set_env_var .env DB_USERNAME "$DB_USER"
set_env_var .env DB_PASSWORD "$DB_PASSWORD"

grep -qE '^APP_KEY=base64:' .env || php artisan key:generate

info "Ejecutando migraciones de Laravel"
php artisan migrate --force

[[ -L public/storage ]] || php artisan storage:link
chmod -R ug+rw storage bootstrap/cache

# ── 4. Frontend (Vue + Vite) ──────────────────────────────────────────────
info "Instalando dependencias de Node"
cd "$ROOT_DIR/frontend"
[[ -f .env ]] || cp .env.example .env
npm install

info "Listo. Arranca la aplicación con:  ./start.sh"
