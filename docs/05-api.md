# API REST v1

El foro ofrece una API JSON en `/api/v1` para aplicaciones móviles, bots e
integraciones. Comparte con la web las reglas de negocio: mismas *policies*,
mismas acciones y mismos límites, así que lo que un miembro no puede hacer en
la web tampoco lo hace por la API.

La referencia completa, generada desde el código con Scramble (OpenAPI 3.1),
está en:

- `/docs/api`: documentación navegable con un cliente para probar las
  peticiones;
- `/docs/api.json`: el documento OpenAPI, para generar clientes.

Esta guía explica lo que la referencia no cuenta: cómo autenticarse, qué
permiso pide cada grupo de rutas, las convenciones y los límites.

## 1. Autenticación

La API usa **tokens Bearer de Sanctum**, sin cookies ni sesión:

```http
Authorization: Bearer 12|kG8w…
Accept: application/json
```

Hay dos maneras de conseguir un token:

1. **Desde la web**, en *Ajustes → Tokens de API*: se elige un nombre y sus
   permisos, y el token se muestra una sola vez.
2. **Con la API**, iniciando sesión como en el formulario de acceso:

   ```bash
   curl -X POST https://foro.example.com/api/v1/auth/token \
     -H 'Accept: application/json' \
     -d email=ana@example.com -d password='…' -d device_name='Mi script'
   ```

   ```json
   {
     "token": "12|kG8w…",
     "abilities": ["read", "write", "messages"],
     "user": { "id": 3, "username": "ana", "email": "ana@example.com", … }
   }
   ```

   - `email` admite también el nombre de usuario (o se envía `username`).
   - `abilities` es opcional: por defecto `read`, `write` y `messages`.
     `moderate` solo se concede si se pide y quien la pide es del *staff*.
   - Si la cuenta tiene activada la **verificación en dos pasos**, la primera
     respuesta es un `422` con `"two_factor_required": true`; se repite la
     petición con `code` (el de la app de autenticación) o con
     `recovery_code` (cada código de recuperación sirve una vez).
   - Los miembros baneados o sin el email verificado reciben un `403`, como en
     la página de ajustes.

`DELETE /auth/token` revoca el token con el que se llama; los demás siguen
funcionando. Los tokens no caducan solos (`sanctum.expiration` es `null`): se
revocan desde los ajustes, con esa ruta o al banear al miembro, que pierde
todos los suyos.

### Permisos (*abilities*)

| Permiso | Para qué sirve |
|---|---|
| `read` | Leer como el miembro del token (incluidas las lecturas públicas), su perfil (`/me`) y sus notificaciones, que también puede marcar como leídas |
| `write` | Participar: hilos, respuestas, ediciones, reacciones, solución, suscripciones, reportes y cambios en el perfil propio |
| `messages` | Mensajes privados: leer conversaciones, empezarlas, responder y abandonarlas |
| `moderate` | Moderación (solo *staff*): fijar, cerrar, mover y restaurar hilos; ocultar, aprobar y restaurar mensajes; la cola de reportes |

El permiso dice lo que el token puede intentar; las *policies* deciden
después con el estado actual del miembro. Un moderador al que se le quita el
rol conserva su token con `moderate`, pero la moderación le responde `403`.

### Lectura pública

Las rutas de lectura (categorías, etiquetas, hilos, mensajes, perfiles y
búsqueda) no piden token. Con uno, se lee como su miembro: sus reacciones y
lo que ha leído, sus permisos sobre cada mensaje (`can`) y, para el *staff*, el
contenido oculto o pendiente. Si el token enviado no existe o está revocado
se responde `401`, y si no tiene `read`, `403`: quien manda un token espera que
se le reconozca.

## 2. Convenciones

- **Formato.** JSON en las dos direcciones. Las fechas van en ISO 8601. El
  contenido de los mensajes llega ya renderizado y saneado en `body_html`; el
  Markdown original (`body`) solo aparece a quien puede editar o citar.
- **Identificadores.** Hilos, mensajes, conversaciones y reportes van por id;
  categorías y etiquetas por *slug*, y los miembros por nombre de usuario.
- **Paginación.** Los listados devuelven `data`, `links` (`first`, `last`,
  `prev` y `next`, que conservan los filtros) y `meta` (`current_page`,
  `last_page`, `per_page`, `total`…). La página se pide con `?page=`.
- **Errores.** El formato estándar de Laravel: `message` y, en los `422`,
  `errors` por campo. A diferencia de la web, la API no ignora los
  parámetros que no entiende: un orden, filtro, categoría o etiqueta
  desconocidos son un `422`.
- **Contenido oculto.** Lo que quien pregunta no puede ver (hilos o mensajes
  ocultos, pendientes de revisión o borrados, conversaciones ajenas) responde
  `404` antes que cualquier otra regla, nunca `403`: así no se revela que
  existe.
- **Idempotencia.** Reacciones, suscripciones, solución, fijar y cerrar se
  ponen con `PUT` y se quitan con `DELETE`. Repetir una petición no cambia
  nada ni da error. Reportar dos veces un mismo mensaje mantiene el reporte
  abierto.
- **Sin email ajeno.** El email solo aparece en `/me` y en la respuesta de
  `/auth/token`, y no se puede cambiar con un token (quien lo cambiara podría
  quedarse la cuenta con un restablecimiento de contraseña).

## 3. Límites de frecuencia

| Límite | Valor | Clave |
|---|---|---|
| General | 120 por minuto | Por miembro (con token) |
| General | 60 por minuto | Por dirección IP (sin token) |
| Pedir un token | 5 por minuto | Por login y dirección |
| Segundo factor fallido | 5 por minuto | Por miembro, desde cualquier dirección |
| Abrir hilos | 3 por minuto | Por miembro |
| Responder | 6 por minuto | Por miembro |
| Editar mensajes e hilos | 10 por minuto | Por miembro |
| Reaccionar | 60 por minuto | Por miembro |
| Reportar | 10 por hora | Por miembro |
| Mensajes privados | 10 por minuto | Por miembro |
| Pedir un resumen de IA | 5 por hora | Por miembro |

Los de participación son los mismos que en la web y se comparten con ella
(`config/forum.php`, `rate_limits`). Cada respuesta lleva `X-RateLimit-Limit`
y `X-RateLimit-Remaining` del límite más próximo a agotarse; al superarlo se
responde `429` con `Retry-After`.

## 4. Rutas

### Cuenta

| Método y ruta | Permiso | Qué hace |
|---|---|---|
| `POST /auth/token` | — | Emite un token (email o usuario, contraseña, `device_name`, `abilities`, `code` o `recovery_code`) |
| `DELETE /auth/token` | cualquiera | Revoca el token de la petición |
| `GET /me` | `read` | El perfil propio, con email, idioma y contadores de no leídos |
| `PATCH /me` | `write` | Cambia nombre, usuario, biografía, ubicación, web o idioma (solo lo que se envía) |

### Foro: lectura (token opcional)

| Método y ruta | Qué hace |
|---|---|
| `GET /categories` y `/categories/{slug}` | Las categorías en su orden, con su último hilo público |
| `GET /tags` y `/tags/{slug}` | Las etiquetas por nombre |
| `GET /threads` | Hilos con `sort` (`activity`, `newest`, `replies`, `views`), `filter` (`all`, `unanswered`, `solved`, `unsolved` y, con token, `following` y `mine`), `category` y `tag` |
| `GET /threads/{id}` | Un hilo (cuenta la visita) y, en `meta.subscribed`, si el miembro lo sigue |
| `GET /threads/{id}/posts` | Sus mensajes, del primero al último; con token, los marca como leídos |
| `GET /threads/{id}/similar` | Hasta 5 hilos relacionados: por significado si la IA está activa sobre PostgreSQL, por palabras si no ([06-ia.md](06-ia.md)) |
| `GET /threads/{id}/summary` | El resumen de IA guardado (`summary_html`, `generated_at` y `stale`); `404` si los resúmenes están apagados o el hilo no tiene |
| `GET /posts/{id}` | Un mensaje suelto (el de una notificación, por ejemplo) |
| `GET /users/{username}` | Un perfil público, con sus pestañas en `/threads` y `/posts` |
| `GET /search/threads?q=` y `/search/posts?q=` | Búsqueda, con `category` y `tag` opcionales; solo encuentra contenido público |

### Foro: participación (`write`)

| Método y ruta | Qué hace |
|---|---|
| `POST /threads` | Abre un hilo (`category_id`, `title`, `body`, `tags`) → `201` |
| `PATCH /threads/{id}` | Cambia el título, las etiquetas o ambos |
| `DELETE /threads/{id}` | Borra un hilo (su autor, mientras nadie haya respondido; el *staff*, siempre) → `204` |
| `POST /threads/{id}/posts` | Responde (`body`, `reply_to_id` para citar) → `201` |
| `PATCH /posts/{id}` y `DELETE /posts/{id}` | Edita o borra un mensaje (borrar el primero borra el hilo) |
| `PUT`/`DELETE /posts/{id}/reactions/{tipo}` | Reacciona o retira la reacción (`like`, `love`, `laugh`, `insightful`, `celebrate`); responde el resumen de reacciones |
| `PUT`/`DELETE /threads/{id}/solution` | Marca (`post_id`) o desmarca la solución; responde el hilo |
| `PUT`/`DELETE /threads/{id}/subscription` | Sigue o deja de seguir el hilo → `204` |
| `POST /threads/{id}/summary` | Pide el resumen de IA: `202` mientras se escribe, `200` con él si el guardado está al día; `404` donde no hay resumen posible (IA apagada, menos de 5 respuestas) |
| `POST /posts/{id}/reports` | Reporta un mensaje (`reason`: `spam`, `abuse`, `off_topic`, `illegal`, `other`; `details`) → `202` |

### Notificaciones (`read`)

| Método y ruta | Qué hace |
|---|---|
| `GET /notifications` | Las notificaciones, de la más reciente a la más antigua (`?unread=1`, solo las no leídas). `post_id` lleva al mensaje, salvo que ya no se pueda ver |
| `PUT /notifications/{id}/read` | Marca una como leída |
| `PUT /notifications/read` | Marca todas como leídas → `204` |

### Mensajes privados (`messages`)

| Método y ruta | Qué hace |
|---|---|
| `GET /conversations` | La bandeja: conversaciones no abandonadas, con lo no leído marcado |
| `POST /conversations` | Empieza una (`recipients`: nombres de usuario, `subject`, `body`) → `201` |
| `GET /conversations/{id}` | Una conversación |
| `GET /conversations/{id}/messages` | Sus mensajes; leerlos la marca como leída |
| `POST /conversations/{id}/messages` | Responde (`body`) → `201` |
| `POST /conversations/{id}/leave` | La abandona → `204` |

Solo sus participantes encuentran una conversación; el *staff* tampoco.

### Moderación (`moderate`, solo *staff*)

| Método y ruta | Qué hace |
|---|---|
| `PUT`/`DELETE /moderation/threads/{id}/pin` | Fija o desfija el hilo |
| `PUT`/`DELETE /moderation/threads/{id}/lock` | Cierra o abre el hilo |
| `POST /moderation/threads/{id}/move` | Lo mueve de categoría (`category_id`) |
| `POST /moderation/threads/{id}/restore` | Restaura un hilo borrado |
| `POST /moderation/posts/{id}/hide` | Oculta un mensaje (`reason` opcional) |
| `POST /moderation/posts/{id}/approve` | Aprueba un mensaje oculto o pendiente |
| `POST /moderation/posts/{id}/restore` | Restaura un mensaje borrado |
| `GET /moderation/reports` | La cola: los reportes abiertos, del más antiguo al más reciente (`?status=resolved` o `dismissed` para el historial) |
| `POST /moderation/reports/{id}/resolve` | Lo resuelve con los demás reportes abiertos del mensaje (`hide_post`, `note`); un mensaje retenido para revisión queda oculto salvo con `hide_post: false` |
| `POST /moderation/reports/{id}/dismiss` | Lo descarta (`note`); un mensaje retenido para revisión se publica de nuevo salvo con `approve_post: false` |

Todas las acciones quedan en el registro de moderación, como las de la web.

## 5. Ejemplo: de cero a una respuesta

```bash
API=https://foro.example.com/api/v1
TOKEN=$(curl -s -X POST $API/auth/token -H 'Accept: application/json' \
  -d email=ana@example.com -d password="$PASSWORD" -d device_name=bot | jq -r .token)

# Los hilos sin respuesta de la categoría «laravel», los más nuevos primero
curl -s "$API/threads?category=laravel&filter=unanswered&sort=newest" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'

# Responder al hilo 42 citando su primer mensaje (el 137)
curl -s -X POST $API/threads/42/posts -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -d body='Prueba a reiniciar Horizon.' -d reply_to_id=137

# Un 👍 al mensaje 140 (repetirlo no suma otro)
curl -s -X PUT $API/posts/140/reactions/like -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

## 6. Dónde está en el código

| Pieza | Ubicación |
|---|---|
| Rutas | `routes/api.php` |
| Controladores | `app/Http/Controllers/Api/V1` (moderación en `Moderation/`) |
| Validación | `app/Http/Requests/Api` y las *form requests* compartidas con la web |
| Token opcional en la lectura | `app/Http/Middleware/AuthenticateOptionalToken.php` (alias `auth.optional`) |
| Limitadores `api` y `api-token` | `AppServiceProvider::configureRateLimiting()` |
| Documentación OpenAPI | `config/scramble.php` y `AppServiceProvider::configureApiDocs()` |
| Tests | `tests/Feature/Api/V1` |
