<?php

namespace Reno\ContentBuilder\Containers;

use Illuminate\Support\Collection;
use Reno\Cms\Helpers\SchemaHelper;
use Reno\Cms\Containers\FieldContainer;
use Reno\ContentBuilder\Interfaces\ContentBlockInterface;
use Reno\ContentBuilder\Interfaces\ContentBlockComposerInterface;

class ContentBlockContainer
{
    /** @var Collection<FieldContainer>|null $schema */
    private ?Collection $fieldsList = null;

    public function __construct(
        private readonly int $id,
        private readonly ContentBlockInterface $block,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBlock(): ContentBlockInterface
    {
        return $this->block;
    }

    public function getField(string $name): FieldContainer
    {
        $fieldsList = $this->getFields();

        if (!$fieldsList->has($name)) {
            throw new \RuntimeException('Field "' . $name . '" does not exist.');
        }

        return $fieldsList->get($name);
    }

    public function getFields(): Collection
    {
        if ($this->fieldsList === null) {
            $this->fieldsList = SchemaHelper::getFields($this->getBlock()->getSchema())
                ->keyBy(fn (FieldContainer $fieldContainer) => $fieldContainer->getField()->getKey());
        }

        return $this->fieldsList;
    }

    public function getViewComposer(): ?ContentBlockComposerInterface
    {
        $composerClass = $this->block->getViewComposer();
        if ($composerClass !== null) {
            return resolve($composerClass);
        }

        return null;
    }
}
