# Evaluación inicial del repositorio

> Fecha: 2026-10-22 · Rama evaluada: `main` (commit `77a45e4`)

Este documento recoge el estado del proyecto **antes** de la reconstrucción, con la
evidencia obtenida al instalarlo y ejecutarlo, y justifica la decisión de reconstruir
sobre Laravel 13 en lugar de actualizar la base existente.

## 1. Resumen ejecutivo

El repositorio contenía el esqueleto de un foro: una aplicación Laravel 12 con el
*starter kit* Jetstream (Inertia + Vue) y un dominio mínimo de `Post` y `Comment`, del que
solo estaban implementados el listado y el detalle de posts. Desde agosto de 2026 la
actividad ha sido exclusivamente de mantenimiento (seguridad, dependencias y CI), con un
nivel de rigor notable, pero sin avance funcional.

**Veredicto:** aproximadamente un 5 % de un foro funcional. La infraestructura de calidad
(tests, CI, auditorías) estaba por delante del producto. La base técnica (Jetstream) es un
*starter kit* legado desde Laravel 12, por lo que actualizar sobre ella no aporta valor:
se reconstruye sobre Laravel 13 conservando las piezas valiosas.

## 2. Evidencia obtenida

| Comprobación | Resultado |
|---|---|
| `composer install` (PHP 8.3) | Correcto. 112 paquetes. |
| `vendor/bin/pest --fail-on-warning` | **20 tests correctos, 3 omitidos** (los de tokens de API: la feature de Jetstream estaba desactivada), 79 aserciones, 1,3 s. |
| `composer audit --locked` | Sin avisos de seguridad. |
| `npm ci && npm run build` | Correcto (bundle principal de 298 KB). |
| `npm audit` (script con lista de excepciones) | 6 entradas, todas toleradas: la cadena `braces → micromatch/chokidar → tailwindcss 3` (GHSA-vfj7-8cjw-p6xm, sin parche). Desaparece al migrar a Tailwind 4. |

## 3. Análisis por áreas

### 3.1 Stack

| Pieza | Versión | Observación |
|---|---|---|
| Laravel | 12.69 | El README seguía diciendo «Laravel 11». La versión estable actual es 13.38. |
| PHP | `^8.2` (lock resuelto para 8.3) | Laravel 13 exige PHP ≥ 8.3; Pest 5 exige PHP ≥ 8.4. |
| Frontend | Jetstream 5 · Inertia 2 · Vue 3 (JavaScript) · Tailwind 3 · Ziggy | Jetstream fue sustituido por los nuevos *starter kits* (Inertia 3, TypeScript, Tailwind 4, Wayfinder). |
| Auth | Fortify + Sanctum 4 (vía Jetstream) | Correcto, pero la API de tokens estaba desactivada. |
| Tests | Pest 3 · PHPUnit 11 | |

### 3.2 Dominio y funcionalidad

- Modelos: `User`, `Post`, `Comment`. Sin categorías, etiquetas, slugs, roles ni estados.
- `PostController`: `index` y `show` implementados; `create`, `store`, `edit`, `update` y
  `destroy` vacíos (*stubs*).
- Sin políticas de autorización, sin *FormRequests*, sin validación.
- Los comentarios no se mostraban en la interfaz.
- El cuerpo del post se pintaba en un `<pre>` (sin Markdown ni saneado).
- Sin búsqueda, notificaciones, moderación, suscripciones, reacciones ni mensajes privados.

### 3.3 API

Solo la ruta por defecto `GET /api/user`. No había API del foro, ni versionado, ni
documentación.

### 3.4 Tests

- 2 tests de `PostController` (listado y detalle), 1 test unitario de `UserResource` y los
  tests generados por Jetstream (perfil, contraseña, 2FA, sesiones, borrado de cuenta).
- **Buenas prácticas a conservar:**
  - `tests/bootstrap.php` genera una `APP_KEY` efímera por ejecución, de modo que nunca se
    escribe una clave en disco.
  - `.env.testing` versionado con valores que son *fixtures*, no credenciales.
  - El test de `UserResource`, que fija que el email solo se expone a su propietario.

### 3.5 CI

- `ci.yml`: tests (SQLite) y build de Vite. Acciones fijadas por SHA,
  `persist-credentials: false`, concurrencia y comentarios que documentan las decisiones.
- `audit.yml`: `composer audit` y `npm audit` con una lista de excepciones auditada (cada
  excepción lleva motivo y fecha). Se ejecuta en cada push y una vez por semana.
- **Faltaba:** lint (Pint), análisis estático, cobertura, tests de frontend y build de
  imágenes Docker.

### 3.6 Docker

- `docker-compose.yml` basado en Sail: exige `composer install` en el host **antes** del
  primer `up`, porque el Dockerfile vive en `vendor/laravel/sail`.
- MySQL 8.0.32 (antiguo) y Mailpit. Puertos publicados solo en *loopback* (correcto).
- **Faltaba:** separación Nginx/PHP-FPM, Redis, workers de colas, scheduler, motor de
  búsqueda y un entorno de producción.

### 3.7 Seguridad

- Correcto: `.env*` fuera del control de versiones, puertos en *loopback*,
  `preventLazyLoading` fuera de producción y un `DeleteUser` transaccional.
- **Pendiente para el propietario:** credenciales filtradas en el historial de git
  (`APP_KEY` y contraseña de base de datos de 2024), ya documentadas en el README anterior.
  Deben rotarse y, si el repositorio es o fue público, purgarse del historial
  (`git filter-repo`). Esta reconstrucción no reescribe el historial.

## 4. Decisión: reconstruir sobre Laravel 13

Actualizar sobre Jetstream supondría migrar un *starter kit* obsoleto para conservar
apenas dos acciones de controlador. Se decide reconstruir sobre la base oficial actual
(Laravel 13 y *starter kit* de Vue con Inertia 3) y portar lo que tiene valor:

| Se conserva | Cómo |
|---|---|
| APP_KEY efímera en tests | `tests/bootstrap.php` se mantiene. |
| `.env.testing` con *fixtures* | Se mantiene, ampliado. |
| Auditoría composer/npm con lista de excepciones | `audit.yml` y el script se mantienen; la excepción de `braces` se retira al pasar a Tailwind 4. |
| Fijado de acciones por SHA | Se aplica a todos los workflows nuevos. |
| Privacidad del email | Regla mantenida en los recursos de la API y en las *props* de Inertia. |
| Borrado de cuenta transaccional | Evoluciona a anonimización: se conservan los mensajes del foro y se eliminan los datos personales. |
| Puertos en *loopback* en desarrollo | Se mantiene en `compose.yaml`. |

Las decisiones de stack confirmadas por el propietario del proyecto están en
[`02-arquitectura.md`](02-arquitectura.md).
