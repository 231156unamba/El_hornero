# El Hornero

Sistema de pedidos, caja y administración para la pollería *El Hornero*.

- **backend/** — API REST en Laravel 12 (PHP >= 8.2) sobre MySQL/MariaDB.
- **frontend/** — SPA en Vue 3 + Vite (Node >= 20).
- **bd.txt** — esquema y datos iniciales de la base de datos `el_hornero`.
- **prueba_unitaria/** — pruebas unitarias sueltas con PHPUnit.

## Instalación en Linux (CachyOS/Arch, Debian/Ubuntu, Fedora)

```bash
git clone https://github.com/231156unamba/El_hornero.git
cd El_hornero
./setup.sh
```

`setup.sh` instala los paquetes del sistema que falten (PHP + extensiones, Composer, Node, MySQL/MariaDB),
activa las extensiones de PHP desactivadas por defecto en Arch, arranca el servidor de base de datos,
importa `bd.txt` si la base `el_hornero` aún no existe, y prepara backend (`composer install`,
`.env`, `APP_KEY`, migraciones, `storage:link`) y frontend (`npm install`).

Opciones:

| Comando | Efecto |
| --- | --- |
| `./setup.sh --reset-db` | borra la base `el_hornero` y la recrea desde `bd.txt` |
| `./setup.sh --skip-deps` | no instala paquetes del sistema |

La conexión por defecto es `root` sin contraseña en `127.0.0.1:3306`. Si tu MySQL usa otras credenciales:

```bash
DB_USER=root DB_PASSWORD=tu_clave ./setup.sh
```

## Ejecución

```bash
./start.sh
```

- Backend: http://localhost:8000
- Frontend: http://localhost:5174

`Ctrl+C` detiene ambos procesos. Los puertos se pueden cambiar con `BACKEND_PORT` y `FRONTEND_PORT`.
En Windows sigue disponible `start.ps1`.

La URL de la API que usa el frontend se configura en `frontend/.env` (`VITE_API_URL`).

## Usuarios de prueba

Los cuatro usuarios que crea `bd.txt` (`admin`, `cocina1`, `pedido1`, `caja1`) tienen la contraseña
`password`.

## Pruebas

```bash
cd backend && php artisan test
cd prueba_unitaria && ../backend/vendor/bin/phpunit
```

## Build de producción del frontend

```bash
cd frontend && npm run build   # genera frontend/dist
```
