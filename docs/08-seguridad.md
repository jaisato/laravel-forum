# Seguridad

Cómo protege el foro las cuentas, el contenido y los datos de sus miembros, qué
queda en manos de quien lo despliega y qué riesgos siguen abiertos. La tabla de
amenazas de la especificación está en
[02-arquitectura.md](02-arquitectura.md#9-seguridad); esta guía cuenta cómo
quedó implementada.

## 1. Cuentas y acceso

Laravel Fortify gestiona el registro, el inicio de sesión, la verificación del
email, el restablecimiento de la contraseña, la verificación en dos pasos (TOTP,
con códigos de recuperación) y las *passkeys* (WebAuthn).

- **Email verificado para participar.** Sin verificarlo, un miembro lee y
  busca, pero no publica, no reacciona ni envía mensajes. Cambiar de email
  vuelve a exigir la verificación.
- **Contraseñas.** En producción, 12 caracteres como mínimo, con mayúsculas,
  minúsculas, números y símbolos, y que no aparezcan en filtraciones conocidas
  (Have I Been Pwned por *k*-anonimato: del servidor solo sale el principio
  del *hash*). Fuera de producción bastan 8. Se guardan con bcrypt.
- **La contraseña, otra vez.** La página de seguridad de los ajustes
  (contraseña, verificación en dos pasos y *passkeys*) y la de los *tokens* de
  la API la piden si no se ha confirmado en las últimas 3 horas
  (`AUTH_PASSWORD_TIMEOUT`, en segundos): una sesión robada no basta para
  crear un *token* que no caduca.
  Activar la verificación en dos pasos exige además un código de la
  aplicación.
- **Sesiones.** En Redis, con cookies `HttpOnly`, `SameSite=Lax` y, en
  producción, `Secure` (`SESSION_SECURE_COOKIE=true` en la plantilla); caducan
  tras 120 minutos sin actividad. Laravel cifra todas las cookies salvo
  `appearance` y `sidebar_state`, dos preferencias de la interfaz.
- **CSRF.** Laravel deja el token en la cookie `XSRF-TOKEN` e Inertia lo
  devuelve en la cabecera `X-XSRF-TOKEN` de cada petición. La API no usa
  cookies: se autentica con *tokens* Bearer.

Los límites contra la fuerza bruta:

| Qué | Límite | Contado por |
|---|---|---|
| Iniciar sesión | 5 por minuto | email o usuario, e IP |
| Código de la verificación en dos pasos | 5 por minuto | cuenta |
| *Passkeys* | 10 por minuto | credencial (o sesión), e IP |
| Pedir un *token* de la API | 5 por minuto | email o usuario, e IP |
| Cambiar la contraseña | 6 por minuto | miembro |

Los límites contra el spam (hilos, respuestas, reacciones, reportes, mensajes
privados y resúmenes) se configuran en `config/forum.php` (`rate_limits`) y
valen igual para la web y la API; los de la API están en
[05-api.md](05-api.md#3-límites-de-frecuencia).

## 2. Permisos

Cada acción pasa por una *policy* en el servidor, venga de la web, de la API o
del panel: la interfaz solo esconde lo que el servidor ya va a rechazar. Los
roles y lo que puede hacer cada uno están en
[02-arquitectura.md](02-arquitectura.md#5-roles-y-permisos).

- **Lo que no se puede ver no existe.** Un hilo o un mensaje oculto o
  borrado, o una conversación ajena, responde 404 y no 403: no se confirma
  que exista.
- **Baneos.** Un miembro baneado sigue leyendo, pero las *policies* le niegan
  publicar, editar, reaccionar, reportar y escribir mensajes privados (403), y
  su perfil público se queda como está mientras dure (puede vaciarlo, y cambiar
  su email y su idioma). Sus *tokens* de la API se revocan al banearlo, así que
  la API le responde 401, y no puede pedir otros hasta que termine. Un
  moderador o administrador baneado pierde además sus permisos de *staff*,
  también en el panel y en Horizon.
- **Panel (`/admin`).** Los moderadores ven la moderación y los
  administradores, todo. Funciona en modo estricto: un recurso o una acción
  sin su método en la *policy* se deniega, en lugar de permitirse como haría
  Filament por defecto.
- **Horizon (`/horizon`).** Solo la administración.
- **Tokens de la API.** Cada uno lleva sus *abilities*: `read`, `write`,
  `messages` y, solo para el *staff*, `moderate`. Pedir uno con el email (o
  el usuario) y la contraseña exige el segundo factor si la cuenta lo tiene
  activo. No caducan solos: se revocan desde los ajustes, al banear la
  cuenta, al borrarla y al cambiar o restablecer la contraseña (quien se hizo
  con ella pierde también sus *tokens*). Sin el permiso `messages`, un
  *token* tampoco ve las notificaciones de mensajes privados.

## 3. Lo que escriben los miembros

Los mensajes se escriben en Markdown y el servidor los convierte a HTML
(CommonMark) una sola vez, al guardarlos:

- El HTML que escribe un miembro se escapa: se ve como texto, nunca se
  interpreta.
- Los enlaces solo pueden ser `http`, `https`, `mailto` o relativos, y las
  imágenes, URL absolutas `http` o `https`. Con cualquier otro esquema
  (`javascript:`, `data:`, `vbscript:`…, también disimulado con tabuladores o
  saltos de línea) queda solo el texto del enlace o la descripción de la
  imagen.
- Los enlaces externos llevan `rel="nofollow ugc noopener noreferrer"` y se
  abren en otra pestaña; las imágenes se cargan en diferido y sin `Referer`.
- Las menciones solo enlazan a miembros que existen.
- En el navegador, `v-html` solo recibe ese HTML ya saneado y el SVG del
  código QR que genera Fortify; ESLint (`vue/no-v-html`) obliga a justificar
  cualquier otro uso.

`tests/Feature/Markdown/MarkdownRendererTest.php` reúne los casos de XSS. Los
resúmenes de la IA siguen el mismo camino que los mensajes.

## 4. Datos personales

- **Email.** Solo lo ve su dueño (en sus ajustes y en `/api/v1/me`) y, en el
  panel, la administración; los moderadores no.
- **Direcciones IP.** No se guardan: las sesiones viven en Redis. Con
  `SESSION_DRIVER=database`, Laravel guardaría la IP y el navegador de cada
  sesión.
- **Avatares.** Se vuelven a codificar como WebP de 256×256 con GD, que no
  copia los metadatos de la foto (EXIF, ubicación GPS), y se guardan con un
  nombre aleatorio.
- **Mensajes privados.** Solo los ven sus participantes: ni los moderadores ni
  la administración tienen acceso, desde el panel ni desde la API, y la IA no
  los procesa.
- **Borrar la cuenta.** Desde los ajustes. Desaparecen los datos personales
  (nombre, email, biografía, ubicación, web, avatar, idioma y preferencias),
  las formas de entrar (contraseña, verificación en dos pasos, *passkeys* y
  *tokens*), las notificaciones (las suyas y las que causó a otros), las
  suscripciones y las lecturas. Sus mensajes se quedan, firmados por «Usuario
  eliminado», y el email queda libre.
- **Nombres de usuario retirados.** Las menciones enlazan a `/u/{usuario}` y
  `@usuario` avisa a quien se llame así, de modo que un nombre que alguien deja
  (al cambiarlo o al borrar su cuenta) no lo puede tomar nadie más: solo su
  dueño puede recuperarlo, y la dirección antigua de su perfil lleva a la
  nueva.
- **IA.** Qué se envía a los proveedores y cómo se aíslan las instrucciones
  que pudiera esconder un mensaje:
  [06-ia.md](06-ia.md#9-privacidad-y-seguridad).

## 5. Producción

Lo que ya trae `compose.prod.yaml` (más detalle en
[04-despliegue.md](04-despliegue.md#7-seguridad)):

- **Siempre HTTPS.** Caddy obtiene y renueva los certificados, redirige HTTP a
  HTTPS y envía HSTS (un año, con subdominios). Es el único servicio con
  puertos publicados.
- **Cabeceras.** Nginx añade `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy:
  strict-origin-when-cross-origin` y una `Permissions-Policy` que apaga la
  cámara, el micrófono, la geolocalización, los pagos y el USB. Ni Caddy ni
  Nginx anuncian su versión.
- **Aislamiento.** PostgreSQL, Redis y Meilisearch viven en una red interna,
  sin salida a Internet, cada uno con su contraseña o clave. Ningún contenedor
  puede ganar privilegios (`no-new-privileges`), las imágenes propias no se
  ejecutan como root, y `web` y `ssr` tienen el sistema de ficheros en solo
  lectura.
- **Proxies de confianza.** Laravel solo cree `X-Forwarded-For`, `-Proto` y
  `-Port` cuando llegan de `TRUSTED_PROXIES`, y nunca `X-Forwarded-Host`.
- **Sin depuración.** `APP_DEBUG=false` en la plantilla: un error muestra la
  página de error del foro, sin trazas.
- **Base de datos a salvo de despistes.** En producción, Laravel se niega a
  ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:reset`,
  `migrate:rollback` y `db:wipe`.

## 6. Secretos

- **Dónde.** Los secretos de producción están en `.env.production` (permisos
  `600`, fuera de git). `.gitignore` excluye todos los `.env*` salvo las
  plantillas y `.env.testing`, que no guarda ninguno: la suite genera su
  `APP_KEY` en cada ejecución.
- **Rotar `APP_KEY`.** Genera una nueva y deja la anterior en
  `APP_PREVIOUS_KEYS`: lo cifrado con ella, sesiones incluidas, se sigue
  leyendo. Quítala cuando ya no haga falta.
- **Credenciales antiguas en el historial.** Unas plantillas de la versión
  anterior tenían una `APP_KEY` y una contraseña de base de datos reales, y
  siguen en el historial de git: qué hacer, en el
  [README](../README.md#aviso-de-seguridad-credenciales-en-el-historial).
- **CI.** Los *workflows* solo usan el `GITHUB_TOKEN` de cada ejecución, de
  solo lectura salvo para publicar imágenes, y el *checkout* no lo guarda en
  el repositorio. El *token* llega a Composer dentro del `Dockerfile` como
  secreto de construcción, que no queda en ninguna capa. La clave SSH del
  despliegue vive en el entorno protegido `production`, y la del servidor se
  comprueba contra `DEPLOY_KNOWN_HOSTS`.

## 7. Dependencias

- `composer audit` y `npm audit` revisan los *lockfiles* en cada *push* y cada
  lunes: cualquier aviso hace fallar CI
  ([07-testing-y-ci.md](07-testing-y-ci.md#7-integración-continua)).
- Dependabot propone cada lunes las actualizaciones de Composer, npm, GitHub
  Actions y las imágenes de Docker.
- Las acciones de GitHub van fijadas por *commit* y hadolint y actionlint por
  *digest*; las imágenes base, por versión, y Dependabot las mueve.
- Las imágenes publicadas llevan procedencia (*provenance*) y SBOM.

## 8. Comunicar una vulnerabilidad

No abras un *issue* público: escribe en privado a quien mantiene el
repositorio, por ejemplo con los avisos de seguridad privados de GitHub
(*Security* → *Report a vulnerability*) si están activados.

## 9. Riesgos conocidos

- **Sin `Content-Security-Policy`.** La defensa contra el XSS es el saneado
  del servidor (sección 3); una CSP sería la segunda línea. El panel (Livewire
  y Alpine) y los estilos en línea la complican: conviene empezar con
  `Content-Security-Policy-Report-Only` y probarla página a página.
- **Imágenes de terceros.** Las imágenes de los mensajes se cargan desde su
  servidor de origen, que ve la IP de quien lee (no la página, gracias a
  `no-referrer`). Un *proxy* de imágenes lo evitaría.
- **Tokens sin caducidad.** Para que caduquen, fija `expiration` (en minutos)
  en `config/sanctum.php` y programa `sanctum:prune-expired`.
- **La clave maestra de Meilisearch.** La aplicación usa la clave maestra; el
  servicio solo es accesible desde la red interna, así que el riesgo es el de
  un contenedor de la aplicación comprometido.
- **`TRUSTED_PROXIES`.** Si cambia lo que hay delante del foro, ajústalo: con
  un rango demasiado estrecho todas las peticiones parecen venir del *proxy* y
  comparten los límites de inicio de sesión; con uno demasiado amplio (`*`),
  cualquiera elige su IP.
- **Copias de seguridad.** `pg_dump` y el volumen de ficheros salen sin
  cifrar: guárdalas cifradas y fuera del servidor.
