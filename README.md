# Iron Screw — Sistema de Etiquetas

Aplicación interna desarrollada con Laravel y MySQL para sincronizar órdenes y productos desde Contabilium, administrar presentaciones por caja e imprimir etiquetas térmicas de 80 × 50 mm.

## Requisitos

- PHP 8.3 o superior
- Composer
- Node.js y npm
- MySQL

## Instalación local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configurá en `.env` la conexión MySQL, las credenciales de Contabilium y contraseñas iniciales seguras:

```dotenv
CONTABILIUM_EMAIL=
CONTABILIUM_API_KEY=
IRON_ADMIN_PASSWORD=
IRON_DEPOSIT_PASSWORD=
```

Luego ejecutá:

```bash
php artisan migrate --seed
npm run build
composer run dev
```

La aplicación quedará disponible en `http://localhost:8001`.

## Sincronización

- Las órdenes nuevas de Contabilium ingresan siempre como `Pendiente`.
- El estado solo cambia a `Finalizado` dentro de Iron Screw.
- Las cantidades locales de Fraccionado y Granel se preservan al sincronizar productos.
- El botón de sincronización inicia un worker temporal automáticamente.
- Para desarrollo continuo, `composer run dev` inicia servidor, scheduler, worker, logs y Vite.

## Seguridad

El repositorio no incluye `.env`, claves API, bases locales, dependencias, logs, cachés ni archivos compilados. Nunca subas credenciales reales al control de versiones.

## Pruebas

```bash
php artisan test
```
