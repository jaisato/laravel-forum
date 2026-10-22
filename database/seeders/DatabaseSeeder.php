<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Forum\CreateThread;
use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\ReactToPost;
use App\Actions\Forum\ReplyToThread;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Actions\Moderation\BanUser;
use App\Actions\Moderation\LockThread;
use App\Actions\Moderation\PinThread;
use App\Actions\Moderation\ReportPost;
use App\Enums\ReactionType;
use App\Enums\ReportReason;
use App\Events\MessageSent;
use App\Events\PostCreated;
use App\Events\PostModerated;
use App\Events\PostReported;
use App\Events\PostUpdated;
use App\Events\ReactionAdded;
use App\Events\SolutionMarked;
use App\Events\ThreadCreated;
use App\Events\ThreadUpdated;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * A demo forum for local development: staff and members, categories, tags,
 * threads with replies, reactions, solutions, a private conversation and a
 * report in the moderation queue.
 *
 * Everything is written through the forum actions, so counters and
 * reputation are exactly what forum:recount computes. Domain events are
 * faked meanwhile: seeding sends no notifications or emails. Content is
 * dated over the last four weeks. Meant for an empty database
 * (`php artisan migrate:fresh --seed`).
 */
final class DatabaseSeeder extends Seeder
{
    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<string, Tag> */
    private array $tags = [];

    private CarbonImmutable $clock;

    private CarbonImmutable $realNow;

    public function run(): void
    {
        $this->realNow = CarbonImmutable::now();
        $this->clock = $this->realNow->subDays(28)->setTime(9, 0);

        Event::fakeFor(function (): void {
            try {
                $this->seedUsers();
                $this->seedCategoriesAndTags();
                $this->seedDemoThreads();
                $this->seedFillerThreads();
                $this->seedConversationAndModeration();
            } finally {
                Carbon::setTestNow();
                CarbonImmutable::setTestNow();
            }
        }, [
            ThreadCreated::class,
            ThreadUpdated::class,
            PostCreated::class,
            PostUpdated::class,
            ReactionAdded::class,
            SolutionMarked::class,
            PostModerated::class,
            PostReported::class,
            MessageSent::class,
        ]);

        // Views are not activity: they only make the listings look lived in.
        Thread::query()->pluck('id')->each(fn (int $id) => DB::table('threads')
            ->where('id', $id)
            ->update(['views_count' => fake()->numberBetween(12, 900)]));
    }

    private function seedUsers(): void
    {
        $this->users['admin'] = User::factory()->admin()->create([
            'name' => 'Administración',
            'username' => 'admin',
            'email' => config('forum.seed.admin_email'),
            'password' => config('forum.seed.admin_password'),
        ]);

        $this->users['moderador'] = User::factory()->moderator()->create([
            'name' => 'Equipo de moderación',
            'username' => 'moderador',
            'email' => 'moderador@example.com',
        ]);

        $members = [
            'ana' => 'Ana García',
            'luis' => 'Luis Martín',
            'marta' => 'Marta Ruiz',
            'javier' => 'Javier López',
            'lucia' => 'Lucía Fernández',
            'carlos' => 'Carlos Sánchez',
            'elena' => 'Elena Díaz',
            'pablo' => 'Pablo Moreno',
            'sofia' => 'Sofía Romero',
            'diego' => 'Diego Navarro',
        ];

        foreach ($members as $username => $name) {
            $this->users[$username] = User::factory()->create([
                'name' => $name,
                'username' => $username,
                'email' => "{$username}@example.com",
                'bio' => fake()->realText(120),
                'location' => fake()->city(),
            ]);
        }

        // Sofía does not want private messages; it shows in her profile.
        $this->users['sofia']->forceFill(['preferences' => ['allow_private_messages' => false]])->save();

        $this->users['spammer'] = User::factory()->create([
            'name' => 'Ofertas Increíbles',
            'username' => 'ofertas',
            'email' => 'ofertas@example.com',
        ]);
    }

    private function seedCategoriesAndTags(): void
    {
        $categories = [
            'anuncios' => ['Anuncios', 'Novedades y normas del foro. Solo el equipo abre hilos.', '#ef4444', 'megaphone', true],
            'general' => ['General', 'Presentaciones y conversación sobre desarrollo web en general.', '#6366f1', 'messages-square', false],
            'laravel' => ['Laravel y PHP', 'Eloquent, colas, testing, rendimiento y todo el ecosistema.', '#f97316', 'code-xml', false],
            'frontend' => ['Vue e Inertia', 'Componentes, TypeScript, SSR y experiencia de usuario.', '#22c55e', 'layout-template', false],
            'devops' => ['DevOps y Docker', 'Contenedores, despliegues, bases de datos y observabilidad.', '#0ea5e9', 'container', false],
            'ia' => ['Inteligencia artificial', 'Agentes, embeddings y uso responsable de modelos de lenguaje.', '#a855f7', 'sparkles', false],
        ];

        $position = 0;

        foreach ($categories as $key => [$name, $description, $color, $icon, $locked]) {
            $this->categories[$key] = Category::query()->create([
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'icon' => $icon,
                'position' => $position++,
                'is_locked' => $locked,
            ]);
        }

        $tags = [
            'php' => '#777bb4', 'laravel' => '#ff2d20', 'vue' => '#42b883', 'inertia' => '#9553e9',
            'typescript' => '#3178c6', 'docker' => '#2496ed', 'postgresql' => '#336791',
            'testing' => '#16a34a', 'rendimiento' => '#f59e0b', 'seguridad' => '#dc2626', 'ia' => '#a855f7',
        ];

        foreach ($tags as $name => $color) {
            $this->tags[$name] = Tag::query()->create(['name' => $name, 'color' => $color]);
        }
    }

    private function seedDemoThreads(): void
    {
        foreach ($this->demoThreads() as $demo) {
            $this->advance(fake()->numberBetween(4, 30));

            $thread = app(CreateThread::class)->handle(
                $this->users[$demo['author']],
                $this->categories[$demo['category']],
                $demo['title'],
                $demo['body'],
                array_map(fn (string $tag): int => $this->tags[$tag]->id, $demo['tags']),
            );

            foreach ($demo['replies'] ?? [] as $reply) {
                $this->advance(fake()->numberBetween(1, 9));

                $post = app(ReplyToThread::class)->handle($thread, $this->users[$reply['author']], $reply['body']);

                foreach ($reply['reactions'] ?? [] as $username => $type) {
                    app(ReactToPost::class)->add($post, $this->users[$username], ReactionType::from($type));
                }

                if ($reply['solution'] ?? false) {
                    app(MarkSolution::class)->handle($thread, $post, $thread->author);
                }
            }

            foreach ($demo['reactions'] ?? [] as $username => $type) {
                app(ReactToPost::class)->add($thread->firstPost, $this->users[$username], ReactionType::from($type));
            }

            if ($demo['pinned'] ?? false) {
                app(PinThread::class)->handle($thread, $this->users['admin']);
            }

            if ($demo['locked'] ?? false) {
                app(LockThread::class)->handle($thread, $this->users['admin']);
            }
        }
    }

    /**
     * Enough extra threads to page through listings.
     */
    private function seedFillerThreads(): void
    {
        $topics = ['Horizon', 'Meilisearch', 'Filament', 'Pest', 'Vite', 'Tailwind', 'Sanctum', 'Scout', 'Reverb', 'pgvector', 'Docker Compose', 'Caddy', 'Redis', 'Wayfinder', 'Fortify', 'Octane'];
        $openings = ['Duda con %s en producción', '¿Alguien usa %s en un proyecto grande?', 'Problema al configurar %s', 'Buenas prácticas con %s', 'Experiencia migrando a %s', '%s: ¿merece la pena?'];
        $members = ['ana', 'luis', 'marta', 'javier', 'lucia', 'carlos', 'elena', 'pablo', 'diego'];
        $categories = ['general', 'laravel', 'frontend', 'devops'];

        foreach ($topics as $topic) {
            $this->advance(fake()->numberBetween(2, 14));

            $thread = app(CreateThread::class)->handle(
                $this->users[fake()->randomElement($members)],
                $this->categories[fake()->randomElement($categories)],
                sprintf(fake()->randomElement($openings), $topic),
                "Estoy trabajando con **{$topic}** y me surge una duda.\n\n".fake()->realText(280),
                [$this->tags[fake()->randomElement(array_keys($this->tags))]->id],
            );

            for ($replies = fake()->numberBetween(0, 4); $replies > 0; $replies--) {
                $this->advance(fake()->numberBetween(1, 6));

                $post = app(ReplyToThread::class)->handle($thread, $this->users[fake()->randomElement($members)], fake()->realText(180));

                if ($post->user_id !== $thread->user_id && fake()->boolean(60)) {
                    app(ReactToPost::class)->add($post, $thread->author, fake()->randomElement(ReactionType::cases()));
                }
            }
        }
    }

    private function seedConversationAndModeration(): void
    {
        $this->advance(3);

        $conversation = app(StartConversation::class)->handle(
            $this->users['ana'],
            collect([$this->users['luis']]),
            'Charla sobre la meetup de Laravel',
            '¡Hola @luis! ¿Te animas a dar una charla corta sobre **colas con Horizon** en la próxima meetup?',
        );

        $this->advance(2);
        app(SendMessage::class)->handle($conversation, $this->users['luis'], '¡Claro! Preparo algo de 15 minutos con una demo en Docker.');

        // A spam reply in the queue, reported by a member, and its author banned.
        $this->advance(1);
        $thread = Thread::query()->where('category_id', $this->categories['general']->id)->latest('id')->firstOrFail();
        $spam = app(ReplyToThread::class)->handle($thread, $this->users['spammer'], '¡Gana dinero desde casa! Visita https://ofertas.example y apúntate ya.');
        app(ReportPost::class)->handle($spam, $this->users['marta'], ReportReason::Spam, 'Enlace publicitario en una respuesta sin relación.');
        app(BanUser::class)->handle($this->users['spammer'], $this->users['moderador'], 'Spam publicitario.', $this->realNow->addDays(7));
    }

    /**
     * Moves the clock forward, so seeded content is spread over time.
     */
    private function advance(int $hours): void
    {
        $this->clock = $this->clock->addHours($hours)->addMinutes(fake()->numberBetween(0, 59));

        if ($this->clock->greaterThan($this->realNow)) {
            $this->clock = $this->realNow->subMinutes(5);
        }

        Carbon::setTestNow($this->clock);
        CarbonImmutable::setTestNow($this->clock);
    }

    /**
     * @return list<array{category: string, author: string, title: string, body: string, tags: list<string>, pinned?: bool, locked?: bool, reactions?: array<string, string>, replies?: list<array{author: string, body: string, reactions?: array<string, string>, solution?: bool}>}>
     */
    private function demoThreads(): array
    {
        return [
            [
                'category' => 'anuncios',
                'author' => 'admin',
                'title' => 'Te damos la bienvenida: normas y primeros pasos',
                'tags' => [],
                'pinned' => true,
                'locked' => true,
                'body' => <<<'MD'
                    ¡Hola y bienvenida al foro! Este es un espacio para aprender y ayudarnos con **Laravel, Vue, Docker e IA**.

                    ## Normas básicas

                    1. Trata a los demás con respeto: aquí todo el mundo empezó alguna vez.
                    2. Busca antes de preguntar; al escribir el título verás hilos parecidos.
                    3. Incluye el código mínimo que reproduce tu problema, en un bloque de código:

                    ```php
                    Route::get('/hola', fn () => 'mundo');
                    ```

                    4. Cuando una respuesta resuelva tu duda, **márcala como solución**.

                    Si ves algo que no encaja, usa el botón *Reportar* y el equipo de moderación lo revisará.
                    MD,
                'reactions' => ['ana' => 'celebrate', 'luis' => 'like', 'marta' => 'love'],
            ],
            [
                'category' => 'laravel',
                'author' => 'luis',
                'title' => '¿Cómo evito consultas N+1 al listar hilos con su autor?',
                'tags' => ['laravel', 'rendimiento'],
                'body' => <<<'MD'
                    En el listado de hilos muestro el nombre del autor y la categoría, y Debugbar me dice que hago **una consulta por fila**:

                    ```php
                    $threads = Thread::latest()->paginate(20);

                    foreach ($threads as $thread) {
                        echo $thread->author->name;
                    }
                    ```

                    ¿Cuál es la forma correcta de hacerlo?
                    MD,
                'replies' => [
                    [
                        'author' => 'ana',
                        'body' => <<<'MD'
                            Es el clásico N+1: carga las relaciones por adelantado con `with()`:

                            ```php
                            $threads = Thread::with(['author', 'category'])->latest()->paginate(20);
                            ```

                            Y en desarrollo activa `Model::preventLazyLoading()` para que te avise con una excepción cada vez que se te escape uno.
                            MD,
                        'reactions' => ['luis' => 'insightful', 'marta' => 'like', 'javier' => 'like'],
                        'solution' => true,
                    ],
                    ['author' => 'luis', 'body' => '¡Gracias @ana! Con eso paso de 41 consultas a 3. Lo de `preventLazyLoading` no lo conocía.'],
                    ['author' => 'javier', 'body' => 'Un truco extra: si solo necesitas contar, `withCount(\'posts\')` también evita el N+1.'],
                ],
            ],
            [
                'category' => 'devops',
                'author' => 'marta',
                'title' => 'Healthchecks en Docker Compose para PostgreSQL y Redis',
                'tags' => ['docker', 'postgresql'],
                'body' => <<<'MD'
                    Mi contenedor de la app arranca antes de que PostgreSQL acepte conexiones y las migraciones fallan. ¿Cómo lo resolvéis vosotros?
                    MD,
                'replies' => [
                    [
                        'author' => 'pablo',
                        'body' => <<<'MD'
                            Usa `healthcheck` en el servicio de la base de datos y `depends_on` con condición:

                            ```yaml
                            services:
                              postgres:
                                healthcheck:
                                  test: ["CMD-SHELL", "pg_isready -U forum"]
                                  interval: 5s
                                  retries: 10
                              app:
                                depends_on:
                                  postgres:
                                    condition: service_healthy
                            ```
                            MD,
                        'reactions' => ['marta' => 'insightful', 'diego' => 'like'],
                        'solution' => true,
                    ],
                    ['author' => 'diego', 'body' => 'Para Redis lo mismo con `redis-cli ping`. Y no olvides un `start_period` si tu imagen tarda en arrancar.'],
                ],
            ],
            [
                'category' => 'frontend',
                'author' => 'lucia',
                'title' => 'SSR con Inertia 3: ¿merece la pena en un foro?',
                'tags' => ['vue', 'inertia'],
                'body' => <<<'MD'
                    Estoy dudando si activar el renderizado en servidor. El foro es público y quiero que Google indexe los hilos. ¿Qué coste tiene mantener el proceso de Node?
                    MD,
                'replies' => [
                    ['author' => 'carlos', 'body' => 'Para contenido público sí: el HTML llega con título y descripción y la primera pintura es más rápida. El coste es un contenedor más con `node bootstrap/ssr/ssr.js`.', 'reactions' => ['lucia' => 'like']],
                    ['author' => 'elena', 'body' => 'Ojo con el código que toca `window` o `localStorage` al montar los componentes: en SSR no existen. Muévelo a `onMounted`.', 'reactions' => ['lucia' => 'insightful', 'carlos' => 'like']],
                ],
            ],
            [
                'category' => 'ia',
                'author' => 'javier',
                'title' => 'Detectar hilos duplicados con embeddings y pgvector',
                'tags' => ['ia', 'postgresql'],
                'body' => <<<'MD'
                    Quiero sugerir hilos parecidos mientras alguien escribe el título. ¿Alguien ha usado `whereVectorSimilarTo` de Laravel con pgvector?
                    MD,
                'replies' => [
                    [
                        'author' => 'elena',
                        'body' => <<<'MD'
                            Sí. Guardo el embedding del título y el inicio del primer mensaje en una columna `vector(1536)` con índice HNSW, y luego:

                            ```php
                            ThreadEmbedding::query()
                                ->whereVectorSimilarTo('embedding', $vector, minSimilarity: 0.75)
                                ->limit(5)
                                ->get();
                            ```

                            Genero los embeddings en una cola y solo si cambia el hash del contenido.
                            MD,
                        'reactions' => ['javier' => 'insightful', 'ana' => 'love'],
                        'solution' => true,
                    ],
                ],
            ],
            [
                'category' => 'laravel',
                'author' => 'carlos',
                'title' => 'Tests con Pest: ¿datasets o un test por caso?',
                'tags' => ['testing', 'php'],
                'body' => 'Tengo una matriz de permisos con 6 roles y 10 acciones. ¿Escribo 60 tests o hay una forma más limpia?',
                'replies' => [
                    ['author' => 'marta', 'body' => 'Datasets combinados: `->with([...roles])->with([...acciones])` y Pest genera el producto cartesiano. El nombre de cada caso sale en el informe.', 'reactions' => ['carlos' => 'like']],
                ],
            ],
            [
                'category' => 'general',
                'author' => 'sofia',
                'title' => 'Me presento: de diseño gráfico a desarrollo web',
                'tags' => [],
                'body' => '¡Hola! Llevo seis meses aprendiendo Laravel y Vue después de años en diseño gráfico. Encantada de estar por aquí 😊',
                'replies' => [
                    ['author' => 'ana', 'body' => '¡Bienvenida, @sofia! Tu ojo para el diseño te va a venir genial con Tailwind.', 'reactions' => ['sofia' => 'love']],
                    ['author' => 'pablo', 'body' => '¡Bienvenida! Si te atascas con algo, pregunta sin miedo.'],
                ],
                'reactions' => ['ana' => 'celebrate', 'pablo' => 'celebrate'],
            ],
            [
                'category' => 'devops',
                'author' => 'diego',
                'title' => 'Copias de seguridad de PostgreSQL en Docker',
                'tags' => ['docker', 'postgresql', 'seguridad'],
                'body' => '¿Qué estrategia usáis para los backups de la base de datos cuando todo corre en contenedores?',
                'replies' => [
                    ['author' => 'marta', 'body' => '`pg_dump` diario desde un contenedor efímero que monta un volumen de backups, y una copia cifrada fuera del servidor. Y prueba a restaurar de vez en cuando.', 'reactions' => ['diego' => 'insightful']],
                ],
            ],
            [
                'category' => 'frontend',
                'author' => 'pablo',
                'title' => 'Tipar las props de página en Inertia con TypeScript',
                'tags' => ['typescript', 'inertia', 'vue'],
                'body' => '¿Cómo tipáis las props que llegan del controlador para no repetir interfaces en cada página?',
                'replies' => [
                    ['author' => 'lucia', 'body' => 'Defino los tipos del dominio en `types/forum.ts` (Thread, Post, Paginated<T>…) y en cada página `defineProps<{ thread: Thread }>()`. Para las props compartidas, un tipo global.', 'reactions' => ['pablo' => 'like', 'carlos' => 'like'], 'solution' => true],
                ],
            ],
        ];
    }
}
