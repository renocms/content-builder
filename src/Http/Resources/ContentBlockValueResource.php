<?php

namespace Reno\ContentBuilder\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Reno\ContentBuilder\Models\ContentBlockValue;

/**
 * @property ContentBlockValue $resource
 */
class ContentBlockValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'block_id' => $this->resource->block_id,
            'block_name' => $this->resource->name ?? '',
            'values' => $this->resource->values,
            'sort_order' => $this->resource->sort_order,
        ];
    }
}
