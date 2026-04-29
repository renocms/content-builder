<?php

namespace Reno\ContentBuilder\Repositories;

use Reno\Cms\Models\Resource;
use Reno\Cms\Models\ResourceValue;
use Illuminate\Support\Collection;
use Reno\ContentBuilder\Models\ContentBuilder;
use Reno\ContentBuilder\Models\ContentBlockValue;
use Reno\ContentBuilder\Fields\ContentBuilderField;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Reno\ContentBuilder\Containers\ContentBlockContainer;
use Reno\ContentBuilder\Containers\ContentBuilderContainer;
use Reno\ContentBuilder\Containers\ContentBlockValueContainer;
use Reno\ContentBuilder\Interfaces\ContentBlockRepositoryInterface;
use Reno\ContentBuilder\Interfaces\ContentBuilderRepositoryInterface;
use Reno\Cms\Interfaces\Repositories\ResourceLayoutRepositoryInterface;

class ContentBlockRepository implements ContentBlockRepositoryInterface
{
    public function __construct(
        protected ContentBuilderRepositoryInterface $contentBuilderRepository,
        protected ResourceLayoutRepositoryInterface $resourceLayoutRepository,
    )
    {
    }

    public function getAll(Resource $resource, string $fieldName): Collection
    {
        $resourceLayoutContainer = $this->resourceLayoutRepository->findById($resource->resource_layout_id);
        $fieldContainer = $resourceLayoutContainer->getField($fieldName);
        $field = $fieldContainer->getField();

        if (!$field instanceof ContentBuilderField) {
            throw new \RuntimeException("Field $fieldName must be instance of ContentBuilderField");
        }

        $contentBuilderContainer = $this->contentBuilderRepository->findByClass($field->getBuilderClass());

        return ContentBlockValue::query()
            ->with('block')
            ->where('builder_id', $contentBuilderContainer->getId())
            ->where('resource_id', $resource->id)
            ->where('resource_field_id', $fieldContainer->getId())
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ContentBlockValue $blockValue) => new ContentBlockValueContainer(
                blockValue: $blockValue,
                blockContainer: $contentBuilderContainer->getBlock($blockValue->block_id),
            ));
    }

    public function getBlocksForResource(int $resourceId, int $resourceFieldId): Collection
    {
        try {
            $resourceValue = ResourceValue::query()
                ->where('resource_id', $resourceId)
                ->where('resource_field_id', $resourceFieldId)
                ->firstOrFail();

            $contentBuilder = ContentBuilder::query()
                ->findOrFail($resourceValue->value);
        } catch (ModelNotFoundException) {
            return new Collection();
        }

        $contentBuilderContainer = $this->contentBuilderRepository->findByClass($contentBuilder->class);

        $values = ContentBlockValue::query()
            ->with('block')
            ->where('builder_id', $contentBuilder->getKey())
            ->where('resource_id', $resourceId)
            ->where('resource_field_id', $resourceFieldId)
            ->orderBy('sort_order')
            ->get();

        return $this->hydrateValues($contentBuilderContainer, $values);
    }

    private function hydrateValues(ContentBuilderContainer $contentBuilderContainer, Collection $values): Collection
    {
        $blockContainers = $contentBuilderContainer->getBlocks()
            ->keyBy(fn (ContentBlockContainer $blockContainer) => $blockContainer->getId());

        return $values->map(function (ContentBlockValue $value) use ($blockContainers) {
            /** @var ContentBlockContainer $blockContainer */
            $blockContainer = $blockContainers->get($value->block_id);

            if ($block = $blockContainer?->getBlock()) {
                $hydratedValues = [];

                foreach ($block->getSchema() as $field) {
                    $hydratedValues[$field->getKey()] = $field->getFieldType()
                        ->hydrate($value->values[$field->getKey()] ?? null);
                }

                $value->values = $hydratedValues;
            }

            $value->name = $block->getLabel();
            return $value;
        });
    }
}
