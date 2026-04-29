<?php

namespace Reno\ContentBuilder\Http\Resources;

use Illuminate\Http\Request;
use Reno\Cms\Containers\FieldContainer;
use Reno\Cms\Interfaces\Forms\FieldInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Reno\ContentBuilder\Containers\ContentBlockContainer;
use Reno\Cms\Http\Resources\Resources\ResourceLayoutFieldResource;

/**
 * @property ContentBlockContainer $resource
 */
class ContentBlockContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $block = $this->resource->getBlock();

        return [
            'id' => $this->resource->getId(),
            'name' => $block->getLabel(),
            'fields' => collect($block->getSchema())
                ->map(function (FieldInterface $field) {
                    return ResourceLayoutFieldResource::make(new FieldContainer(0, $field))->resolve();
                })
                ->values()
                ->all(),
        ];
    }
}
