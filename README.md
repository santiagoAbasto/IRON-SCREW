# Iron Screw — Sistema de Etiquetas

Aplicación interna en Laravel 13 y MySQL que sincroniza productos y órdenes de venta desde Contabilium, administra unidades por caja y genera etiquetas térmicas de **100 × 80 mm**.

## Funciones principales

- Sincronización automática de órdenes cada minuto y productos cada hora.
- Sincronización manual desde la pantalla de órdenes.
- Las órdenes nuevas ingresan como `Pendiente`; sólo Iron Screw puede marcarlas `Finalizado`.
- Conservación de las cantidades locales de fraccionado y granel durante cada sincronización.
- Importación y exportación de cantidades mediante Excel.
- Impresión de etiquetas por producto u orden.
- Impresión conjunta de todas las etiquetas de una orden, priorizando la presentación a granel.
- Exclusión de órdenes canceladas del listado y del acceso operativo.
- Administración de usuarios, roles y permisos.

## Requisitos

- PHP 8.3 o superior con las extensiones requeridas por Laravel y `ZipArchive`.
- Composer 2.
- Node.js 20 o superior y npm.
- MySQL 8 o MariaDB equivalente.
- Nginx o Apache con HTTPS.
- Un supervisor de procesos para la cola.
- Cron disponible para el scheduler.

## Instalación local

```bash
git clone https://github.com/santiagoAbasto/IRON-SCREW.git
cd IRON-SCREW
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Para desarrollo, cambiá temporalmente en `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8001
SESSION_SECURE_COOKIE=false
```

Configurá MySQL, Contabilium y dos contraseñas iniciales de al menos 16 caracteres:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iron_screw
DB_USERNAME=iron_screw
DB_PASSWORD=

CONTABILIUM_EMAIL=
CONTABILIUM_API_KEY=
IRON_ADMIN_PASSWORD=
IRON_DEPOSIT_PASSWORD=
```

Luego:

```bash
php artisan migrate --seed
npm run build
composer run dev
```

La aplicación local se sirve en `http://localhost:8001`.

## Despliegue en producción

El servidor web debe apuntar únicamente al directorio `public/`; nunca a la raíz del repositorio.

```bash
composer install --no-dev --classmap-authoritative
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

El repositorio incluye `public/build` compilado para los hostings de producción que no ofrecen Node.js. En esos servidores se omiten `npm ci` y `npm run build`; esos comandos sólo son necesarios cuando se desea regenerar los recursos.

Configuración mínima obligatoria:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://etiquetas.tudominio.com
LOG_CHANNEL=daily
LOG_LEVEL=warning

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

QUEUE_CONNECTION=database
CACHE_STORE=database
```

Aplicá permisos de escritura sólo a `storage/` y `bootstrap/cache/` para el usuario del servidor web. El resto del código debe permanecer de sólo lectura.

### Cola

En producción debe existir un worker permanente administrado por Supervisor, systemd o el panel del hosting:

```bash
php artisan queue:work --sleep=1 --tries=5 --timeout=240 --max-time=3600
```

Ejemplo conceptual de Supervisor:

```ini
[program:iron-screw-worker]
command=php /var/www/iron-screw/artisan queue:work --sleep=1 --tries=5 --timeout=240 --max-time=3600
directory=/var/www/iron-screw
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/iron-screw/storage/logs/worker.log
```

Después de cada despliegue:

```bash
php artisan queue:restart
```

### Scheduler

Agregá una sola entrada al cron:

```cron
* * * * * cd /var/www/iron-screw && php artisan schedule:run >> /dev/null 2>&1
```

## Seguridad

- El login limita intentos repetidos y regenera la sesión al autenticar.
- Todas las acciones mutables usan `POST`, `PUT`, `PATCH` o `DELETE` con protección CSRF.
- El cierre de sesión invalida la sesión y cancela procesos asociados.
- Contraseñas de usuarios: mínimo 6 caracteres.
- Las rutas se protegen por sesión activa y permisos de rol.
- Las respuestas incluyen encabezados contra framing, MIME sniffing y exposición innecesaria.
- `.env`, claves, bases locales, logs, cachés, dependencias y archivos compilados no se versionan.

Antes de publicar:

1. Usá HTTPS y redirigí todo HTTP a HTTPS.
2. Generá una `APP_KEY` exclusiva para producción.
3. Usá credenciales MySQL sin permisos administrativos y limitadas a esta base.
4. Guardá las credenciales de Contabilium sólo en el gestor de secretos o `.env` del servidor.
5. Restringí el acceso por VPN, firewall o allowlist si la aplicación es exclusivamente interna.
6. Configurá backups automáticos de MySQL y probá su restauración.
7. Rotá contraseñas iniciales y la API key si fueron compartidas fuera del equipo autorizado.
8. Conservá `APP_DEBUG=false`; una página de excepción puede revelar información sensible.

## Verificación

```bash
php artisan test
npm run build
composer audit
npm audit --omit=dev
```

### Configuración de impresión SATO

Para etiquetas de 80 × 50 mm, el controlador de la impresora debe usar exactamente:

- Papel personalizado: `80,0 mm × 50,0 mm`.
- Orientación: horizontal.
- Escala: `100 %` (sin ajustar al área imprimible).
- Márgenes: ninguno.
- Sensor de material: GAP/separación.
- Encabezados y pies del navegador: desactivados.

Al colocar un rollo nuevo, ejecutá la calibración de material desde el controlador o la impresora. Si cada impresión queda más desplazada que la anterior o aparece una zona blanca grande entre diseños, el alto configurado o el paso detectado por el sensor no coincide con 50 mm; ese corrimiento acumulativo no se corrige desplazando el diseño.

Comprobación de producción:

```bash
php artisan about --only=environment
php artisan config:show app
php artisan route:list
php artisan schedule:list
php artisan queue:monitor default:100
```

La prueba automatizada cubre login, cierre de sesión, permisos, gestión, sincronización, finalización de órdenes, importación Excel y reglas de seguridad. Antes de cada publicación también se recomienda probar visualmente el flujo completo contra un entorno staging con credenciales separadas de Contabilium.

## Operación y recuperación

- Revisá `storage/logs/laravel.log` y el log del worker ante errores.
- Los jobs fallidos se consultan con `php artisan queue:failed`.
- Reintentá un job con `php artisan queue:retry ID`.
- Mantené una versión anterior desplegable y un backup compatible antes de migrar.
- Para modo mantenimiento: `php artisan down --secret="token-temporal"` y luego `php artisan up`.

## Licencia

Software propietario de uso interno. No redistribuir sin autorización.
