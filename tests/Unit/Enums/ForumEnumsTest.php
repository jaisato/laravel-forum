<?php

declare(strict_types=1);

use App\Enums\ModerationActionType;
use App\Enums\PostStatus;
use App\Enums\ReactionType;
use App\Enums\ReportReason;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\ThreadStatus;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\File;

it('shows an emoji for every reaction', function () {
    expect(collect(ReactionType::cases())->mapWithKeys(fn (ReactionType $type) => [$type->value => $type->emoji()])->all())
        ->toBe([
            'like' => '👍',
            'love' => '❤️',
            'laugh' => '😂',
            'insightful' => '💡',
            'celebrate' => '🎉',
        ]);
});

it('keeps the report reasons the moderation queue expects', function () {
    expect(array_column(ReportReason::cases(), 'value'))->toBe(['spam', 'abuse', 'off_topic', 'illegal', 'other'])
        ->and(array_column(ReportSource::cases(), 'value'))->toBe(['user', 'ai'])
        ->and(array_column(ReportStatus::cases(), 'value'))->toBe(['open', 'resolved', 'dismissed']);
});

it('translates every label into Spanish', function (string $enum) {
    /** @var array<string, string> $spanish */
    $spanish = json_decode(File::get(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);

    app()->setLocale('en');

    foreach ($enum::cases() as $case) {
        expect($spanish)->toHaveKey($case->label());
    }
})->with([
    Role::class,
    ThreadStatus::class,
    PostStatus::class,
    ReactionType::class,
    ReportReason::class,
    ReportSource::class,
    ReportStatus::class,
    ModerationActionType::class,
]);

it('shows reaction and moderation labels in Spanish', function () {
    app()->setLocale('es');

    expect(ReactionType::Insightful->label())->toBe('Útil')
        ->and(ReportReason::OffTopic->label())->toBe('Fuera de tema')
        ->and(ReportStatus::Dismissed->label())->toBe('Descartado')
        ->and(ModerationActionType::PostHidden->label())->toBe('Mensaje ocultado')
        ->and(ThreadStatus::PendingReview->label())->toBe('Pendiente de revisión');
});

it('gives the admin panel the same labels as the rest of the forum', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case)->toBeInstanceOf(HasLabel::class)
            ->and($case->getLabel())->toBe($case->label());
    }
})->with([
    Role::class,
    ThreadStatus::class,
    PostStatus::class,
    ReportReason::class,
    ReportSource::class,
    ReportStatus::class,
    ModerationActionType::class,
]);
