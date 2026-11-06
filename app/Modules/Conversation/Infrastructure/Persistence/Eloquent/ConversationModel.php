<?php

declare(strict_types=1);

namespace App\Modules\Conversation\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Database row of a conversation. Only the repository works with it; the domain never sees it.
 *
 * @property string $id
 * @property string $title
 * @property string $model
 * @property float $temperature
 * @property string|null $system_prompt
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class ConversationModel extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $table = 'conversations';
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $guarded = [];
    protected $casts = [
        'temperature' => 'float',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
