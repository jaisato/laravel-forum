<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Services\Ai\SimilarThreadFinder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A valid new thread, with the given changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function newThreadInput(Category $category, array $changes = []): array
{
    return [
        'category_id' => $category->id,
        'title' => '¿Cómo se testean las colas en Laravel?',
        'body' => 'Uso `Queue::fake()` en los tests, ¿es suficiente?',
        'tags' => [],
        ...$changes,
    ];
}

describe('the form', function () {
    it('sends guests to the login page', function () {
        $this->get(route('threads.create'))->assertRedirect(route('login'));
        $this->post(route('threads.store'))->assertRedirect(route('login'));
    });

    it('offers the categories the member may post in, the tags and the category they came from', function () {
        $open = Category::factory()->create(['name' => 'Laravel']);
        Category::factory()->locked()->create(['name' => 'Anuncios']);
        $tag = Tag::factory()->create(['name' => 'eloquent']);

        $this->actingAs(User::factory()->create())
            ->get(route('threads.create', ['category' => $open->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('threads/Create')
                ->has('categories', 1)
                ->where('categories.0.id', $open->id)
                ->where('categories.0.name', 'Laravel')
                ->has('tags', 1)
                ->where('tags.0.id', $tag->id)
                ->where('tags.0.name', 'eloquent')
                ->where('category', $open->id)
                ->where('similar', []));
    });

    it('lets staff open threads in locked categories', function () {
        Category::factory()->create();
        $locked = Category::factory()->locked()->create();

        $this->actingAs(User::factory()->moderator()->create())
            ->get(route('threads.create', ['category' => $locked->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', 2)
                ->where('category', $locked->id));
    });

    it('preselects no category the member may not post in', function () {
        Category::factory()->create();
        $locked = Category::factory()->locked()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('threads.create', ['category' => $locked->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('category', null));
    });

    it('refuses members who may not take part', function (Closure $member) {
        Category::factory()->create();

        $this->actingAs($member())->get(route('threads.create'))->assertForbidden();
    })->with([
        'unverified' => [fn () => User::factory()->unverified()->create()],
        'banned' => [fn () => User::factory()->banned()->create()],
    ]);

    it('suggests similar threads once the title is long enough', function () {
        $existing = forumThread();
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('threads.create', ['title' => 'testing the forum']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('similar', 1)
                ->where('similar.0', [
                    'id' => $existing->id,
                    'title' => 'A thread about testing the forum',
                    'slug' => $existing->slug,
                    'replies_count' => 0,
                    'is_solved' => false,
                ]));

        $this->actingAs($member)
            ->get(route('threads.create', ['title' => 'testing']))
            ->assertInertia(fn (Assert $page) => $page->where('similar', []));
    });

    it('looks for similar threads with no more of the title than a thread takes', function () {
        $searched = [];
        app()->instance(SimilarThreadFinder::class, new class($searched) implements SimilarThreadFinder
        {
            /** @param  list<string>  $searched */
            public function __construct(private array &$searched) {}

            public function similarTo(Thread $thread, int $limit = 5): Collection
            {
                return collect();
            }

            public function forText(string $text, int $limit = 5, ?int $exceptThreadId = null): Collection
            {
                $this->searched[] = $text;

                return collect();
            }
        });

        Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('threads.create', ['title' => str_repeat('ñ', 6000)]))
            ->assertOk();

        expect($searched)->toHaveCount(1)
            ->and(mb_strlen($searched[0]))->toBe(config('forum.threads.title_max'));
    });
});

describe('creating a thread', function () {
    it('opens the thread and takes its author to it', function () {
        $category = Category::factory()->create();
        [$queues, $testing] = Tag::factory()->count(2)->create();
        $author = User::factory()->create();

        $response = $this->actingAs($author)->post(route('threads.store'), newThreadInput($category, [
            'title' => '   ¿Cómo se testean las colas en Laravel?   ',
            'tags' => [$queues->id, $testing->id],
        ]));

        $thread = Thread::query()->sole();

        $response->assertRedirect(route('threads.show', $thread))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Thread created.']);

        expect($thread->title)->toBe('¿Cómo se testean las colas en Laravel?')
            ->and($thread->user_id)->toBe($author->id)
            ->and($thread->category_id)->toBe($category->id)
            ->and($thread->tags->modelKeys())->toEqualCanonicalizing([$queues->id, $testing->id])
            ->and($thread->firstPost->body)->toBe('Uso `Queue::fake()` en los tests, ¿es suficiente?');
    });

    it('keeps members out of locked categories but not staff', function () {
        $locked = Category::factory()->locked()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('threads.store'), newThreadInput($locked))
            ->assertForbidden();

        expect(Thread::query()->count())->toBe(0);

        $this->actingAs(User::factory()->moderator()->create())
            ->post(route('threads.store'), newThreadInput($locked))
            ->assertRedirect();

        expect(Thread::query()->count())->toBe(1);
    });

    it('validates the thread', function (Closure $changes, string $field) {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('threads.store'), newThreadInput($category, $changes()))
            ->assertSessionHasErrors($field);

        expect(Thread::query()->count())->toBe(0);
    })->with([
        'a short title' => [fn () => ['title' => 'Colas'], 'title'],
        'a long title' => [fn () => ['title' => str_repeat('a', 151)], 'title'],
        'a short body' => [fn () => ['body' => 'Ayuda'], 'body'],
        'a long body' => [fn () => ['body' => str_repeat('a', 30_001)], 'body'],
        'no category' => [fn () => ['category_id' => null], 'category_id'],
        'an unknown category' => [fn () => ['category_id' => 999_999], 'category_id'],
        'six tags' => [fn () => ['tags' => Tag::factory()->count(6)->create()->modelKeys()], 'tags'],
        'an unknown tag' => [fn () => ['tags' => [999_999]], 'tags.0'],
    ]);

    it('slows down members who open threads too fast', function () {
        $category = Category::factory()->create();
        $member = User::factory()->create();
        $attempts = config('forum.rate_limits.threads.attempts');

        foreach (range(1, $attempts) as $i) {
            $this->actingAs($member)->post(route('threads.store'), newThreadInput($category, ['title' => "Una pregunta sobre colas número {$i}"]));
        }

        $this->actingAs($member)
            ->from(route('threads.create'))
            ->post(route('threads.store'), newThreadInput($category, ['title' => 'Una pregunta de más sobre colas']))
            ->assertRedirect(route('threads.create'))
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Too many attempts. Please wait a moment.']);

        expect(Thread::query()->count())->toBe($attempts);
    });
});

describe('editing and deleting', function () {
    it('gives those who may edit the thread the tags to choose from', function () {
        $thread = forumThread();
        $tag = Tag::factory()->create(['name' => 'colas']);

        $this->actingAs($thread->author)
            ->get(route('threads.show', $thread))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', true)
                ->has('tagOptions', 1)
                ->where('tagOptions.0.id', $tag->id));

        $this->actingAs(User::factory()->create())
            ->get(route('threads.show', $thread))
            ->assertInertia(fn (Assert $page) => $page->missing('tagOptions'));
    });

    it('lets the author change the title and the tags', function () {
        $thread = forumThread();
        $tag = Tag::factory()->create();

        $this->actingAs($thread->author)
            ->from(route('threads.show', $thread))
            ->patch(route('threads.update', $thread), ['title' => '  Un título más claro para el hilo  ', 'tags' => [$tag->id]])
            ->assertRedirect(route('threads.show', $thread))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Thread updated.']);

        expect($thread->fresh())
            ->title->toBe('Un título más claro para el hilo')
            ->slug->toBe($thread->slug)
            ->and($thread->fresh()->tags->modelKeys())->toBe([$tag->id]);
    });

    it('validates the edit like a new thread', function () {
        $thread = forumThread();

        $this->actingAs($thread->author)
            ->patch(route('threads.update', $thread), ['title' => 'Corto', 'tags' => Tag::factory()->count(6)->create()->modelKeys()])
            ->assertSessionHasErrors(['title', 'tags']);
    });

    it('keeps others from editing or deleting the thread', function () {
        $thread = forumThread();
        $other = User::factory()->create();

        $this->actingAs($other)->patch(route('threads.update', $thread), ['title' => 'Un título que no es suyo', 'tags' => []])->assertForbidden();
        $this->actingAs($other)->delete(route('threads.destroy', $thread))->assertForbidden();

        expect($thread->fresh()->trashed())->toBeFalse();
    });

    it('lets the author delete a thread nobody has answered', function () {
        $thread = forumThread();

        $this->actingAs($thread->author)
            ->delete(route('threads.destroy', $thread))
            ->assertRedirect(route('categories.show', $thread->category))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Thread deleted.']);

        expect($thread->fresh()->trashed())->toBeTrue();
    });

    it('keeps an answered thread from its author but not from staff', function () {
        $thread = forumThread();
        forumReply($thread);

        $this->actingAs($thread->author)->delete(route('threads.destroy', $thread))->assertForbidden();

        expect($thread->fresh()->trashed())->toBeFalse();

        $this->actingAs(User::factory()->moderator()->create())
            ->delete(route('threads.destroy', $thread))
            ->assertRedirect(route('categories.show', $thread->category));

        expect($thread->fresh()->trashed())->toBeTrue();
    });
});

describe('the new thread button', function () {
    it('shows when the viewer may start threads there', function (?Closure $viewer, bool $everywhere, bool $inLocked) {
        Category::factory()->create();
        $locked = Category::factory()->locked()->create();
        $user = $viewer === null ? null : $viewer();

        if ($user !== null) {
            $this->actingAs($user);
        }

        $this->get(route('threads.index'))
            ->assertInertia(fn (Assert $page) => $page->where('can.create_thread', $everywhere));

        $this->get(route('categories.show', $locked))
            ->assertInertia(fn (Assert $page) => $page->where('can.create_thread', $inLocked));
    })->with([
        'a guest' => [null, false, false],
        'a member' => [fn () => User::factory()->create(), true, false],
        'an unverified member' => [fn () => User::factory()->unverified()->create(), false, false],
        'a moderator' => [fn () => User::factory()->moderator()->create(), true, true],
    ]);
});
