<?php

namespace Reno\ContentBuilder\Fields;

use Reno\Cms\Fields\AbstractField;
use Reno\Cms\Interfaces\FieldTypes\FieldTypeInterface;
use Reno\ContentBuilder\Containers\ContentBlockContainer;
use Reno\ContentBuilder\FieldTypes\ContentBuilderFieldType;
use Reno\ContentBuilder\Interfaces\ContentBuilderRepositoryInterface;
use Reno\ContentBuilder\Http\Resources\ContentBlockContainerResource;

class ContentBuilderField extends AbstractField
{
    protected ?string $builderClass = null;

    public function __construct(string $key, FieldTypeInterface $fieldType, string $builderClass)
    {
        parent::__construct($key, $fieldType);
        $this->builderClass = $builderClass;
    }

    public static function make(string $key, string $builderClass): self
    {
        return new self($key, new ContentBuilderFieldType(), $builderClass);
    }

    public function getBuilderClass(): string
    {
        return $this->builderClass;
    }

    public function getConfiguration(): array
    {
        $configuration = parent::getConfiguration();

        if ($this->builderClass) {
            try {
                /** @var ContentBuilderRepositoryInterface $contentBuilderRepository */
                $contentBuilderRepository = resolve(ContentBuilderRepositoryInterface::class);
                $contentBuilderContainer = $contentBuilderRepository->findByClass($this->builderClass);
            } catch (\Throwable) {
                return $configuration;
            }

            $configuration = array_merge($configuration, [
                'builder_id' => $contentBuilderContainer->getId(),
                'available_blocks' => $contentBuilderContainer->getBlocks()
                    ->map(fn (ContentBlockContainer $blockContainer) => ContentBlockContainerResource::make($blockContainer)->resolve())
                    ->values()
                    ->all() ?? [],
            ]);
        }

        return $configuration;
    }
}
