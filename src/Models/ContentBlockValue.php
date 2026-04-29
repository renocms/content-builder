<?php

namespace Reno\ContentBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Reno\Cms\Helpers\TablePrefixHelper;
use Reno\Cms\Models\Resource;
use Reno\Cms\Models\ResourceField;

/**
 * @property int $id
 * @property int $builder_id
 * @property int $block_id
 * @property int $resource_id
 * @property int $resource_field_id
 * @property array<array-key, mixed>|null $values
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Reno\ContentBuilder\Models\ContentBlock $block
 * @property-read \Reno\ContentBuilder\Models\ContentBuilder $builder
 * @property-read Resource|null $resource
 * @property-read ResourceField|null $resourceField
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereBlockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereBuilderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereResourceFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentBlockValue whereValues($value)
 * @mixin \Eloquent
 */
class ContentBlockValue extends Model
{
    protected $fillable = [
        'builder_id',
        'block_id',
        'resource_id',
        'resource_field_id',
        'values',
        'sort_order',
    ];

    protected $casts = [
        'values' => 'array',
        'sort_order' => 'integer',
    ];

    public static function getTableName(): string
    {
        return TablePrefixHelper::table('content_block_values');
    }

    public function getTable(): string
    {
        return static::getTableName();
    }

    public function builder(): BelongsTo
    {
        return $this->belongsTo(ContentBuilder::class, 'builder_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(ContentBlock::class, 'block_id');
    }

    public function resourceField(): BelongsTo
    {
        return $this->belongsTo(ResourceField::class, 'resource_field_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }
}
