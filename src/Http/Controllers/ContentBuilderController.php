<?php

namespace Reno\ContentBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Reno\ContentBuilder\Http\Requests\ContentBuilderIndexRequest;
use Reno\ContentBuilder\Http\Resources\ContentBlockValueResource;
use Reno\ContentBuilder\Interfaces\ContentBlockRepositoryInterface;

class ContentBuilderController
{
    public function index(ContentBlockRepositoryInterface $repository, ContentBuilderIndexRequest $request): JsonResponse
    {
        $blocks = $repository->getBlocksForResource(
            resourceId: (int) $request->input('resource_id'),
            resourceFieldId: (int) $request->input('resource_field_id'),
        );

        return ContentBlockValueResource::collection($blocks)->response();
    }
}
