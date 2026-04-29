<?php

namespace Reno\ContentBuilder\Services;

use Reno\Cms\Models\ResourceValue;
use Illuminate\Support\Facades\DB;
use Reno\Cms\Helpers\SchemaHelper;
use Reno\Cms\Helpers\ValidatorHelper;
use Illuminate\Support\Facades\Validator;
use Reno\ContentBuilder\Models\ContentBuilder;
use Reno\ContentBuilder\Models\ContentBlockValue;
use Reno\ContentBuilder\Containers\ContentBlockContainer;
use Reno\ContentBuilder\Interfaces\ContentBlockInterface;
use Reno\ContentBuilder\Containers\ContentBuilderContainer;
use Reno\ContentBuilder\Interfaces\ContentBuilderRepositoryInterface;

class ContentBuilderSyncService
{
    public function __construct(
        protected ContentBuilderRepositoryInterface $contentBuilderRepository,
    )
    {
    }

    public function syncResourceValue(ResourceValue $resourceValue, mixed $value): void
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Content builder value must be an array');
        }

        if (empty($value['builder_id']) || !is_int($value['builder_id'])) {
            throw new \RuntimeException('Content builder value: builder_id is missing');
        }

        $contentBuilder = ContentBuilder::findOrFail($value['builder_id']);
        $contentBuilderContainer = $this->contentBuilderRepository->findByClass($contentBuilder->class);

        $savedBlockValueIds = [];
        $blocks = $value['blocks'] ?? [];

        $this->validateBlocks($contentBuilderContainer, $blocks);

        DB::beginTransaction();

        try {
            foreach (array_values($blocks) as $index => $blockData) {
                if (!is_array($blockData)) {
                    continue;
                }

                $contentBlockValue = $this->saveBlockValue(
                    contentBuilder: $contentBuilder,
                    resourceValue: $resourceValue,
                    contentBlockContainer: $this->resolveBlockContainer($contentBuilderContainer, $blockData['block_id']),
                    blockValueId: $blockData['id'],
                    values: $blockData['values'],
                    sortOrder: $index,
                );

                $savedBlockValueIds[] = $contentBlockValue->getKey();
            }

            ContentBlockValue::query()
                ->where('builder_id', $contentBuilder->getKey())
                ->where('resource_id', $resourceValue->resource_id)
                ->where('resource_field_id', $resourceValue->resource_field_id)
                ->whereNotIn('id', $savedBlockValueIds)
                ->delete();

            $resourceValue->update([
                'value' => $contentBuilder->getKey(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteResourceValue(ResourceValue $resourceValue): void
    {
        ContentBlockValue::query()
            ->where('builder_id', $resourceValue->value)
            ->where('resource_id', $resourceValue->resource_id)
            ->where('resource_field_id', $resourceValue->resource_field_id)
            ->delete();
    }

    private function resolveBlockContainer(ContentBuilderContainer $contentBuilderContainer, int $blockId): ContentBlockContainer
    {
        $contentBlockContainer = $contentBuilderContainer->getBlocks()->first(
            fn (ContentBlockContainer $blockContainer) => $blockContainer->getId() === $blockId
        );

        if ($contentBlockContainer === null) {
            throw new \RuntimeException("Block {$blockId} is not available in the selected builder set.");
        }

        return $contentBlockContainer;
    }

    private function saveBlockValue(
        ContentBuilder $contentBuilder,
        ResourceValue $resourceValue,
        ContentBlockContainer $contentBlockContainer,
        ?int $blockValueId,
        array $values,
        int $sortOrder,
    ): ContentBlockValue
    {
        $contentBlockValue = $blockValueId
            ? ContentBlockValue::query()
                ->whereKey($blockValueId)
                ->where('builder_id', $contentBuilder->getKey())
                ->where('resource_id', $resourceValue->resource_id)
                ->where('resource_field_id', $resourceValue->resource_field_id)
                ->first()
            : null;

        $contentBlockValue ??= new ContentBlockValue();

        $contentBlockValue->fill([
            'builder_id' => $contentBuilder->getKey(),
            'block_id' => $contentBlockContainer->getId(),
            'resource_id' => $resourceValue->resource_id,
            'resource_field_id' => $resourceValue->resource_field_id,
            'values' => $this->prepareValuesForStorage($contentBlockContainer->getBlock(), $values),
            'sort_order' => $sortOrder,
        ])->save();

        return $contentBlockValue;
    }

    private function prepareValuesForStorage(ContentBlockInterface $block, array $values): array
    {
        $preparedValues = [];

        foreach ($block->getSchema() as $field) {
            $preparedValues[$field->getKey()] = $field->getFieldType()->dehydrate(
                $values[$field->getKey()] ?? null
            );
        }

        return $preparedValues;
    }

    private function validateBlocks(ContentBuilderContainer $builderContainer, array $blocks): void
    {
        $data = $blockRules = $rules = [];

        /** @var ContentBlockContainer $blockContainer */
        foreach ($builderContainer->getBlocks() as $blockContainer) {
            foreach (SchemaHelper::getFields($blockContainer->getBlock()->getSchema()) as $fieldContainer) {
                $blockRules[$blockContainer->getId()][] = ValidatorHelper::normalizeRulesArray(
                    $fieldContainer->getField()->getKey(),
                    $fieldContainer->getField()->getValidationRules(),
                );
            }
        }

        foreach ($blocks as $index => $blockData) {
            if (!isset($blockRules[ $blockData['block_id'] ])) {
                continue;
            }

            foreach ($blockRules[ $blockData['block_id'] ] as $fieldRules) {
                foreach ($fieldRules as $key => $rule) {
                    $rules['blocks.' . $index . '.' . $key] = $rule;
                }
            }

            $data[$index] = $blockData['values'];
        }

        $data = [
            'blocks' => $data,
        ];

        Validator::make($data, $rules)->validate();
    }
}
