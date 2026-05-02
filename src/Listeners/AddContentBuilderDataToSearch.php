<?php

namespace Reno\ContentBuilder\Listeners;

use Reno\Cms\Events\Resources\ResourcesReindexing;
use Reno\ContentBuilder\Models\ContentBlockValue;

class AddContentBuilderDataToSearch
{
    public function handle(ResourcesReindexing $event): void
    {
        $resourceIds = $event->resourceIds;
        if ($resourceIds === []) {
            return;
        }

        $blockValues = ContentBlockValue::query()
            ->whereIn('resource_id', $resourceIds)
            ->with(['block', 'resourceField'])
            ->get();

        foreach ($blockValues as $blockValue) {
            $parts = [];

            $blockClass = (string) ($blockValue->block?->class ?? '');
            if ($blockClass !== '') {
                $parts[] = class_basename($blockClass);
            }

            $fieldKey = (string) ($blockValue->resourceField?->key ?? '');
            if ($fieldKey !== '') {
                $parts[] = $fieldKey;
            }

            foreach ($this->extractTexts($blockValue->values ?? []) as $text) {
                $parts[] = $text;
            }

            $searchText = trim(implode(' ', $parts));
            if ($searchText === '') {
                continue;
            }

            $event->addSearchText((int) $blockValue->resource_id, $searchText);
        }
    }

    /**
     * @param array<mixed> $values
     * @return string[]
     */
    private function extractTexts(array $values): array
    {
        $texts = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                foreach ($this->extractTexts($value) as $nestedText) {
                    $texts[] = $nestedText;
                }

                continue;
            }

            if (is_scalar($value)) {
                $text = trim(strip_tags((string) $value));
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }
}
