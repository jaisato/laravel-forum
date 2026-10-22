# Plan de implementación: reconstrucción del foro (Laravel 13)

> **Para agentes:** SUB-SKILL REQUERIDA: superpowers:subagent-driven-development (recomendada) o
> superpowers:executing-plans para implementar este plan tarea a tarea. Los pasos usan casillas
> (`- [ ]`) para el seguimiento.

**Goal:** Sustituir el esqueleto Laravel 12/Jetstream por un foro completo sobre Laravel 13 con
web Inertia 3 + Vue 3 (SSR), API v1, IA con `laravel/ai`, panel Filament 5, Docker local/producción,
CI/CD y documentación.

**Architecture:** Base del *starter kit* oficial de Vue. Toda escritura pasa por clases `App\Actions\*`
compartidas por web, API, Filament y consola; los efectos secundarios cuelgan de eventos de dominio
y *jobs* en cola. Las *props* de Inertia y la API usan los mismos `JsonResource`.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia 3, Vue 3.5 + TS, Tailwind 4, Fortify, Sanctum, Wayfinder,
Scout + Meilisearch, Horizon, laravel/ai, Filament 5, Scramble, Pest 5, Vitest, Docker, GitHub Actions.

**Spec:** `docs/02-arquitectura.md`

## Global Constraints

- PHP `^8.4` con `config.platform.php = "8.4.1"`; `laravel/framework ^13.38`; `inertiajs/inertia-laravel ^3.5`; Node ≥ 22 (imágenes Docker: Node 24).
- `declare(strict_types=1);` en todo fichero PHP nuevo o reescrito bajo `app/`, `database/` y `tests/`.
- Toda modificación de dominio pasa por una clase `final` en `App\Actions\…` con método público `handle(...)`; controladores, Filament y comandos no escriben modelos directamente.
- Textos de interfaz con `__()` (PHP) y `trans()`/`$t()` (Vue). Claves en inglés; traducción completa en `lang/es.json`; idioma por defecto `es`, alternativo `en`.
- Valores fijados por la especificación (en `config/forum.php`):
  - paginación 20;
  - máximo 5 etiquetas por hilo;
  - reputación +1 por reacción y +15 por solución;
  - usuarios en línea: 15 min;
  - deduplicación de vistas: 60 min;
  - avatar 256×256 WebP (≤ 2048 KB);
  - API 120/min autenticado y 60/min invitado;
  - IA:
    - umbral de ocultación 0,8;
    - resumen desde 5 respuestas, regenerado tras ≥ 3 respuestas nuevas o 24 h;
    - *embeddings* de 1536 dimensiones, similitud mínima 0,75 y top 5;
    - hasta 3 etiquetas sugeridas.
- El email de un usuario solo se serializa para él mismo (web y API).
- Agentes de IA sin `#[Temperature]` (la API de Anthropic rechaza los parámetros de muestreo desde el 2026-10-21) y con `#[MaxTokens]` explícito. Modelo de texto por defecto `claude-opus-5-5`.
- Cobertura mínima: PHP ≥ 95 % de líneas (job PostgreSQL de CI) y TypeScript ≥ 90 % de líneas en `resources/js/{components/forum,composables,lib}`.
- Docker: puertos de desarrollo en `127.0.0.1`, procesos no root y *healthcheck* en cada servicio.
- Commits: *conventional commits* en español con los *trailers* `Co-Authored-By` y `Claude-Session`.

## Review Focus

1. **Markdown malicioso** (`<script>`, `javascript:`, `onerror=`, HTML en menciones, salida de IA): siempre escapado o neutralizado. → Tarea 7 (`MarkdownRendererTest`).
2. **Contenido oculto, pendiente o borrado** filtrándose por listados, búsqueda, API, sitemap, similares, perfil o notificaciones: invisible para miembros y visible para el *staff* donde corresponda. → Tareas 17, 20, 23, 25 y 29 (un test «hidden thread is not leaked» en cada una).
3. **Doble envío** de reacción, suscripción, reporte o solución: idempotente, sin error 500 y sin contadores negativos. → Tareas 10 y 25.
4. **Usuarios baneados o eliminados:** el baneado no publica ni por web ni por API (tokens revocados) y el baneo temporal expira; el contenido de cuentas eliminadas se muestra como «Usuario eliminado» sin errores de `null`. → Tareas 3, 11, 22 y 25.
5. **IA no disponible o con salida inválida** (excepción, veredicto fuera del enumerado, etiquetas inexistentes, resumen vacío): sin efectos, sin romper la petición y con log. Con la IA desactivada, la interfaz oculta los controles y la API responde 404. → Tareas 27, 28 y 29.

---

### Task 1: Base Laravel 13 (*starter kit* de Vue)

**Files:**
- Delete: legado Jetstream:
  - `app/Actions/Jetstream`, `app/Http/Controllers/PostController.php`, `app/Models/{Post,Comment}.php`, `app/Http/Resources/*`;
  - `app/Providers/{Jetstream,Telescope,Testing}ServiceProvider.php`, `config/{jetstream,telescope}.php`;
  - `resources/js/**` (JS antiguo), `resources/markdown`, migraciones `2024_*`;
  - `tests/Feature/{Jetstream,Controllers}`, `tests/Unit/UserResourceTest.php`;
  - `docker-compose.yml`, `tailwind.config.js`, `postcss.config.js`, `jsconfig.json`, `public/assets`.
- Create: copia del *starter kit* `laravel/vue-starter-kit@d282e81`, excepto:
  - `chisel*.php`, `InstallFeaturesCommand.php` y los marcadores `/* @chisel-* */`;
  - `pnpm-workspace.yaml` y `vite-plus` (se usan `vite`/`vitest`/`eslint`/`prettier`).
- Keep: `tests/bootstrap.php`, `.env.testing` (ampliado), `.github/` (se reescribe en la tarea 35), `docs/`.
- Create: `eslint.config.js`, `.prettierrc.json`, `.prettierignore`, `vitest.config.ts`, `tests/js/setup.ts`, `tests/Pest.php`, `tests/Feature/HealthTest.php`.

**Interfaces:**
- Produces:
  - Composer:
    - `composer test` = `pest --parallel`;
    - `composer test:coverage` = `pest --coverage --min=95`;
    - `composer lint` = `pint`;
    - `composer analyse` = `phpstan analyse`.
  - npm: `dev`, `build`, `build:ssr`, `test` (`vitest run`), `test:coverage`, `lint`, `format`, `format:check`, `types:check` (`vue-tsc --noEmit`).

- [ ] Convertir a Pest los tests del *starter kit* (`tests/Feature/Auth/*`, `Settings/*`, `DashboardTest` → `HomeTest` provisional); en `HealthTest`, `get('/up')->assertOk()`.
- [ ] `composer install` y `npm install`; `vendor/bin/pest`, `npm run build`, `npm run types:check` y `npm run lint` en verde.
- [ ] Commit `feat!: reconstruir sobre Laravel 13 y el starter kit oficial de Vue`.

### Task 2: Plataforma: PostgreSQL, Redis, colas, búsqueda, IA, admin, i18n

**Files:**
- Create: `config/forum.php` (todas las claves de *Global Constraints* más `ai.*`).
- Configuración publicada: Scout, Horizon (`HorizonServiceProvider`), Sanctum (`routes/api.php` y migración de tokens), `config/ai.php`, Scramble, `config/images.php` y `AdminPanelProvider` (vacío).
- Create: `app/Http/Middleware/SetLocale.php`, `app/Http/Controllers/LocaleController.php`.
- Create (traducciones): `lang/{es,en}.json` y `lang/es/{auth,pagination,passwords,validation}.php`.
- Modify: `.env.example` (valores de Docker: `DB_CONNECTION=pgsql`, `REDIS_HOST=redis`, `SCOUT_DRIVER=meilisearch`, `QUEUE_CONNECTION=redis`, `FORUM_AI_ENABLED=false`…), `.env.testing`, `bootstrap/app.php` (alias `abilities`, `ability`, `not-banned`; `SetLocale` en web), `resources/js/app.ts`, `resources/js/ssr.ts`.
- Test: `tests/Feature/LocaleTest.php`.

**Interfaces:**
- Produces:
  - `App\Support\Locale::supported(): array` = `['es','en']`, `App\Support\Locale::default(): string` = `'es'`;
  - ruta `POST /locale` (`locale.update`) con body `{locale}`;
  - *prop* compartida `locale`.

- [ ] Tests:
  - `SetLocale` prioriza usuario > sesión > `Accept-Language` > `es`;
  - `POST /locale` guarda en sesión y en `users.locale` si hay sesión iniciada;
  - un locale no soportado da 422.
- [ ] Implementar y dejar la suite en verde (SQLite).
- [ ] Commit `feat: configurar PostgreSQL, Redis, Horizon, Scout, Sanctum, IA, Filament e i18n`.

### Task 3: Usuarios, roles y baneos

**Files:**
- Modify: migración `create_users_table` (añadir `username` único, `role`, `reputation`, `threads_count`, `posts_count`, `avatar_path`, `bio`, `location`, `website`, `locale`, `preferences` JSON, `banned_at`, `banned_until`, `ban_reason`, `last_seen_at`, `deleted_at`), `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Actions/Fortify/CreateNewUser.php`, `app/Providers/FortifyServiceProvider.php`.
- Create: `app/Enums/Role.php`, `app/Http/Middleware/{TrackLastSeen,EnsureUserIsNotBanned}.php`.
- Test: `tests/Unit/Enums/RoleTest.php`, `tests/Feature/Users/{UserModelTest,TrackLastSeenTest,EnsureUserIsNotBannedTest}.php`, `tests/Feature/Auth/{RegistrationTest,AuthenticationTest}.php`.

**Interfaces:**
- Produces:
  - `Role: string {Admin='admin', Moderator='moderator', Member='member'}` con `label(): string` e `isStaff(): bool`.
  - `User`:
    - consultas de rol: `isAdmin()`, `isModerator()`, `isStaff()`;
    - `isBanned(): bool` (`banned_at` no nulo y `banned_until` nulo o futuro);
    - `canParticipate(): bool` (verificado y no baneado);
    - `wantsEmail(string $type): bool`;
    - atributos `avatar_url` (`?string`) y `preferences` (array con defaults `User::DEFAULT_PREFERENCES`);
    - *scope* `online(int $minutes)`.
  - Preferencias: `email_replies`, `email_mentions`, `email_solutions`, `email_messages`, `email_moderation`, `allow_private_messages` (todas `true`).
  - Estados de factory: `admin()`, `moderator()`, `banned(?CarbonInterface $until = null)`, `unverified()`, `withTwoFactor()`.
  - Login con `email` o `username` (campo `email` del formulario).

- [ ] Tests:
  - matriz de `isBanned` (sin baneo, permanente, temporal futuro y temporal expirado);
  - el registro exige `username` válido, único y en minúsculas;
  - login con *username*;
  - `TrackLastSeen` actualiza como máximo cada 5 min;
  - `not-banned`: web → *redirect back* con `flash.error`; JSON → 403.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(users): roles, baneos, preferencias y login por username`.

### Task 4: Categorías, etiquetas, hilos y mensajes

**Files:**
- Create: migraciones `categories`, `tags`, `threads`, `thread_tag` y `posts` (columnas e índices de la especificación, §7).
- Create: `app/Enums/{ThreadStatus,PostStatus}.php` (`published`, `pending_review`, `hidden`), `app/Models/Concerns/HasUniqueSlug.php`, `app/Models/{Category,Tag,Thread,Post}.php` y sus factories.
- Test: `tests/Feature/Forum/Models/{SlugTest,ThreadVisibilityTest,PostVisibilityTest,RelationsTest}.php`.

**Interfaces:**
- Produces:
  - `HasUniqueSlug`: rellena `slug` al crear desde `slugSource(): string`, añade sufijos `-2`, `-3`… y el slug no cambia al editar.
  - `Thread`:
    - *route key* `slug`;
    - relaciones `category()`, `author()` (`user_id`, `withTrashed`), `posts()`, `firstPost()`, `lastPost()`, `solution()`, `tags()`;
    - *scope* `visibleTo(?User)`:
      - publicado para todos;
      - `pending_review` también para el autor;
      - cualquier estado para el *staff*;
    - `isSolved(): bool`.
  - `Post`: `thread()`, `author()`, `replyTo()`, `reactions()`, `reports()`, *scope* `visibleTo(?User)`, `isFirst(): bool`.
  - `Category::scopeOrdered()`.

- [ ] Tests:
  - slugs únicos con sufijo y estables al cambiar el título;
  - la visibilidad devuelve lo esperado para invitado, autor, miembro y *staff*;
  - el autor de un usuario borrado se resuelve (*soft delete*).
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(forum): modelos de categorías, etiquetas, hilos y mensajes`.

### Task 5: Reacciones, suscripciones, lecturas, reportes, moderación y mensajería (esquema)

**Files:**
- Create: migraciones `reactions` (único `post_id`, `user_id`, `type`), `thread_subscriptions` y `thread_reads` (únicos `thread_id`, `user_id`), `reports`, `moderation_actions`, `conversations`, `conversation_participants` y `messages`.
- Create: enums `ReactionType` (`like`, `love`, `laugh`, `insightful`, `celebrate`, con `emoji()`), `ReportReason`, `ReportSource`, `ReportStatus`, `ModerationActionType`.
- Create: modelos y factories correspondientes.
- Test: `tests/Feature/Forum/Models/SchemaConstraintsTest.php`.

- [ ] Tests:
  - las restricciones únicas rechazan duplicados (`UniqueConstraintViolationException`);
  - las relaciones devuelven los modelos esperados.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(forum): esquema de reacciones, suscripciones, reportes y mensajería`.

### Task 6: `thread_embeddings` (solo PostgreSQL)

**Files:**
- Create: migración condicional (`Schema::ensureVectorExtensionExists()`, `vector('embedding', dimensions: config('forum.ai.similar.dimensions'))->index()`), `app/Models/ThreadEmbedding.php` (cast `AsVector`).
- Test: `tests/Feature/Ai/ThreadEmbeddingSchemaTest.php` (grupo `pgsql`).

- [ ] Test:
  - en SQLite la tabla no existe y la migración no falla;
  - en PostgreSQL se guarda y lee un vector de 1536 dimensiones.
- [ ] Implementar, ejecutar en SQLite y en PostgreSQL.
- [ ] Commit `feat(ai): tabla de embeddings con pgvector`.

### Task 7: Markdown seguro y menciones

**Files:**
- Create: `app/Support/Markdown/{MarkdownRenderer,MentionParser}.php`.
- Test: `tests/Unit/Markdown/MentionParserTest.php`, `tests/Feature/Markdown/MarkdownRendererTest.php`.

**Interfaces:**
- Produces:
  - `MentionParser::extract(string $markdown): list<string>`: *usernames* en minúsculas, únicos; ignora código y emails.
  - `MarkdownRenderer::render(string $markdown): string`:
    - CommonMark + GFM con `html_input=escape` y `allow_unsafe_links=false`;
    - enlaces externos con `rel="nofollow ugc noopener noreferrer"`;
    - las menciones de usuarios existentes pasan a `<a class="mention" href="/u/{username}">`.
  - `MarkdownRenderer::excerpt(string $markdown, int $length = 200): string` (texto plano).

- [ ] Tests (Review Focus 1):
  - `<script>` escapado;
  - enlace `javascript:` sin `href`;
  - `<img onerror>` escapado;
  - mención inexistente como texto plano;
  - mención existente enlazada;
  - mención dentro de un bloque de código no enlazada;
  - tablas y listas de tareas renderizadas;
  - `excerpt` sin etiquetas y cortado por palabra.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(markdown): renderizado seguro con menciones`.

### Task 8: Autorización

**Files:**
- Create: `app/Policies/{Thread,Post,Report,Conversation,Category,Tag,User,ModerationAction}Policy.php`.
- Modify: `AppServiceProvider` (gates `access-admin`, `viewHorizon`, `viewApiDocs`).
- Test: `tests/Feature/Policies/*PolicyTest.php` (*datasets* por rol).

**Interfaces:**
- Produces:
  - `ThreadPolicy`: `view`, `create(User, Category)`, `update`, `delete`, `restore`, `reply`, `pin`, `lock`, `move`, `markSolution`, `subscribe`, `summarize`.
  - `PostPolicy`: `update`, `delete`, `restore`, `react`, `report`, `hide`, `approve`.
  - `ReportPolicy`: `viewAny`, `resolve`.
  - `ConversationPolicy`: `view`, `reply`, `leave`, `message(User $sender, User $recipient)`.
  - `UserPolicy`: `ban(User $actor, User $target)`, `changeRole`.
  - Reglas exactas: matriz de la especificación, §5.

- [ ] Tests: matriz completa (casos positivos y negativos, incluidos baneado, sin verificar, hilo cerrado, categoría bloqueada y contenido propio).
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(auth): políticas y gates del foro`.

### Task 9: Acciones de hilos y mensajes + eventos

**Files:**
- Create: `app/Actions/Forum/{CreateThread,UpdateThread,ReplyToThread,UpdatePost,DeletePost,DeleteThread,RestorePost,RestoreThread}.php` y `app/Events/{ThreadCreated,ThreadUpdated,PostCreated,PostUpdated,PostDeleted}.php`.
- Test: `tests/Feature/Actions/Forum/*Test.php`.

**Interfaces:**
- Produces:
  - `CreateThread::handle(User $author, Category $category, string $title, string $body, array $tagIds = []): Thread`;
  - `UpdateThread::handle(Thread $thread, User $editor, string $title, array $tagIds): Thread`;
  - `ReplyToThread::handle(Thread $thread, User $author, string $body, ?Post $replyTo = null): Post`;
  - `UpdatePost::handle(Post $post, User $editor, string $body): Post` (`PostUpdated` lleva `previousMentions`);
  - `DeletePost::handle(Post $post, User $actor, ?string $reason = null): void` (si es el primer post, borra el hilo);
  - `DeleteThread::handle(Thread $thread, User $actor, ?string $reason = null): void`;
  - `RestorePost::handle(Post $post, User $actor): void`;
  - `RestoreThread::handle(Thread $thread, User $actor): void`.
  - Los eventos se emiten tras el *commit* (`ShouldDispatchAfterCommit`).

- [ ] Tests:
  - contadores (categoría, hilo y usuario) al crear, responder, borrar y restaurar;
  - `first_post_id`, `last_post_id` y `last_posted_at` correctos;
  - autor suscrito y marcado como leído;
  - edición con `edited_at` y `edited_by_id`;
  - las acciones de *staff* registran la acción de moderación;
  - los eventos se emiten.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(forum): acciones de hilos y respuestas con eventos de dominio`.

### Task 10: Reacciones, solución, suscripciones, lecturas y vistas

**Files:**
- Create: `app/Actions/Forum/{ReactToPost,MarkSolution,UnmarkSolution}.php`, `app/Services/{ThreadSubscriptions,ReadTracker,ViewCounter}.php`, `app/Events/{ReactionAdded,SolutionMarked}.php`.
- Test: `tests/Feature/Actions/Forum/{ReactToPostTest,SolutionTest}.php`, `tests/Feature/Services/{ThreadSubscriptionsTest,ReadTrackerTest,ViewCounterTest}.php`.

**Interfaces:**
- Produces:
  - `ReactToPost`: `add(Post, User, ReactionType): bool`, `remove(Post, User, ReactionType): bool` (devuelven si hubo cambio) y `toggle(...): bool` (devuelve si queda activa);
  - `MarkSolution::handle(Thread $thread, Post $post, User $actor): void`, `UnmarkSolution::handle(Thread $thread, User $actor): void`;
  - `ThreadSubscriptions`: `subscribe(Thread, User): void`, `unsubscribe(Thread, User): void`, `isSubscribed(Thread, User): bool`;
  - `ReadTracker`: `markRead(Thread, User): void`, `lastReadAt(Thread, User): ?CarbonInterface`, `firstUnreadPost(Thread, User): ?Post`;
  - `Thread::scopeWithReadState(Builder, ?User)` añade `last_read_at`;
  - `ViewCounter::record(Thread, Request): bool` (deduplica por usuario o sesión durante 60 min).

- [ ] Tests (Review Focus 3):
  - `add` dos veces deja una sola reacción y +1 de reputación;
  - `remove` sin reacción devuelve `false` y no descuenta;
  - cambiar la solución traspasa los +15;
  - la vista repetida no incrementa.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(forum): reacciones, soluciones, suscripciones, lecturas y vistas`.

### Task 11: Moderación

**Files:**
- Create: `app/Actions/Moderation/{RecordModerationAction,PinThread,LockThread,MoveThread,HidePost,ApprovePost,ReportPost,ResolveReport,DismissReport,BanUser,UnbanUser,ChangeUserRole}.php` y `app/Events/{PostReported,PostModerated}.php`.
- Test: `tests/Feature/Actions/Moderation/*Test.php`.

**Interfaces:**
- Produces:
  - `RecordModerationAction::handle(?User $moderator, ModerationActionType $type, Model $subject, ?string $reason = null, array $meta = []): ModerationAction`;
  - estado de hilos:
    - `PinThread::handle(Thread, User $moderator, bool $pinned = true): void`;
    - `LockThread::handle(Thread, User $moderator, bool $locked = true): void`;
    - `MoveThread::handle(Thread, Category, User $moderator): void` (actualiza contadores de ambas categorías);
  - moderación de mensajes:
    - `HidePost::handle(Post, ?User $moderator, ?string $reason = null): void` (`null` = sistema/IA; si es el primer post, el hilo pasa a `pending_review`);
    - `ApprovePost::handle(Post, User $moderator): void`;
  - reportes:
    - `ReportPost::handle(Post, User $reporter, ReportReason, ?string $details = null): Report` (idempotente mientras haya uno abierto);
    - `ReportPost::forAi(Post $post, ReportReason $reason, string $details, float $confidence): Report` (`source=ai`, `reporter_id` nulo; idempotente);
    - `ResolveReport::handle(Report, User $moderator, bool $hidePost, ?string $note = null): void`;
    - `DismissReport::handle(Report, User $moderator, ?string $note = null): void`;
  - usuarios:
    - `BanUser::handle(User $target, User $moderator, string $reason, ?CarbonInterface $until = null): void` (revoca tokens);
    - `UnbanUser::handle(User $target, User $moderator): void`;
    - `ChangeUserRole::handle(User $target, User $admin, Role $role): void`.

- [ ] Tests:
  - cada acción registra `moderation_actions`;
  - ocultar el primer post deja el hilo pendiente;
  - un reporte duplicado devuelve el existente;
  - el baneo revoca tokens (Review Focus 4);
  - mover actualiza contadores.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(moderation): acciones de moderación con registro de auditoría`.

### Task 12: Mensajes privados (dominio)

**Files:**
- Create: `app/Actions/Messaging/{StartConversation,SendMessage,LeaveConversation,MarkConversationRead}.php`, `app/Events/MessageSent.php`.
- Test: `tests/Feature/Actions/Messaging/*Test.php`.

**Interfaces:**
- Produces:
  - `StartConversation::handle(User $sender, Collection $recipients, string $subject, string $body): Conversation`;
  - `SendMessage::handle(Conversation $conversation, User $sender, string $body): Message`;
  - `LeaveConversation::handle(Conversation $conversation, User $user): void`;
  - `MarkConversationRead::handle(Conversation $conversation, User $user): void`;
  - `User::unreadConversationsCount(): int`.

- [ ] Tests:
  - no se escribe a uno mismo ni a quien desactivó los privados;
  - `last_message_at` se actualiza;
  - abandonar oculta la conversación;
  - el contador de no leídas es correcto.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(messaging): conversaciones privadas`.

### Task 13: Notificaciones

**Files:**
- Create: `app/Notifications/{ThreadReplied,UserMentioned,SolutionAccepted,PostModerated,NewPrivateMessage}.php` y `app/Listeners/{NotifyThreadSubscribers,NotifyMentionedUsers,NotifySolutionAuthor,NotifyModeratedAuthor,NotifyConversationParticipants}.php`.
- Test: `tests/Feature/Notifications/*Test.php`.

**Interfaces:**
- Produces:
  - notificaciones `ShouldQueue` con `via()` = `['database']`, más `'mail'` si `$notifiable->wantsEmail(<tipo>)`;
  - `toArray()` con `{type, title, excerpt, url, actor: {username, avatar_url}}`.

- [ ] Tests:
  - los suscriptores (menos el autor) reciben `ThreadReplied`;
  - solo los usuarios nuevos mencionados al editar reciben `UserMentioned`;
  - el email se respeta según preferencias;
  - un post oculto o borrado no notifica (Review Focus 2).
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(notifications): notificaciones por base de datos y email`.

### Task 14: Búsqueda

**Files:**
- Modify: `Thread` y `Post` (`Searchable`, `toSearchableArray`, `shouldBeSearchable` solo si está publicado y no borrado), `config/scout.php` (`index-settings` de Meilisearch: filtrables `category_id`, `tag_ids`, `is_solved`; ordenables `last_posted_at`, `created_at`).
- Create: `app/Services/Search/ForumSearch.php`.
- Test: `tests/Feature/Search/ForumSearchTest.php` (driver `collection`).

**Interfaces:**
- Produces: `ForumSearch::threads(string $query, array $filters, int $perPage = 20): LengthAwarePaginator` y `ForumSearch::posts(string $query, array $filters, int $perPage = 20): LengthAwarePaginator` (filtros: `category`, `tag`).

- [ ] Tests:
  - resultados filtrados por categoría y etiqueta;
  - ocultos y borrados excluidos (Review Focus 2).
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(search): búsqueda con Scout y Meilisearch`.

### Task 15: Consola y *scheduler*

**Files:**
- Create: `app/Console/Commands/{RecountForum,MakeAdmin,PruneNotifications}.php`.
- Modify: `routes/console.php` (planificación).
- Test: `tests/Feature/Console/*Test.php`.

**Interfaces:**
- Produces:
  - `forum:recount`;
  - `forum:make-admin {email}`;
  - `forum:prune-notifications {--days=90}`;
  - planificación:
    - `horizon:snapshot` cada 5 min;
    - `sanctum:prune-expired --hours=24` y `forum:prune-notifications` diarios;
    - `forum:recount` semanal.

- [ ] Tests:
  - `recount` corrige contadores y reputación manipulados;
  - `make-admin` promociona o falla con email inexistente;
  - el `prune` borra las notificaciones leídas de más de 90 días;
  - `Schedule` contiene los eventos.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(console): comandos de mantenimiento y planificación`.

### Task 16: Shell Inertia, tipos y componentes base

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/layouts/*`, `resources/js/app.ts`.
- Create: `resources/js/types/forum.ts`, `resources/js/components/forum/{UserAvatar,RoleBadge,CategoryBadge,TagBadge,RelativeTime,Pagination,EmptyState,MarkdownContent,LocaleSwitcher,NotificationBell}.vue` y `resources/js/composables/{useRelativeTime,useDraft}.ts`.
- Test: `tests/Feature/Inertia/SharedPropsTest.php`, `tests/js/components/*.spec.ts`, `tests/js/composables/*.spec.ts`.

**Interfaces:**
- Produces (*props* compartidas):
  - `auth.user`: `{id, name, username, email, avatar_url, role, is_staff, is_banned, email_verified, unread_notifications, unread_conversations}` o `null`;
  - `locale` y `locales`;
  - `features.ai`: `{summaries, similar, tags}`;
  - `flash`: `{success?, error?, token?}`.
- Convención: las *props* de recurso único usan `->resolve()`; las paginadas, la colección de recurso (`data`, `links`, `meta`).
- Tipos TS: `UserSummary`, `Category`, `Tag`, `ThreadSummary`, `Thread`, `Post`, `ReactionSummary`, `Paginated<T>`, `ForumNotification`, `Conversation`, `Message`.

- [ ] Tests:
  - PHP: *props* de invitado frente a usuario y contadores de no leídos;
  - Vitest:
    - `RelativeTime` en es/en;
    - `Pagination` deshabilita los extremos;
    - `useDraft` persiste y limpia;
    - `NotificationBell` muestra el contador;
    - `LocaleSwitcher` hace POST.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(ui): layout del foro, tipos y componentes base`.

### Task 17: Portada y listados

**Files:**
- Create: `app/Http/Controllers/{Home,Thread,Category,Tag}Controller.php` (`index`/`show`), `app/Http/Requests/ThreadIndexRequest.php`, `app/Queries/ThreadListing.php`, `app/Services/ForumStats.php` y `app/Http/Resources/{UserResource,CategoryResource,TagResource,ThreadResource}.php`.
- Create (Vue): `resources/js/pages/{Home,threads/Index}.vue` y `components/forum/{ThreadList,ThreadListItem,CategoryCard}.vue`.
- Test: `tests/Feature/Web/{HomeTest,ThreadIndexTest,CategoryPageTest,TagPageTest}.php`, `tests/js/components/ThreadListItem.spec.ts`.

**Interfaces:**
- Produces:
  - `ThreadListing::paginate(array $filters, ?User $viewer): LengthAwarePaginator`:
    - `sort`: `activity` | `newest` | `replies` | `views`;
    - `filter`: `all` | `unanswered` | `solved` | `unsolved` | `following` | `mine`;
    - `category` y `tag` (slug);
  - `ForumStats::snapshot(): array{members:int, threads:int, posts:int, newest_member:?array, online:list<array>}` (caché de 300 s);
  - categorías y etiquetas renderizan `threads/Index` con `context`.

- [ ] Tests:
  - fijados primero en la categoría;
  - cada filtro y orden;
  - marca «nuevo» para el usuario;
  - estadísticas;
  - un hilo oculto no aparece para un miembro y sí para el *staff* (Review Focus 2).
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(web): portada y listados de hilos`.

### Task 18: Página de hilo e interacciones

**Files:**
- Create (controladores): `app/Http/Controllers/{PostController,PostReactionController,ThreadSolutionController,ThreadSubscriptionController,PostReportController,MarkdownPreviewController}.php` y `app/Http/Controllers/Moderation/{ThreadModerationController,PostModerationController}.php`.
- Create (requests y recursos): `app/Http/Requests/{StorePostRequest,UpdatePostRequest,ReactionRequest,StoreReportRequest,MoveThreadRequest}.php` y `app/Http/Resources/PostResource.php`.
- Create (Vue): `resources/js/pages/threads/Show.vue` y `components/forum/{PostCard,PostReactions,PostActions,ReplyForm,MarkdownEditor,ReportDialog,MoveThreadDialog,SubscriptionButton,ThreadModerationMenu,ThreadStatusBadges}.vue`.
- Test: `tests/Feature/Web/ThreadShowTest.php`, `tests/Feature/Web/Posts/*Test.php`, `tests/Feature/Web/Moderation/*Test.php`, `tests/js/components/{PostReactions,MarkdownEditor,ReplyForm,ReportDialog}.spec.ts`.

**Interfaces:**
- Produces:
  - `GET /t/{thread:slug}`:
    - *props*:
      - `thread`;
      - `posts` (paginado; `?unread=1` salta a la página del primer no leído);
      - `can` (permisos del visor);
      - `subscribed`;
      - *deferred* `similar` y `summary`;
    - registra vista y lectura.
  - `POST /markdown/preview` → `{html}`, con autenticación y *throttle*.
  - *Rate limiters* con nombre, registrados en `AppServiceProvider` desde `config('forum.rate_limits')` y compartidos por web y API (clave: usuario):
    - `forum-threads` 3/min y `forum-replies` 6/min;
    - `forum-reactions` 60/min, `forum-reports` 10/h y `forum-messages` 10/min.
  - Rutas con nombre:
    - mensajes: `posts.store`, `posts.update`, `posts.destroy`;
    - interacciones: `posts.reactions.toggle`, `posts.reports.store`, `threads.subscription.store`, `threads.subscription.destroy`, `threads.solution.store`, `threads.solution.destroy`;
    - moderación de hilos: `moderation.threads.{pin,unpin,lock,unlock,move,restore}`;
    - moderación de mensajes: `moderation.posts.{hide,approve,restore}`.

- [ ] Tests:
  - cada ruta: invitado → login; sin permiso → 403; validación → errores; éxito → *redirect* con `flash.success`;
  - la vista no se duplica;
  - el `<title>` y la meta descripción están presentes.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(web): página de hilo con respuestas, reacciones y moderación`.

### Task 19: Crear y editar hilos

**Files:**
- Modify: `ThreadController` (`create`, `store`, `update`, `destroy`).
- Create: `app/Http/Requests/{StoreThreadRequest,UpdateThreadRequest}.php`, `resources/js/pages/threads/Create.vue`, `components/forum/{TagSelect,SimilarThreadsHint}.vue`.
- Test: `tests/Feature/Web/ThreadCreateTest.php`, `tests/js/components/TagSelect.spec.ts`.

**Interfaces:**
- Produces:
  - `create`:
    - *props* `categories` (solo las permitidas), `tags` y `category` (preseleccionada);
    - `similar` (solo si `?title=` tiene ≥ 10 caracteres; recarga parcial `only: ['similar']`);
  - validación:
    - `title`: 8–150;
    - `body`: 10–30 000;
    - `tags`: ≤ 5 ids existentes.

- [ ] Tests:
  - categoría bloqueada → 403 para miembros;
  - etiquetas > 5 → error;
  - el título recibe *trim*;
  - `store` redirige al hilo;
  - el borrado solo es posible sin respuestas (autor).
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(web): creación y edición de hilos`.

### Task 20: Perfil, búsqueda y notificaciones

**Files:**
- Create: `app/Http/Controllers/{UserProfileController,SearchController,NotificationController}.php`, `resources/js/pages/{users/Show,search/Index,notifications/Index}.vue` y `components/forum/NotificationItem.vue`.
- Test: `tests/Feature/Web/{UserProfileTest,SearchPageTest,NotificationsPageTest}.php`.

**Interfaces:**
- Produces:
  - `/u/{user:username}?tab=threads|posts`;
  - `/search?q=&type=threads|posts&category=&tag=`;
  - notificaciones: `notifications.index`, `notifications.read` (POST `{id}`), `notifications.read-all`.

- [ ] Tests:
  - el perfil de un usuario borrado da 404;
  - las pestañas solo muestran contenido visible (Review Focus 2);
  - la búsqueda vacía no consulta;
  - marcar una notificación ajena da 404.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(web): perfiles, búsqueda y notificaciones`.

### Task 21: Mensajes privados (web)

**Files:**
- Create: `app/Http/Controllers/{ConversationController,ConversationMessageController}.php`, `app/Http/Requests/{StoreConversationRequest,StoreMessageRequest}.php`, `app/Http/Resources/{ConversationResource,MessageResource}.php` y `resources/js/pages/conversations/{Index,Show,Create}.vue`.
- Test: `tests/Feature/Web/ConversationsTest.php`.

- [ ] Tests:
  - solo los participantes ven la conversación (404 a los demás);
  - `?to=username` precarga el destinatario;
  - responder actualiza las marcas de lectura;
  - abandonar la conversación.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(web): mensajes privados`.

### Task 22: Ajustes y borrado de cuenta

**Files:**
- Modify: `Settings/ProfileController`, `ProfileUpdateRequest` (añadir `username`, `bio` ≤ 500, `location` ≤ 80, `website` URL, `locale`, `avatar` imagen ≤ 2048 KB, `remove_avatar`) y `pages/settings/Profile.vue`.
- Create:
  - acciones: `app/Actions/Users/{UpdateProfile,DeleteAccount}.php`;
  - controladores: `app/Http/Controllers/Settings/{NotificationPreferencesController,ApiTokenController}.php`;
  - páginas: `pages/settings/{Notifications,ApiTokens}.vue`.
- Test: `tests/Feature/Settings/{ProfileUpdateTest,AccountDeletionTest,NotificationPreferencesTest,ApiTokensTest}.php`.

**Interfaces:**
- Produces:
  - `UpdateProfile::handle(User $user, array $data, ?UploadedFile $avatar = null, bool $removeAvatar = false): User` (avatar con `Image::fromUpload()->cover(256,256)->toWebp()`);
  - `DeleteAccount::handle(User $user): void`:
    - anonimiza: `name` vacío, `username` `deleted-{id}`, `email` `deleted-{id}@deleted.invalid`, avatar, 2FA, tokens, *passkeys*, suscripciones y lecturas;
    - hace *soft delete* y conserva los posts;
  - tokens: *abilities* permitidas `read`, `write` y `messages`, más `moderate` solo para el *staff*; el token en claro se devuelve una vez en `flash.token`.

- [ ] Tests:
  - el avatar se guarda en WebP de 256×256;
  - el *username* es único;
  - el email cambiado se desverifica;
  - el borrado anonimiza y los posts muestran «Usuario eliminado» (Review Focus 4);
  - un miembro no puede pedir `moderate`.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(settings): perfil, preferencias, tokens de API y anonimización de cuenta`.

### Task 23: SSR, SEO y páginas de error

**Files:**
- Create: `resources/js/ssr.ts`, `app/Http/Controllers/{SitemapController,RobotsController}.php` y `resources/js/pages/Error.vue`.
- Modify: `vite.config.ts` (`ssr.noExternal: true`, comando de Wayfinder vía `WAYFINDER_COMMAND`), `config/inertia.php` (`INERTIA_SSR_URL`), `bootstrap/app.php` (Inertia `Error` para 403/404/419/429/500/503 con `APP_DEBUG=false`) y `public/robots.txt` (eliminado: pasa a ser dinámico).
- Test: `tests/Feature/Web/{SitemapTest,RobotsTest,ErrorPagesTest}.php`.

**Interfaces:**
- Produces:
  - `GET /sitemap.xml`: categorías y hasta 5000 hilos visibles; caché de 1 h;
  - `GET /robots.txt`: incluye `Sitemap: {APP_URL}/sitemap.xml`.

- [ ] Tests:
  - el sitemap excluye ocultos y borrados (Review Focus 2);
  - robots con la URL absoluta;
  - un 404 sin *debug* renderiza el componente `Error` con `status`.
- [ ] Implementar; `npm run build:ssr` genera `bootstrap/ssr/ssr.js` y `node bootstrap/ssr/ssr.js` arranca.
- [ ] Commit `feat(web): SSR, sitemap, robots y páginas de error`.

### Task 24: API: autenticación, *abilities* y recursos

**Files:**
- Create: `routes/api.php` (prefijo `v1` y nombres `api.v1.*`), `app/Http/Controllers/Api/V1/{AuthTokenController,MeController}.php`, `app/Http/Requests/Api/IssueTokenRequest.php` y los recursos restantes (`NotificationResource`, `ReportResource`).
- Modify: `AppServiceProvider` (*rate limiter* `api`).
- Test: `tests/Feature/Api/V1/{AuthTokenTest,MeTest,RateLimitTest}.php`.

**Interfaces:**
- Produces:
  - `POST /api/v1/auth/token`:
    - entrada `{email|username, password, device_name, abilities?, code?, recovery_code?}`;
    - respuesta `201 {token, abilities, user}`;
    - error `422 two_factor_required` si el usuario tiene 2FA y no envía código;
  - `DELETE /api/v1/auth/token` → 204;
  - `GET`/`PATCH /api/v1/me`.

- [ ] Tests:
  - credenciales inválidas → 422;
  - 2FA con código válido, inválido y de recuperación;
  - *ability* ausente → 403;
  - usuario baneado → 403 al emitir (Review Focus 4);
  - las cabeceras `X-RateLimit-*` respetan los límites 120 y 60.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(api): emisión de tokens con 2FA, abilities y rate limiting`.

### Task 25: API del foro

**Files:**
- Create: `app/Http/Controllers/Api/V1/{CategoryController,TagController,ThreadController,ThreadPostController,PostController,PostReactionController,ThreadSolutionController,ThreadSubscriptionController,PostReportController,UserController,SearchController}.php`.
- Test: `tests/Feature/Api/V1/*Test.php`.

**Interfaces:**
- Consumes: acciones de las tareas 9 a 14, `ThreadListing` y `ForumSearch`.
- Produces:
  - reacciones: `PUT` y `DELETE /posts/{post}/reactions/{type}` (idempotentes, 200 con el resumen de reacciones);
  - solución: `PUT /threads/{thread}/solution` (`{post_id}`) y `DELETE`;
  - suscripción: `PUT` y `DELETE /threads/{thread}/subscription`.

- [ ] Tests (por endpoint):
  - sin token → 401 en escrituras;
  - lectura pública;
  - validación 422;
  - política 403;
  - doble `PUT` idempotente (Review Focus 3);
  - contenido oculto → 404 para miembros (Review Focus 2);
  - email solo en `/me`.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(api): endpoints de hilos, mensajes, reacciones, suscripciones, perfiles y búsqueda`.

### Task 26: API de notificaciones, conversaciones y moderación + OpenAPI

**Files:**
- Create: `app/Http/Controllers/Api/V1/{NotificationController,ConversationController,ConversationMessageController}.php` y `Api/V1/Moderation/{ThreadModerationController,PostModerationController}.php`.
- Modify: `config/scramble.php`.
- Test: `tests/Feature/Api/V1/{NotificationsTest,ConversationsTest,ModerationTest,ApiDocsTest}.php`.

- [ ] Tests:
  - la moderación exige rol de *staff* y la *ability* `moderate`;
  - las conversaciones exigen la *ability* `messages`;
  - `GET /docs/api.json` devuelve 200 con `paths` que incluyen `/auth/token`.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(api): notificaciones, conversaciones, moderación y documentación OpenAPI`.

### Task 27: IA: núcleo y moderación automática

**Files:**
- Create: `app/Ai/ForumAi.php`, `app/Ai/Agents/ContentModerator.php`, `app/Ai/Data/ModerationResult.php`, `app/Actions/Ai/ApplyModerationVerdict.php`, `app/Jobs/Ai/ModeratePost.php` y `app/Listeners/QueueAiModeration.php`.
- Test: `tests/Feature/Ai/{ForumAiTest,ModeratePostTest}.php`.

**Interfaces:**
- Produces:
  - `ForumAi`: `enabled(string $feature): bool`, `textProvider(): string`, `textModel(string $feature): string`;
  - `ContentModerator`:
    - esquema `{verdict: allow|review|block, categories: string[], confidence: number 0..1, reason: string}`;
    - `#[MaxTokens(1024)]`;
  - `ModerationResult::fromArray(array): ?self` (`null` si la salida es inválida);
  - `ApplyModerationVerdict::handle(Post, ModerationResult): void`;
  - `ModeratePost`: 3 intentos, *backoff* `[10, 60, 300]`.

- [ ] Tests (Review Focus 5):
  - `block` ≥ 0,8 oculta, crea un reporte IA y notifica al autor;
  - `block` < 0,8 y `review` solo crean el reporte;
  - `allow` no hace nada;
  - un veredicto desconocido no actúa y deja log;
  - una excepción del proveedor no altera el post;
  - con la IA desactivada, `assertNeverPrompted`.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(ai): moderación automática con laravel/ai`.

### Task 28: IA: resúmenes de hilos

**Files:**
- Create: `app/Ai/Agents/ThreadSummarizer.php`, `app/Actions/Ai/SummarizeThread.php`, `app/Jobs/Ai/GenerateThreadSummary.php`, `app/Http/Controllers/ThreadSummaryController.php`, `Api/V1/ThreadSummaryController.php` y `components/forum/AiSummaryCard.vue`.
- Test: `tests/Feature/Ai/SummaryTest.php`, `tests/js/components/AiSummaryCard.spec.ts`.

**Interfaces:**
- Produces:
  - `SummarizeThread::handle(Thread $thread): ?string` (`null` si la salida está vacía);
  - `Thread::canHaveSummary(): bool` y `Thread::summaryIsStale(): bool`;
  - `POST /t/{thread}/summary` (web);
  - `GET /api/v1/threads/{thread}/summary`:
    - 200 `{summary_html, generated_at, stale}`;
    - 202 si queda en cola;
    - 404 si no aplica o la IA está desactivada.

- [ ] Tests:
  - menos de 5 respuestas → 404 y el botón no aparece;
  - la regeneración solo si está desactualizado;
  - el HTML del resumen pasa por `MarkdownRenderer` (XSS);
  - un resumen vacío no se guarda.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(ai): resúmenes TL;DR de hilos`.

### Task 29: IA: hilos similares y sugerencia de etiquetas

**Files:**
- Create: `app/Services/Ai/{SimilarThreadFinder,VectorSimilarThreadFinder,KeywordSimilarThreadFinder}.php`, `app/Jobs/Ai/GenerateThreadEmbedding.php`, `app/Listeners/QueueThreadEmbedding.php`, `app/Console/Commands/EmbedThreads.php` y `app/Ai/Agents/TagSuggester.php`.
- Create: `app/Actions/Ai/SuggestTags.php`, `app/Http/Controllers/TagSuggestionController.php`, `Api/V1/SimilarThreadController.php` y `components/forum/SimilarThreads.vue`.
- Test: `tests/Feature/Ai/{SimilarThreadsTest,VectorSimilarThreadsTest(pgsql),EmbedThreadsCommandTest,TagSuggestionsTest}.php`.

**Interfaces:**
- Produces:
  - `SimilarThreadFinder`:
    - `similarTo(Thread $thread, int $limit = 5): Collection`;
    - `forText(string $text, int $limit = 5, ?int $exceptThreadId = null): Collection`;
    - se enlaza a la implementación vectorial si la conexión es `pgsql` y la funcionalidad está activa; si no, a la de palabras clave;
  - `forum:embed-threads {--force}`;
  - `SuggestTags::handle(string $title, string $body): Collection` (solo etiquetas existentes, ≤ 3);
  - `POST /ai/tag-suggestions` → `{tags}`.

- [ ] Tests:
  - el *embedding* no se regenera si el hash no cambia;
  - los similares excluyen el propio hilo y los ocultos (Review Focus 2);
  - las etiquetas inexistentes devueltas por el agente se descartan (Review Focus 5);
  - con la IA desactivada, 404.
- [ ] Implementar; ejecutar en SQLite y en PostgreSQL.
- [ ] Commit `feat(ai): hilos similares con pgvector y sugerencia de etiquetas`.

### Task 30: Panel de administración (Filament 5)

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php`, `app/Filament/Resources/{Categories,Tags,Users,Threads,Posts,Reports,ModerationActions}/*` y `app/Filament/Widgets/{ForumStatsOverview,ActivityChart}.php`.
- Modify: `User` (`FilamentUser::canAccessPanel` = `isStaff()`, `HasAvatar`) y `HorizonServiceProvider` (gate `viewHorizon` = admin).
- Test: `tests/Feature/Admin/*Test.php`.

**Interfaces:**
- Consumes: acciones de moderación (tarea 11) y de foro (tarea 9). Las acciones de Filament las invocan; nunca escriben directamente.

- [ ] Tests:
  - invitado → login;
  - miembro → 403;
  - un moderador no ve categorías ni etiquetas ni cambia roles;
  - un administrador crea una categoría;
  - acciones banear, resolver reporte y aprobar post;
  - `/horizon` solo para administradores.
- [ ] Implementar y dejar en verde.
- [ ] Commit `feat(admin): panel de administración y moderación con Filament`.

### Task 31: Puertas de calidad

**Files:**
- Create: `tests/Arch/ArchTest.php`.
- Modify: `phpstan.neon` (nivel 7, rutas `app`, `config`, `database`, `routes`), `vitest.config.ts` (umbrales).

- [ ] Arch:
  - `preset()->laravel()` y `preset()->security()`;
  - sin `dd`/`dump`/`ray`;
  - `App` con `strict_types`;
  - `App\Actions` final con método `handle`.
- [ ] Ejecutar Pint, PHPStan, ESLint, Prettier, vue-tsc y la suite con cobertura en SQLite y PostgreSQL; completar tests hasta ≥ 95 % (PHP) y ≥ 90 % (TS).
- [ ] Commit `test: arquitectura, análisis estático y umbrales de cobertura`.

### Task 32: Imágenes Docker

**Files:**
- Create: `Dockerfile` (*targets* `base`, `dev`, `vendor`, `assets`, `app`, `web`, `ssr`), `.dockerignore` y `docker/{php,nginx,entrypoint}/*`.

**Interfaces:**
- Produces:
  - *healthchecks*:
    - `app`: `php-fpm-healthcheck` (ping FastCGI `/ping`);
    - `web`: `wget -qO- http://127.0.0.1/up`;
    - `ssr`: `wget -qO- http://127.0.0.1:13714/health`;
  - *entrypoint* de `app` con `php artisan optimize` y el enlace `storage:link`.

- [ ] Verificar con hadolint y construir las *targets* que permita el entorno (`ssr`, `web` con los assets compilados).
- [ ] Commit `build(docker): Dockerfile multi-stage para app, web, ssr y dev`.

### Task 33: Compose local y Makefile

**Files:**
- Create: `compose.yaml`, `Makefile` y `docker/postgres/init/01-init.sql` (extensión `vector` y base de datos `forum_testing`).
- Modify: `.env.example`.

- [ ] Verificar: `docker compose config -q`; levantar `postgres`, `redis`, `meilisearch` y `mailpit`, y ejecutar migraciones y la suite contra ellos.
- [ ] Commit `build(docker): entorno local con Docker Compose y Makefile`.

### Task 34: Compose de producción

**Files:**
- Create: `compose.prod.yaml`, `docker/caddy/Caddyfile` y `.env.production.example`.

- [ ] Verificar `docker compose -f compose.prod.yaml config -q` (redes `edge`, `app` y `data` interna; servicio `migrate` *one-shot*; límites de recursos).
- [ ] Commit `build(docker): stack de producción con Caddy, Horizon y migraciones one-shot`.

### Task 35: CI/CD

**Files:**
- Create: `.github/workflows/{ci,release}.yml` y `.github/dependabot.yml`.
- Modify: `.github/workflows/audit.yml` y `.github/npm-audit-allowlist.json` (sin excepciones).

- [ ] Fijar las acciones por SHA (`git ls-remote`); validar con `actionlint`.
- [ ] Commit `ci: lint, tests con cobertura, build, smoke test Docker y publicación en GHCR`.

### Task 36: Documentación

**Files:**
- Create: `README.md` y `docs/{03-desarrollo-local,04-despliegue,05-api,06-ia,07-testing-y-ci,08-seguridad}.md`.

- [ ] Cada comando documentado se ha ejecutado al menos una vez en este plan.
- [ ] Commit `docs: README y guías de desarrollo, despliegue, API, IA, testing y seguridad`.

### Task 37: Verificación final y entrega

- [ ] Suite completa en SQLite y PostgreSQL con cobertura; Vitest con cobertura; Pint, PHPStan, ESLint, vue-tsc, `npm run build` y `npm run build:ssr`.
- [ ] Revisión independiente de toda la rama; corregir los hallazgos.
- [ ] `git push -u origin claude/dazzling-mccarthy-gtlluw`.
