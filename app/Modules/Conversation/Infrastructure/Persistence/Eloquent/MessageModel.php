<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Database row of a message. Only the repository works with it; the domain never sees it.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $role
 * @property string $content
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $total_tokens
 * @property string|null $finish_reason
 * @property \Carbon\CarbonImmutable $created_at
 */
final class MessageModel extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $table = 'messages';
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $guarded = [];
    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'created_at' => 'immutable_datetime',
    ];
}
