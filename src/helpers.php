<?php

use Illuminate\Contracts\Support\Htmlable;

if (!function_exists('contentBuilder'))
{
    function contentBuilder(string $builderClass): Htmlable
    {
        /** @var ContentBuilderRendererInterface $renderer */
        $renderer = resolve(ContentBuilderRendererInterface::class);
        return $renderer->render($builderClass);
    }
}
