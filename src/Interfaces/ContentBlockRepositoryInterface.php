<?php

namespace Reno\ContentBuilder\Interfaces;

use Reno\Cms\Models\Resource;
use Illuminate\Support\Collection;

interface ContentBlockRepositoryInterface
{
    public function getAll(Resource $resource, string $fieldName): Collection;

    public function getBlocksForResource(int $resourceId, int $resourceFieldId): Collection;
}
