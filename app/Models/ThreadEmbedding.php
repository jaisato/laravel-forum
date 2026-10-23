<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Semantic fingerprint of a thread (title and opening post) used to find
 * related threads. PostgreSQL only: the table needs pgvector.
 *
 * @property int $thread_id
 * @property list<float> $embedding
 * @property string $model
 * @property string $content_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['thread_id', 'embedding', 'model', 'content_hash'])]
class ThreadEmbedding extends Model
{
    protected $primaryKey = 'thread_id';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => AsVector::class,
        ];
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
