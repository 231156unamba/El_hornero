#!/usr/bin/env bash
# Levanta el backend (Laravel) y el frontend (Vite) en una sola terminal.
# Ctrl+C detiene ambos.  Variables opcionales: BACKEND_PORT, FRONTEND_PORT.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$ROOT_DIR/scripts/common.sh"

[[ -f "$ROOT_DIR/backend/.env" ]] || die "Falta backend/.env. Ejecuta primero ./setup.sh"
[[ -d "$ROOT_DIR/backend/vendor" ]] || die "Falta backend/vendor. Ejecuta primero ./setup.sh"
[[ -d "$ROOT_DIR/frontend/node_modules" ]] || die "Falta frontend/node_modules. Ejecuta primero ./setup.sh"

ensure_mysql_running

port_in_use "$BACKEND_PORT" && die "El puerto $BACKEND_PORT ya está ocupado."
port_in_use "$FRONTEND_PORT" && die "El puerto $FRONTEND_PORT ya está ocupado."

pids=()
# php artisan serve y npm lanzan procesos hijos, así que hay que bajar el árbol completo.
kill_tree() {
    local pid="$1" child
    for child in $(pgrep -P "$pid" 2>/dev/null); do
        kill_tree "$child"
    done
    kill "$pid" 2>/dev/null || true
}
cleanup() {
    trap - INT TERM EXIT
    info "Deteniendo servicios..."
    for pid in "${pids[@]}"; do
        kill_tree "$pid"
    done
    wait 2>/dev/null || true
}
trap cleanup INT TERM EXIT

info "Backend  → http://localhost:$BACKEND_PORT"
(cd "$ROOT_DIR/backend" && php artisan serve --host=0.0.0.0 --port="$BACKEND_PORT") &
pids+=($!)

info "Frontend → http://localhost:$FRONTEND_PORT"
(cd "$ROOT_DIR/frontend" && npm run dev -- --port "$FRONTEND_PORT") &
pids+=($!)

wait -n
