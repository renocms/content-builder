<?php

namespace Reno\ContentBuilder\Containers;

use Reno\Cms\Models\Media;
use Illuminate\Support\Arr;
use Reno\Cms\Helpers\SchemaHelper;
use Reno\Cms\Containers\FieldContainer;
use Reno\ContentBuilder\Models\ContentBlockValue;

final class ContentBlockValueContainer
{
    private ?array $viewData = null;

    public function __construct(
        private readonly ContentBlockValue $blockValue,
        private readonly ContentBlockContainer $blockContainer,
    )
    {
    }

    public function getBlockValue(): ContentBlockValue
    {
        return $this->blockValue;
    }

    public function getBlockContainer(): ContentBlockContainer
    {
        return $this->blockContainer;
    }

    public function getBlockName(): string
    {
        return $this->blockContainer->getBlock()->getKey();
    }

    public function getViewData(): array
    {
        if ($this->viewData !== null) {
            return $this->viewData;
        }

        $values = $this->getBlockValue()->values;
        $fields = $this->getBlockContainer()->getFields();
        $this->viewData = [];

        /** @var FieldContainer $fieldContainer */
        foreach ($fields as $fieldContainer) {
            $field = $fieldContainer->getField();
            $this->viewData[ $field->getKey() ] = $values[ $field->getKey() ] ?? null;
        }

        $mediaFields = SchemaHelper::collectMediaFields($fields, $values);

        if (!empty($mediaFields)) {
            Media::query()
                ->whereIn('id', array_keys($mediaFields))
                ->get()
                ->each(function (Media $media) use ($mediaFields) {
                    Arr::set($this->viewData, $mediaFields[$media->id], $media);
                });
        }

        if ($blockComposer = $this->getBlockContainer()->getViewComposer()) {
            $this->viewData = array_merge($this->viewData, $blockComposer->compose($this->viewData));
        }

        return $this->viewData;
    }
}
