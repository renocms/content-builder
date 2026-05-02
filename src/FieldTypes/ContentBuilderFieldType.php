<?php

namespace Reno\ContentBuilder\FieldTypes;

use Reno\Cms\Interfaces\FieldTypes\FieldTypeInterface;
use Reno\Cms\Interfaces\FieldTypes\SyncsResourceValueInterface;
use Reno\Cms\Models\ResourceValue;
use Reno\ContentBuilder\Services\ContentBuilderSyncService;

class ContentBuilderFieldType implements FieldTypeInterface, SyncsResourceValueInterface
{
    private ?ContentBuilderSyncService $contentBuilderService = null;

    public function getType(): string
    {
        return 'contentbuilder';
    }

    public function getName(): string
    {
        return 'Content Builder';
    }

    public function getDescription(): ?string
    {
        return __('cms::cms.field_type_content_builder_description');
    }

    public function getJsModule(): string
    {
        return '/js/reno/content-builder/build/components/ContentBuilderEditor.js';
    }

    public function getValidationRules(): array
    {
        return [
            'value' => ['nullable', 'array'],
            'value.builder_id' => ['required', 'integer', 'min:1'],
            'value.blocks' => ['array'],
            'value.blocks.*.id' => ['nullable', 'integer', 'min:1'],
            'value.blocks.*.block_id' => ['required', 'integer', 'min:1'],
            'value.blocks.*.values' => ['required', 'array'],
        ];
    }

    public function dehydrate(mixed $value): mixed
    {
        if (is_array($value) && isset($value['builder_id'])) {
            return $value['builder_id'];
        }

        return $value;
    }

    public function hydrate(mixed $value): ?int
    {
        return $value && is_numeric($value) ? (int) $value : null;
    }

    public function syncResourceValue(ResourceValue $resourceValue, mixed $value): void
    {
        $this->getSyncService()->syncResourceValue($resourceValue, $value);
    }

    public function deleteResourceValue(ResourceValue $resourceValue): void
    {
        $this->getSyncService()->deleteResourceValue($resourceValue);
    }

    private function getSyncService(): ContentBuilderSyncService
    {
        if ($this->contentBuilderService === null) {
            $this->contentBuilderService = app(ContentBuilderSyncService::class);
        }

        return $this->contentBuilderService;
    }
}
