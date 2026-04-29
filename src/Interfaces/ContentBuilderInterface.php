<?php

namespace Reno\ContentBuilder\Interfaces;

interface ContentBuilderInterface
{
    /**
     * @return array<class-string<ContentBlockInterface>>
     */
    public function getBlocks(): array;

    public function getPerPage(): int;
}
