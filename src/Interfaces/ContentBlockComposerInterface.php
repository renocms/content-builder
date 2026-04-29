<?php

namespace Reno\ContentBuilder\Interfaces;

interface ContentBlockComposerInterface
{
    public function compose(array $data): array;
}
