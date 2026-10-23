<?php

declare(strict_types=1);

use App\Ai\ForumAi;

beforeEach(function () {
    config([
        'forum.ai.enabled' => true,
        'forum.ai.text' => ['provider' => 'anthropic', 'model' => 'claude-opus-5-5'],
        'forum.ai.moderation.enabled' => true,
        'forum.ai.moderation.model' => null,
        'forum.ai.summaries.enabled' => true,
        'forum.ai.summaries.model' => 'claude-sonnet-5-5',
        'forum.ai.similar.enabled' => true,
        'forum.ai.similar.provider' => 'openai',
        'forum.ai.similar.model' => 'text-embedding-3-small',
        'forum.ai.similar.dimensions' => 1536,
        'forum.ai.tags.enabled' => false,
    ]);
});

it('keeps every feature off while the master switch is off', function () {
    config(['forum.ai.enabled' => false]);

    expect(app(ForumAi::class)->enabled('moderation'))->toBeFalse()
        ->and(app(ForumAi::class)->enabled('summaries'))->toBeFalse();
});

it('enables a feature only when its own switch is on as well', function () {
    $ai = app(ForumAi::class);

    expect($ai->enabled('moderation'))->toBeTrue()
        ->and($ai->enabled('tags'))->toBeFalse();
});

it('rejects features it does not know about', function () {
    app(ForumAi::class)->enabled('translations');
})->throws(InvalidArgumentException::class);

it('uses a feature model when one is configured and the shared model otherwise', function () {
    $ai = app(ForumAi::class);

    expect($ai->textModel('summaries'))->toBe('claude-sonnet-5-5')
        ->and($ai->textModel('moderation'))->toBe('claude-opus-5-5');
});

it('exposes the text provider and the embeddings settings', function () {
    $ai = app(ForumAi::class);

    expect($ai->textProvider())->toBe('anthropic')
        ->and($ai->embeddingsProvider())->toBe('openai')
        ->and($ai->embeddingsModel())->toBe('text-embedding-3-small')
        ->and($ai->embeddingsDimensions())->toBe(1536);
});

it('summarises which features the interface may offer', function () {
    expect(app(ForumAi::class)->features())->toBe([
        'moderation' => true,
        'summaries' => true,
        'similar' => true,
        'tags' => false,
    ]);
});
