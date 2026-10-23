<?php

declare(strict_types=1);

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(forumUser('admin'));
});

it('lets administrators create, rename and delete tags', function () {
    Livewire::test(ManageTags::class)
        ->callAction('create', data: ['name' => 'horizon', 'color' => '#22c55e'])
        ->assertHasNoFormErrors();

    $tag = Tag::query()->firstWhere('name', 'horizon');

    expect($tag)->not->toBeNull()
        ->slug->toBe('horizon')
        ->color->toBe('#22c55e');

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('edit')->table($tag), data: ['name' => 'laravel-horizon'])
        ->assertHasNoFormErrors();

    // The address stays: links to the tag keep working.
    expect($tag->fresh())
        ->name->toBe('laravel-horizon')
        ->slug->toBe('horizon');

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('delete')->table($tag));

    expect(Tag::query()->count())->toBe(0);
});

it('refuses a tag name already taken', function () {
    Tag::factory()->create(['name' => 'vue']);

    Livewire::test(ManageTags::class)
        ->callAction('create', data: ['name' => 'vue'])
        ->assertHasFormErrors(['name' => 'unique']);

    expect(Tag::query()->count())->toBe(1);
});
