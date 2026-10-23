<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModerationActions\Pages;

use App\Filament\Resources\ModerationActions\ModerationActionResource;
use Filament\Resources\Pages\ListRecords;

final class ListModerationActions extends ListRecords
{
    protected static string $resource = ModerationActionResource::class;
}
