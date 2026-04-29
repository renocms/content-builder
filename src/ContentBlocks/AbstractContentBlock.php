<?php

namespace Reno\ContentBuilder\ContentBlocks;

use Reno\ContentBuilder\Interfaces\ContentBlockInterface;

abstract class AbstractContentBlock implements ContentBlockInterface
{
    public function getViewComposer(): ?string
    {
        return null;
    }
}
