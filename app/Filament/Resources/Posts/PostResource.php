<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts;

use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Every post, deleted ones included, for the staff to approve, hide, delete
 * or restore (spec §4.8). Posts are written in the forum.
 */
final class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('Moderation');
    }

    /**
     * The posts held for review (by the AI): they wait for a decision even
     * once their report is closed.
     */
    public static function getNavigationBadge(): ?string
    {
        $held = Post::query()->where('status', PostStatus::PendingReview)->count();

        return $held > 0 ? (string) $held : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('Posts held for review');
    }

    public static function getModelLabel(): string
    {
        return __('post');
    }

    public static function getPluralModelLabel(): string
    {
        return __('posts');
    }

    /**
     * @return Builder<Post>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Post> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->withoutGlobalScopes([SoftDeletingScope::class])
            // The thread even when deleted: its title and its address.
            ->with(['author', 'thread' => fn ($thread) => $thread->withTrashed()])
            ->withCount(['reports as open_reports_count' => fn (Builder $reports) => $reports->where('status', ReportStatus::Open)]);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
        ];
    }
}
