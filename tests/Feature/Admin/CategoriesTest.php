<?php

declare(strict_types=1);

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(forumUser('admin'));
});

it('lets administrators create a category, placed after the others', function () {
    Category::factory()->create(['position' => 4]);

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Anuncios',
            'description' => 'Novedades del foro.',
            'color' => '#ef4444',
            'icon' => 'megaphone',
            'is_locked' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->firstWhere('name', 'Anuncios'))
        ->slug->toBe('anuncios')
        ->description->toBe('Novedades del foro.')
        ->color->toBe('#ef4444')
        ->icon->toBe('megaphone')
        ->is_locked->toBeTrue()
        ->position->toBe(5);
});

it('checks the category form', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => '', 'color' => 'red', 'description' => str_repeat('a', 301)])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'color', 'description' => 'max']);

    expect(Category::query()->count())->toBe(0);
});

it('renames a category without changing its address', function () {
    $category = Category::factory()->create(['name' => 'General']);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['name' => 'Conversación general'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->fresh())
        ->name->toBe('Conversación general')
        ->slug->toBe($category->slug);
});

it('deletes only the categories without threads', function () {
    $empty = Category::factory()->create();
    $used = forumThread()->category;

    Livewire::test(ListCategories::class)
        ->assertActionHidden(TestAction::make('delete')->table($used))
        ->callAction(TestAction::make('delete')->table($empty));

    expect(Category::query()->find($empty->id))->toBeNull()
        ->and(Category::query()->find($used->id))->not->toBeNull();
});

it('lists the categories in their order and lets administrators change it', function () {
    $first = Category::factory()->create(['position' => 0]);
    $second = Category::factory()->create(['position' => 1]);

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords([$first, $second], inOrder: true)
        ->call('reorderTable', [(string) $second->id, (string) $first->id]);

    expect($second->fresh()->position)->toBeLessThan($first->fresh()->position);
});
