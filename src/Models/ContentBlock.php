<?php

namespace Reno\ContentBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Reno\Cms\Helpers\TablePrefixHelper;

/**
 * @property int $id
 * @property string $class
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Reno\ContentBuilder\Models\ContentBlockValue> $blockValues
 * @property-read int|null $block_values_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlock whereClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlock whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContentBlock extends Model
{
    protected $fillable = [
        'class',
    ];

    public static function getTableName(): string
    {
        return TablePrefixHelper::table('content_blocks');
    }

    public function getTable(): string
    {
        return static::getTableName();
    }

    public function blockValues(): HasMany
    {
        return $this->hasMany(ContentBlockValue::class, 'block_id');
    }
}
