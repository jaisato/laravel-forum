# Tests y CI

Cómo se comprueba que el foro funciona: la suite de PHP (Pest) y la de
TypeScript (Vitest), las reglas de arquitectura, el análisis estático, los
*workflows* de GitHub Actions que lo exigen todo en cada *pull request* y el
camino de una versión hasta GHCR.

## 1. Mapa de la suite

| Carpeta | Qué prueba | Base de datos |
|---|---|---|
| `tests/Unit` | Reglas sin estado: el analizador de menciones, los enumerados y sus traducciones, la configuración de colas y de IA, y que la interfaz esté traducida al español | Ninguna |
| `tests/Feature` | Todo lo demás: páginas Inertia (`Web`), la API (`Api/V1`), acciones del dominio, *policies*, Markdown y XSS, notificaciones, IA con *fakes*, panel de administración (`Admin`), búsqueda, comandos y planificador | Nueva en cada test (`RefreshDatabase`) |
| `tests/Arch` | Reglas de arquitectura (sección 6) | Ninguna |
| `tests/js` | Componentes del foro, *composables*, utilidades y páginas, con Vitest y `@vue/test-utils` | — |

Son unos 1120 tests de PHP y 234 de TypeScript. En SQLite se omiten los 7
que necesitan pgvector; en PostgreSQL, 1 (el que comprueba precisamente la
alternativa sin pgvector).

## 2. Ejecutarla

Con Docker, los comandos de [03-desarrollo-local.md](03-desarrollo-local.md#6-tests-y-calidad)
(`make test`, `make test-pgsql`, `make test-js`, `make coverage`, `make lint`,
`make fix`). Lo que ejecutan por debajo:

```bash
composer test                           # Pest en paralelo, SQLite en memoria, --fail-on-warning
composer test:coverage                  # lo mismo con cobertura: falla por debajo del 95 %
vendor/bin/pest tests/Feature/Admin     # una carpeta
vendor/bin/pest --filter="ban a member" # los tests cuyo nombre coincide
npm test                                # Vitest
npm run test:coverage                   # Vitest con sus umbrales
npx vitest run tests/js/components/AiSummaryCard.spec.ts
```

Para PostgreSQL basta con apuntar la suite a una base de datos de pruebas
(`make test-pgsql` usa la `forum_testing` del servicio `postgres`, que ya
tiene la extensión `vector`); cada proceso paralelo crea y usa su propia copia:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=forum_testing \
DB_USERNAME=forum DB_PASSWORD=forum vendor/bin/pest --parallel
```

La suite no necesita `.env` ni `APP_KEY`: lee el `.env.testing` versionado
(SQLite en memoria, caché y colas en el propio proceso, Scout con el driver
`collection`, la IA apagada y la interfaz en inglés) y `tests/bootstrap.php`
genera una clave desechable en el entorno del proceso.

## 3. Escribir tests

- **Primero el test, y verlo fallar.** Un test que pasa antes de que exista
  el código no demuestra nada: si ocurre (porque el comportamiento ya
  existía), rompe a propósito el código que cubre y comprueba que falla.
- **Los datos, como los crea el foro.** Los *helpers* de `tests/Pest.php`
  usan las acciones del dominio, así que los contadores quedan bien:
  - `forumUser($rol)`: `guest`, `unverified`, `banned`, `member`, `moderator`,
    `banned moderator`, `admin` o `banned admin`;
  - `forumThread()` y `forumReply()`: un hilo (con su primer mensaje) o una
    respuesta, como los escribe un miembro;
  - `allows($usuario, $habilidad, $argumentos)`: lo que decide la *policy*;
  - `apiToken($usuario, $abilities)`: un token de Sanctum en texto plano.

  Las *factories* quedan para estados que el foro no produce por sí solo
  (un mensaje oculto, un reporte ya resuelto…).
- **Nada sale del proceso.** `Queue::fake()`, `Notification::fake()` y los
  *fakes* de laravel/ai (`Agent::fake([...])->preventStrayPrompts()`,
  `Embeddings::fake(...)`) sustituyen a los servicios reales; la guía de
  [06-ia.md](06-ia.md#11-pruebas) tiene ejemplos.
- **Solo en PostgreSQL**, lo que necesita pgvector:
  `->skip(fn (): bool => DB::connection()->getDriverName() !== 'pgsql', 'pgvector lives in PostgreSQL.')`.
- **Inertia.** `assertInertia()` comprueba la página y sus *props*;
  `loadDeferredProps()` resuelve las diferidas (resúmenes, hilos similares).
- **Panel de administración.** `Livewire::test(ListUsers::class)` con
  `TestAction::make('ban')->table($miembro)` para las acciones de una fila,
  `callAction()`, `assertActionHidden()` y `assertHasFormErrors()`. Un test
  por rol cuando se mira la navegación: Filament la construye una vez por
  aplicación, y dos peticiones del mismo test compartirían la del primero.
- **Carga diferida prohibida.** Fuera de producción, acceder a una relación
  que no se cargó es una excepción (`Model::preventLazyLoading()`): la
  consulta tiene que cargarla (`with()`).
- **Datos que se buscan, fijos.** Si un test busca por texto, fija los campos
  en los que se busca: un nombre aleatorio de Faker puede contener lo buscado.

## 4. SQLite y PostgreSQL

Localmente la suite corre sobre SQLite en memoria por rapidez; en CI también
sobre PostgreSQL 18 con pgvector, donde se activan los tests de vectores y se
mide la cobertura. Diferencias que ya han mordido:

- **Empates en el orden.** Dos filas creadas en el mismo segundo salen en
  cualquier orden en PostgreSQL: ordena también por `id`.
- **Funciones SQL.** Solo las que entienden los dos motores (`date()`,
  `count()`…); lo específico de PostgreSQL va detrás de una interfaz con
  alternativa, como la búsqueda de hilos similares.
- **Búsquedas de texto.** `LIKE` distingue mayúsculas en PostgreSQL y no en
  SQLite.

## 5. Cobertura

| Suite | Mínimo | Ahora | Dónde se mide |
|---|---|---|---|
| PHP (`app/`) | 95 % de líneas | 98,8 % en PostgreSQL y 97,5 % en SQLite | `composer test:coverage` (pcov); en CI, el job de PostgreSQL |
| TypeScript (`components/forum`, `composables`, `lib`) | 90 % de líneas, sentencias y funciones; 85 % de ramas | 96,31 %, 95,95 %, 94,66 % y 91,25 % | `npm run test:coverage` (umbrales en `vitest.config.ts`) |

El informe de Vitest queda en `coverage/js/index.html`; el de PHP, en CI, como
artefacto `coverage-php` (formato Clover) del job de PostgreSQL.

## 6. Análisis estático y reglas de arquitectura

- **Pint** (estilo de Laravel), **Larastan** (nivel 7 sobre `app`,
  `bootstrap/app.php`, `config`, `database` y `routes`), **ESLint**,
  **Prettier**, **vue-tsc** y **hadolint** (con las excepciones de
  `.hadolint.yaml`). `make lint` los ejecuta todos y `make fix` corrige lo que
  se puede corregir solo.
- **`tests/Arch/ArchTest.php`**:
  - nada de `dd`, `dump`, `ray`, `var_dump`, `print_r` ni `die`;
  - `declare(strict_types=1)` en todo `App`;
  - las acciones de `App\Actions` son finales y trabajan en `handle()` (las
    de Fortify responden a sus contratos: `create`, `reset`, `update`);
  - los eventos del dominio se emiten cuando la transacción se confirma;
  - los *presets* `laravel` y `security` de Pest. El de Laravel tiene
    excepciones explícitas, cada una explicada en el test: los
    controladores cuyos verbos son órdenes de moderación o de la bandeja
    (fijar, cerrar, ocultar, resolver, leer…), los *listeners* que siguen
    varios eventos con `handle{Evento}()`, las reglas de validación que
    comparten las *form requests* de la web y la API, las cifras cacheadas de
    la portada, los canales de notificación y el nombre que Filament da a su
    panel.

  laravel/pint declara el espacio de nombres `App\` en su propio
  `composer.json`; el test limita `App\` a `app/` para que las reglas no
  juzguen las clases de Pint.

## 7. Integración continua

Todas las acciones van fijadas por *commit* (el comentario dice la versión) y
los *workflows* solo pueden leer el repositorio, salvo la publicación de
imágenes (`packages: write`).

### `ci.yml`: cada *pull request* y cada *push* a `main`

| Job | Qué hace | Para reproducirlo |
|---|---|---|
| PHP · Pint y Larastan | `composer validate --strict`, Pint y Larastan | `make lint` |
| PHP 8.4 · PostgreSQL + cobertura | La suite sobre PostgreSQL 18 + pgvector con pcov y el mínimo del 95 % | `make test-pgsql` y `make coverage` |
| PHP 8.5 · SQLite | La suite con la siguiente versión de PHP | `composer test` con PHP 8.5 |
| JS · ESLint, Prettier, vue-tsc y Vitest | Genera las rutas de Wayfinder y pasa los cuatro | `make lint` y `make test-js` |
| Build · Vite (cliente y SSR) | `npm run build:ssr` | el mismo comando |
| Docker · imágenes y *smoke test* | hadolint, las cuatro imágenes (`app`, `web`, `ssr`, `dev`) con la caché de GitHub y el *smoke test* | sección 8 |
| GitHub Actions · actionlint | Valida los propios *workflows* (y sus *scripts* con shellcheck) | `docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.12` |

Un *push* nuevo a la misma rama cancela la ejecución anterior.

### `audit.yml`: avisos de seguridad

`composer audit` y `npm audit` contra los *lockfiles*, en cada *push* y
*pull request* y además cada lunes (un aviso puede publicarse sin que cambie
nada aquí). Cualquier aviso, de cualquier gravedad, hace fallar el job.
`npm audit` pasa por `.github/scripts/npm-audit-allowlist.mjs`, que tolera
solo lo que liste `.github/npm-audit-allowlist.json`: ahora mismo, nada. Una
excepción es una entrada con el GHSA, el paquete, la fecha y el motivo; el
*script* avisa cuando una ya no coincide con ningún aviso y falla cuando un
aviso tolerado recibe un parche que no rompe nada.

### `dependabot.yml`

Cada lunes, una *pull request* por ecosistema (composer, npm, GitHub Actions,
las imágenes base del `Dockerfile` y las de los servicios de Compose) con sus
actualizaciones menores y de parche, y una aparte para cada versión mayor. CI
las juzga como cualquier otra.

### `release.yml`: publicar una versión

Un *tag* `v*` construye `app`, `web` y `ssr` y las publica en GHCR con los
nombres que espera `compose.prod.yaml` (`ghcr.io/<propietario>/<repo>-app`…),
etiquetadas `1.2.3`, `1.2` y `latest`, con procedencia y SBOM:

```bash
git tag -a v1.2.3 -m "Versión 1.2.3"
git push origin v1.2.3
```

Después, si el repositorio lo tiene configurado, despliega esa versión por
SSH en el entorno protegido `production`; los pasos y lo que necesita están en
[04-despliegue.md](04-despliegue.md#10-publicar-una-versión).

## 8. *Smoke test* del stack de producción

`.github/scripts/smoke-test.sh` levanta `compose.prod.yaml` con las imágenes ya
construidas en `https://localhost`, con secretos desechables y un proyecto de
Compose propio (`laravel-forum-smoke`), y comprueba que:

1. todos los servicios arrancan sanos y las migraciones (`migrate`) terminan;
2. `/up` responde a través de Caddy, Nginx y PHP-FPM;
3. la portada llega renderizada por el servidor SSR (con su cabecera, que
   sin SSR no existe).

Al terminar borra el proyecto y sus volúmenes; si algo falla, imprime el estado
y los logs de cada servicio. No lee ni escribe `.env.production`: genera su
propio entorno y lo pasa con `ENV_FILE`. Para ejecutarlo en local, con los
puertos 80 y 443 libres, construye antes las imágenes con la plantilla (sin
`ENV_FILE`, Compose buscaría un `.env.production`):

```bash
ENV_FILE=.env.production.example docker compose -f compose.prod.yaml --env-file .env.production.example build
IMAGE_TAG=latest .github/scripts/smoke-test.sh
```

## 9. Problemas frecuentes

- **Un test falla solo en paralelo sobre PostgreSQL.** Casi siempre es un
  empate de orden (sección 4) o un valor único que dos *factories* repiten.
- **`LazyLoadingViolationException`.** Falta un `with()` en la consulta.
  En Filament, las columnas `relacion.campo` añaden su propio `with('relacion')`,
  que sustituye a un `with(['relacion' => fn …])` anterior: usa claves con
  punto (`'post.thread' => fn …`) para lo anidado.
- **La suite falla por un aviso.** `--fail-on-warning` lo trata como un fallo
  a propósito: un aviso de PHP es la forma que han tomado aquí los errores que
  todo lo demás daba por buenos.
- **Un test de búsqueda falla a veces.** Datos aleatorios que coinciden con el
  texto buscado (sección 3).
- **El *preset* de Laravel se queja de una clase nueva.** Sigue la convención
  (sufijos `Controller`, `Request`, `Command`…) o, si hay un motivo, añádela a
  las excepciones del test con el motivo al lado.
