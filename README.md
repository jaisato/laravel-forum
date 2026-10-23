# Laravel Forum

Foro de comunidad bilingüe (español por defecto e inglés) construido con
**Laravel 13**, **Inertia 3** y **Vue 3** con TypeScript, con renderizado en
servidor (SSR), búsqueda en **Meilisearch** y colas en **Redis**. Se desarrolla
y se despliega con **Docker**: un único `Dockerfile` *multi-stage* y Docker
Compose para el entorno local y para producción.

El diseño completo (alcance, arquitectura, modelo de datos y seguridad) está en
[docs/02-arquitectura.md](docs/02-arquitectura.md). La reconstrucción avanza por
tareas según el [plan](docs/plans/2026-10-22-reconstruccion-foro.md); esta
página describe lo que ya funciona.

## Funcionalidades

**Cuentas**

- Registro, inicio de sesión con email o nombre de usuario, verificación del
  email y recuperación de la contraseña.
- Segundo factor (TOTP con códigos de recuperación) y *passkeys*.
- Ajustes de perfil, seguridad (contraseña, 2FA y *passkeys*) y apariencia
  (claro, oscuro o el del sistema); borrado de la cuenta con anonimización.

**Foro**

- Portada con categorías, últimos hilos y estadísticas; páginas de categoría y
  de etiqueta.
- Listado de hilos con filtros (sin respuesta, resueltos, sin resolver, los que
  sigues…).
- Creación de hilos con categoría y hasta cinco etiquetas, hilos parecidos
  sugeridos mientras se escribe el título y borrador guardado en el navegador;
  edición del título y las etiquetas.
- Página de hilo: respuestas en Markdown con vista previa (renderizado seguro
  en el servidor), citas, reacciones, menciones `@usuario`, solución marcada,
  seguimiento del hilo y salto al primer mensaje sin leer.
- Perfiles públicos (`/u/usuario`) con biografía, reputación y pestañas de
  hilos y respuestas.
- Búsqueda de hilos y mensajes con filtros por categoría y etiqueta; solo
  encuentra contenido público.
- Mensajes privados: conversaciones con uno o varios miembros (desde el perfil,
  «Enviar un mensaje»), bandeja con lo no leído y abandonar una conversación.
  Solo sus participantes las ven; tampoco el *staff*.
- Reportes de mensajes y moderación en el propio hilo: fijar, cerrar, mover,
  ocultar, aprobar y restaurar.
- Notificaciones en la web (campana con contador que se actualiza sola y página
  para abrirlas o marcarlas como leídas) y por email (en cola y según las
  preferencias de cada miembro): respuestas en hilos seguidos, menciones,
  soluciones aceptadas y moderación. Una notificación sobre un mensaje que se
  oculta o se borra después deja de mostrar su contenido.
- Límites de frecuencia por miembro al publicar, reaccionar y reportar.
- Interfaz en español e inglés con selector de idioma.

## Stack

| Capa | Tecnologías |
|---|---|
| Backend | PHP 8.4, Laravel 13, Fortify (autenticación), Sanctum, Scout, Horizon, Wayfinder |
| Frontend | Inertia 3, Vue 3.5 + TypeScript, Vite 8, Tailwind CSS 4, shadcn-vue (Reka UI), laravel-vue-i18n |
| Datos | PostgreSQL 18 con pgvector, Redis 8 (caché, sesiones y colas), Meilisearch |
| Calidad | Pest, Larastan (nivel 7), Pint, Vitest, ESLint, Prettier, vue-tsc |
| Infraestructura | Docker, Docker Compose, Nginx, Caddy (TLS en producción) |

## Arranque rápido

Solo hacen falta **Docker Engine 25 o posterior con Docker Compose v2**, **GNU
Make** y **Git**: PHP, Composer, Node y los servicios de datos viven en
contenedores.

```bash
git clone https://github.com/jaisato/laravel-forum.git
cd laravel-forum
make setup
```

`make setup` crea el `.env`, construye la imagen de desarrollo, instala las
dependencias, crea la base de datos con datos de demostración y levanta todos
los servicios. Después:

- El foro: <http://localhost:8080>. Cuenta de administración:
  `admin@example.com` / `password`; moderación: `moderador@example.com`; y diez
  miembros de demostración (`ana@example.com`, `luis@example.com`…), todos con
  la contraseña `password`.
- Los correos que envía el foro: Mailpit en <http://localhost:8025>.

La guía completa (servicios, Vite y SSR, depuración, problemas frecuentes) está
en [docs/03-desarrollo-local.md](docs/03-desarrollo-local.md).

## Comandos

| Comando | Qué hace |
|---|---|
| `make up` / `make down` | Arranca o para el entorno (los datos se quedan en los volúmenes) |
| `make ps` / `make logs` | Estado de los contenedores / sus logs (`make logs s=queue`) |
| `make shell` / `make tinker` | Una shell o el REPL de Laravel en el contenedor `app` |
| `make migrate` / `make fresh` | Migraciones pendientes / base de datos nueva con datos de demostración |
| `make test` | Suite PHP (Pest, en paralelo, SQLite en memoria) |
| `make test-pgsql` | Suite PHP sobre PostgreSQL 18 (base de datos `forum_testing`) |
| `make test-js` | Suite de Vitest |
| `make coverage` | Ambas suites con cobertura (PHP: mínimo del 95 %) |
| `make lint` | Estilo, análisis estático y tipos: Pint, Larastan, ESLint, Prettier, vue-tsc y hadolint |
| `make fix` | Corrige el estilo (Pint, ESLint y Prettier) |

`make help` los lista todos.

## Tests sin Docker

Con PHP 8.4 (extensiones `pdo_sqlite`, `intl`, `gd`, `zip`…) y Composer:

```bash
composer install
composer test
```

No hay que crear un `.env`, generar una clave ni levantar una base de datos: la
suite lee el `.env.testing` versionado (lo selecciona `APP_ENV=testing` en
`phpunit.xml`) y usa SQLite en memoria. `APP_KEY` es el único valor que ese
fichero no lleva: `tests/bootstrap.php` genera una clave desechable en el
entorno del proceso antes de arrancar el framework, de modo que ninguna clave
se escribe en disco. Si defines `APP_KEY`, se respeta.

`composer test` ejecuta Pest con `--fail-on-warning`: un aviso de PHP o de
PHPUnit hace fallar la suite.

## Producción

`compose.prod.yaml` despliega el foro con Caddy (HTTPS automático) delante de
Nginx, PHP-FPM, el servidor SSR, Horizon y el planificador, con PostgreSQL,
Redis y Meilisearch en una red interna sin salida. Cada despliegue ejecuta
antes las migraciones.

```bash
cp .env.production.example .env.production   # y completa sus valores
docker compose -f compose.prod.yaml --env-file .env.production up -d --wait
```

Todos los pasos (requisitos, actualizaciones, copias de seguridad y ajustes) en
[docs/04-despliegue.md](docs/04-despliegue.md).

## Documentación

- [01 · Evaluación inicial](docs/01-evaluacion-inicial.md): el punto de partida
  y por qué se reconstruyó.
- [02 · Arquitectura](docs/02-arquitectura.md): la especificación de diseño.
- [03 · Desarrollo local](docs/03-desarrollo-local.md): el entorno con Docker
  Compose.
- [04 · Despliegue](docs/04-despliegue.md): producción con `compose.prod.yaml`.

## Aviso de seguridad: credenciales en el historial

`.env.local`, `.env.testing` y `.env.example` estuvieron versionados con una
`APP_KEY` y una contraseña de base de datos reales. Ya no lo están y
`.gitignore` excluye todos los `.env.*` salvo las plantillas, pero **esos
valores siguen en el historial de git** y deben considerarse comprometidos:

1. Genera una clave nueva en cada entorno que usara la antigua (`php artisan
   key:generate`). Invalida las sesiones, las URL firmadas y todo lo cifrado con
   la clave anterior.
2. Cambia las contraseñas de los usuarios de base de datos `forum_user` y `root`
   allí donde se usaran.
3. Si el repositorio es o fue público, purga esos valores del historial
   (`git filter-repo` o BFG) y fuerza la subida.

## Licencia

MIT, según `composer.json`.
