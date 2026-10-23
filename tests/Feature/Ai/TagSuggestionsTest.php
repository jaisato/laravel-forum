<?php

declare(strict_types=1);

use App\Actions\Ai\SuggestTags;
use App\Ai\Agents\TagSuggester;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;

function enableAiTags(): void
{
    config([
        'forum.ai.enabled' => true,
        'forum.ai.tags.enabled' => true,
        'forum.ai.moderation.enabled' => false,
    ]);
}

/**
 * The forum's tags (laravel, colas, horizon and vue), and a category to
 * start threads in.
 */
function forumTags(): void
{
    foreach (['laravel', 'colas', 'horizon', 'vue'] as $name) {
        Tag::factory()->create(['name' => $name, 'slug' => $name]);
    }

    Category::factory()->create();
}

it('suggests up to three of the forum tags, never one that does not exist', function () {
    forumTags();
    enableAiTags();
    TagSuggester::fake([['tags' => ['Laravel', 'inventada', 'colas', 'horizon', 'vue']]]);

    $tags = app(SuggestTags::class)->handle('Mis colas no arrancan', 'Desde ayer Horizon no procesa nada.');

    expect($tags->pluck('slug')->all())->toBe(['laravel', 'colas', 'horizon']);

    TagSuggester::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Mis colas no arrancan')
        && str_contains($prompt->prompt, 'Desde ayer Horizon no procesa nada.')
        && str_contains($prompt->prompt, 'vue'));
});

it('suggests nothing when the provider fails, and logs it', function () {
    forumTags();
    enableAiTags();
    TagSuggester::fake([fn () => throw new RuntimeException('Overloaded')]);
    Log::spy();

    expect(app(SuggestTags::class)->handle('Mis colas no arrancan', ''))->toBeEmpty();

    Log::shouldHaveReceived('warning')->once();
});

it('asks nothing when the forum has no tags', function () {
    enableAiTags();
    TagSuggester::fake();

    expect(app(SuggestTags::class)->handle('Mis colas no arrancan', ''))->toBeEmpty();

    TagSuggester::assertNeverPrompted();
});

it('answers the new thread form', function () {
    forumTags();
    enableAiTags();
    TagSuggester::fake([['tags' => ['colas']]]);

    $this->actingAs(User::factory()->create())
        ->postJson('/ai/tag-suggestions', ['title' => 'Mis colas no arrancan', 'body' => 'Desde ayer.'])
        ->assertOk()
        ->assertJsonPath('tags.0.slug', 'colas')
        ->assertJsonPath('tags.0.name', 'colas')
        ->assertJsonCount(1, 'tags');
});

it('needs a title and a member who may start threads', function () {
    forumTags();
    enableAiTags();
    TagSuggester::fake();

    $this->postJson('/ai/tag-suggestions', ['title' => 'Mis colas no arrancan'])->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson('/ai/tag-suggestions', ['body' => 'Sin título'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');

    $this->actingAs(User::factory()->unverified()->create())
        ->postJson('/ai/tag-suggestions', ['title' => 'Mis colas no arrancan'])
        ->assertForbidden();

    TagSuggester::assertNeverPrompted();
});

it('is not found while the feature is off', function () {
    forumTags();
    TagSuggester::fake();

    $this->actingAs(User::factory()->create())
        ->postJson('/ai/tag-suggestions', ['title' => 'Mis colas no arrancan'])
        ->assertNotFound();

    TagSuggester::assertNeverPrompted();
});
