<?php

namespace Reno\ContentBuilder\Interfaces;

use Reno\Cms\Interfaces\Forms\FieldInterface;

interface ContentBlockInterface
{
    public function getKey(): string;

    public function getLabel(): string;

    public function getViewComposer(): ?string;

    /**
     * @return array<FieldInterface>
     */
    public function getSchema(): array;
}
