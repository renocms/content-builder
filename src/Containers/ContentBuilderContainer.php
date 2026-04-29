<?php

namespace Reno\ContentBuilder\Containers;

use Illuminate\Support\Collection;
use Reno\ContentBuilder\Interfaces\ContentBuilderInterface;

class ContentBuilderContainer
{
    /** @var Collection<ContentBlockContainer>|null */
    private ?Collection $blocks = null;

    public function __construct(
        private readonly int $id,
        private readonly ContentBuilderInterface $set,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSet(): ContentBuilderInterface
    {
        return $this->set;
    }

    public function addBlock(ContentBlockContainer $block): void
    {
        if ($this->blocks === null) {
            $this->blocks = Collection::make();
        }

        $this->blocks->put($block->getId(), $block);
    }

    /**
     * @return Collection<ContentBlockContainer>
     */
    public function getBlocks(): Collection
    {
        return $this->blocks ?? Collection::make();
    }

    public function getBlock(int $blockId): ContentBlockContainer
    {
        return $this->blocks->get($blockId);
    }
}
