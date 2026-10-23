<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;

it('derives an ASCII slug from the title', function () {
    $thread = Thread::factory()->create(['title' => '¿Cómo instalo Laravel 13 en Docker?']);

    expect($thread->slug)->toBe('como-instalo-laravel-13-en-docker');
});

it('suffixes the slug when the title is already taken', function () {
    $slugs = collect(range(1, 3))
        ->map(fn () => Thread::factory()->create(['title' => 'Hola mundo'])->slug)
        ->all();

    expect($slugs)->toBe(['hola-mundo', 'hola-mundo-2', 'hola-mundo-3']);
});

it('keeps the slug stable when the title is edited', function () {
    $thread = Thread::factory()->create(['title' => 'Primer título']);

    $thread->update(['title' => 'Un título distinto']);

    expect($thread->refresh()->slug)->toBe('primer-titulo');
});

it('never reuses the slug of a deleted thread', function () {
    Thread::factory()->create(['title' => 'Hola mundo'])->delete();

    expect(Thread::factory()->create(['title' => 'Hola mundo'])->slug)->toBe('hola-mundo-2');
});

it('falls back to a generic slug when the title has no letters or digits', function () {
    expect(Thread::factory()->create(['title' => '¡¡¡ ??? !!!'])->slug)->toBe('thread')
        ->and(Thread::factory()->create(['title' => '¿¿??'])->slug)->toBe('thread-2');
});

it('caps the slug length without leaving a trailing dash', function () {
    $thread = Thread::factory()->create(['title' => trim(str_repeat('palabra ', 18))]);

    expect(strlen($thread->slug))->toBeLessThanOrEqual(80)
        ->and($thread->slug)->not->toEndWith('-');
});

it('slugs categories and tags from their names', function () {
    expect(Category::factory()->create(['name' => 'Fuera de tema'])->slug)->toBe('fuera-de-tema')
        ->and(Tag::factory()->create(['name' => 'Vue.js'])->slug)->toBe('vuejs');
});

it('takes the next slug when another request claims it at the same moment', function () {
    // The rival request committed first…
    Category::factory()->create(['name' => 'Ayuda con Docker']);

    // …after this one had already checked that the slug was free.
    $late = new class extends Category
    {
        protected $table = 'categories';

        private bool $checked = false;

        protected function uniqueSlug(string $source): string
        {
            if (! $this->checked) {
                $this->checked = true;

                return 'ayuda-con-docker';
            }

            return parent::uniqueSlug($source);
        }
    };
    $late->fill(['name' => 'Ayuda con Docker', 'color' => '#000000'])->save();

    expect($late->slug)->toBe('ayuda-con-docker-2')
        ->and(Category::query()->count())->toBe(2);
});

it('keeps suffixed slugs within the column length', function () {
    // Cyrillic transliterates to several letters per character.
    $name = str_repeat('Щ', 40);
    Category::factory()->create(['name' => $name]);
    $second = Category::factory()->create(['name' => $name]);

    expect(mb_strlen($second->slug))->toBeLessThanOrEqual(80)
        ->and($second->slug)->toEndWith('-2');
});
