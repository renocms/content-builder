<?php

namespace Reno\ContentBuilder\Interfaces;

use Illuminate\Support\Collection;
use Reno\ContentBuilder\Containers\ContentBuilderContainer;

interface ContentBuilderRepositoryInterface
{
    /**
     * @return Collection<ContentBuilderContainer>
     */
    public function getAll(): Collection;

    public function findById(int $id): ContentBuilderContainer;

    public function findByClass(string $className): ContentBuilderContainer;

    public function clearCache(): void;
}
