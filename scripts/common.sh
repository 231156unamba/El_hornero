# Funciones compartidas por setup.sh y start.sh. No ejecutar directamente.

DB_NAME="${DB_NAME:-el_hornero}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
BACKEND_PORT="${BACKEND_PORT:-8000}"
FRONTEND_PORT="${FRONTEND_PORT:-5174}"

info()  { printf '\033[1;34m[el-hornero]\033[0m %s\n' "$*"; }
warn()  { printf '\033[1;33m[el-hornero]\033[0m %s\n' "$*" >&2; }
die()   { printf '\033[1;31m[el-hornero]\033[0m %s\n' "$*" >&2; exit 1; }

has_command() { command -v "$1" >/dev/null 2>&1; }

detect_package_manager() {
    if has_command pacman; then echo pacman
    elif has_command apt-get; then echo apt
    elif has_command dnf; then echo dnf
    else echo unknown
    fi
}

require_php_version() {
    local version
    version="$(php -r 'echo PHP_VERSION;')"
    php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
        || die "Laravel 12 necesita PHP >= 8.2 y tienes $version"
    info "PHP $version"
}

# En Arch/CachyOS las extensiones vienen compiladas pero desactivadas en php.ini.
# Se activan en un archivo propio dentro del scan-dir para no tocar php.ini.
enable_php_extensions() {
    local required=(bcmath curl gd iconv intl mbstring mysqli openssl pdo_mysql pdo_sqlite zip fileinfo)
    local loaded missing=()
    loaded="$(php -m | tr 'A-Z' 'a-z')"

    local ext
    for ext in "${required[@]}"; do
        grep -qx "$ext" <<<"$loaded" || missing+=("$ext")
    done

    [[ ${#missing[@]} -eq 0 ]] && { info "Extensiones PHP requeridas: OK"; return; }

    local scan_dir
    scan_dir="$(php -i | awk -F'=> ' '/Scan this dir for additional .ini files/ {print $2}' | tr -d ' ')"
    if [[ -z "$scan_dir" || ! -d "$scan_dir" ]]; then
        die "Faltan extensiones PHP (${missing[*]}) y no se encontró el directorio conf.d de PHP. Actívalas a mano en $(php -i | awk -F'=> ' '/Loaded Configuration File/ {print $2}')"
    fi

    info "Activando extensiones PHP faltantes: ${missing[*]}"
    printf 'extension=%s\n' "${missing[@]}" | sudo tee "$scan_dir/99-el-hornero.ini" >/dev/null

    loaded="$(php -m | tr 'A-Z' 'a-z')"
    local still_missing=()
    for ext in "${missing[@]}"; do
        grep -qx "$ext" <<<"$loaded" || still_missing+=("$ext")
    done
    [[ ${#still_missing[@]} -eq 0 ]] \
        || die "No se pudieron cargar: ${still_missing[*]}. Instala los paquetes correspondientes (por ejemplo php-gd, php-intl, php-sqlite en Arch)."
}

mysql_root() {
    if [[ -n "$DB_PASSWORD" ]]; then
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$@"
    else
        mysql -u "$DB_USER" "$@"
    fi
}

mysql_service_name() {
    if systemctl list-unit-files 2>/dev/null | grep -q '^mysqld\.service'; then echo mysqld
    elif systemctl list-unit-files 2>/dev/null | grep -q '^mariadb\.service'; then echo mariadb
    elif systemctl list-unit-files 2>/dev/null | grep -q '^mysql\.service'; then echo mysql
    else echo ""
    fi
}

ensure_mysql_running() {
    has_command mysql || die "No se encontró el cliente 'mysql'. Instala mysql o mariadb."

    if mysql_root -e 'SELECT 1' >/dev/null 2>&1; then
        info "MySQL accesible como '$DB_USER'"
        return
    fi

    local service
    service="$(mysql_service_name)"
    if [[ -n "$service" ]] && has_command systemctl; then
        # MariaDB recién instalada no tiene el directorio de datos inicializado.
        if [[ "$service" == "mariadb" && ! -d /var/lib/mysql/mysql ]]; then
            info "Inicializando el directorio de datos de MariaDB"
            sudo mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
        fi
        info "Arrancando el servicio $service"
        sudo systemctl enable --now "$service"
        sleep 3
    elif has_command service; then
        sudo service mysql start || true
        sleep 3
    fi

    mysql_root -e 'SELECT 1' >/dev/null 2>&1 \
        || die "No se puede conectar a MySQL como '$DB_USER'. Revisa que el servidor esté activo y que el usuario no tenga contraseña (o exporta DB_PASSWORD=...)."
    info "MySQL accesible como '$DB_USER'"
}

database_has_tables() {
    local count
    count="$(mysql_root -N -B -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null || echo 0)"
    [[ "$count" -gt 0 ]]
}

# set_env_var <archivo> <clave> <valor>
set_env_var() {
    local file="$1" key="$2" value="$3"
    if grep -qE "^${key}=" "$file"; then
        local tmp
        tmp="$(mktemp)"
        awk -v k="$key" -v v="$value" -F= '
            $1 == k { print k "=" v; next }
            { print }
        ' "$file" >"$tmp"
        mv "$tmp" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >>"$file"
    fi
}

port_in_use() {
    if has_command ss; then ss -ltn "sport = :$1" 2>/dev/null | grep -q LISTEN
    else (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
    fi
}
