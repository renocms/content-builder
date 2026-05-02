<?php

namespace Reno\ContentBuilder\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Reno\Cms\Services\ClassesDiscoverer;
use Reno\ContentBuilder\Containers\ContentBlockContainer;
use Reno\ContentBuilder\Containers\ContentBuilderContainer;
use Reno\ContentBuilder\Interfaces\ContentBlockInterface;
use Reno\ContentBuilder\Interfaces\ContentBuilderInterface;
use Reno\ContentBuilder\Interfaces\ContentBuilderRepositoryInterface;
use Reno\ContentBuilder\Models\ContentBlock;
use Reno\ContentBuilder\Models\ContentBuilder;

class ContentBuilderRepository implements ContentBuilderRepositoryInterface
{
    private const CACHE_LOCK = 'content-builders';

    /**
     * @var array<class-string<ContentBuilderInterface>, ContentBuilderInterface>|null
     */
    private static ?array $rawBuildersCache = null;

    /**
     * @var array<class-string<ContentBlockInterface>, ContentBlockInterface>|null
     */
    private static ?array $rawBlocksCache = null;

    /**
     * @var array<class-string<ContentBuilderInterface>, ContentBuilderContainer>
     */
    private static array $buildersByClassCache = [];

    /**
     * @var array<string, ContentBuilder>|null
     */
    private static ?array $builderModelsCache = null;

    /**
     * @var array<int, class-string<ContentBuilderInterface>>|null
     */
    private static ?array $builderClassesByIdCache = null;

    /**
     * @var array<string, ContentBlock>|null
     */
    private static ?array $blockModelsCache = null;

    public function __construct(
        private readonly ClassesDiscoverer $classesDiscoverer,
    )
    {
    }

    public function getAll(): Collection
    {
        $result = Collection::make();

        foreach (array_keys($this->getRawBuilders()) as $className) {
            $container = $this->resolveBuilderByClassName($className);
            $result->put($container->getId(), $container);
        }

        return $result;
    }

    public function findById(int $id): ContentBuilderContainer
    {
        $this->initBuilderModelsCache();

        if (!isset(self::$builderClassesByIdCache[$id])) {
            throw new \RuntimeException("Builder set with ID {$id} not found.");
        }

        return $this->resolveBuilderByClassName(self::$builderClassesByIdCache[$id]);
    }

    public function findByClass(string $className): ContentBuilderContainer
    {
        return $this->resolveBuilderByClassName($className);
    }

    public function clearCache(): void
    {
        self::$rawBuildersCache = null;
        self::$rawBlocksCache = null;
        self::$buildersByClassCache = [];
        self::$builderModelsCache = null;
        self::$builderClassesByIdCache = null;
        self::$blockModelsCache = null;
    }

    /**
     * @return array<class-string<ContentBuilderInterface>, ContentBuilderInterface>
     */
    private function getRawBuilders(): array
    {
        $this->initRawDefinitionsCache();
        return self::$rawBuildersCache;
    }

    /**
     * @return array<class-string<ContentBlockInterface>, ContentBlockInterface>
     */
    private function getRawBlocks(): array
    {
        $this->initRawDefinitionsCache();
        return self::$rawBlocksCache;
    }

    private function initRawDefinitionsCache(): void
    {
        if (self::$rawBuildersCache !== null && self::$rawBlocksCache !== null) {
            return;
        }

        $lock = Cache::lock(self::CACHE_LOCK, 30);

        if (!$lock->block(5)) {
            throw new \RuntimeException('Content builders are being locked');
        }

        try {
            $buildersByClass = [];
            $blocksByClass = [];
            $directory = config('content-builder.path', app_path('Reno/ContentBuilder'));

            foreach ($this->classesDiscoverer->discover($directory) as $className) {
                if (is_subclass_of($className, ContentBuilderInterface::class)) {
                    /** @var ContentBuilderInterface $builder */
                    $builder = app($className);
                    $buildersByClass[$className] = $builder;
                }

                if (is_subclass_of($className, ContentBlockInterface::class)) {
                    /** @var ContentBlockInterface $block */
                    $block = app($className);
                    $blocksByClass[$className] = $block;
                }
            }

            ksort($buildersByClass);
            ksort($blocksByClass);
            self::$rawBuildersCache = $buildersByClass;
            self::$rawBlocksCache = $blocksByClass;
        } finally {
            $lock->release();
        }
    }

    private function resolveBuilderByClassName(string $className): ContentBuilderContainer
    {
        if (isset(self::$buildersByClassCache[$className])) {
            return self::$buildersByClassCache[$className];
        }

        $rawBuilders = $this->getRawBuilders();

        if (!isset($rawBuilders[$className])) {
            throw new \RuntimeException("Builder set {$className} not found.");
        }

        $builder = $rawBuilders[$className];
        $model = $this->getModelForContentBuilder($builder::class);
        $container = new ContentBuilderContainer(
            id: $model->getKey(),
            set: $builder,
        );

        $rawBlocks = $this->getRawBlocks();

        foreach ($builder->getBlocks() as $blockClass) {
            $block = $rawBlocks[$blockClass] ?? null;

            if (!$block instanceof ContentBlockInterface) {
                continue;
            }

            $container->addBlock(new ContentBlockContainer(
                id: $this->getModelForContentBlock($blockClass)->getKey(),
                block: $block,
            ));
        }

        if (self::$builderModelsCache === null) {
            self::$builderModelsCache = [];
        }
        if (self::$builderClassesByIdCache === null) {
            self::$builderClassesByIdCache = [];
        }

        self::$buildersByClassCache[$className] = $container;
        self::$builderModelsCache[$className] = $model;
        self::$builderClassesByIdCache[$container->getId()] = $className;

        return $container;
    }

    private function initBuilderModelsCache(): void
    {
        if (self::$builderModelsCache !== null) {
            return;
        }

        $models = ContentBuilder::query()->get();
        self::$builderModelsCache = $models->keyBy('class')->all();
        self::$builderClassesByIdCache = $models
            ->keyBy('id')
            ->map(fn (ContentBuilder $builder) => $builder->class)
            ->toArray();
    }

    private function initBlockModelsCache(): void
    {
        if (self::$blockModelsCache !== null) {
            return;
        }

        self::$blockModelsCache = ContentBlock::query()->get()->keyBy('class')->all();
    }

    private function getModelForContentBuilder(string $className): ContentBuilder
    {
        $this->initBuilderModelsCache();

        if (isset(self::$builderModelsCache[$className])) {
            return self::$builderModelsCache[$className];
        }

        $contentBuilder = ContentBuilder::query()->updateOrCreate([
            'class' => $className,
        ]);

        if (self::$builderModelsCache === null) {
            self::$builderModelsCache = [];
        }
        if (self::$builderClassesByIdCache === null) {
            self::$builderClassesByIdCache = [];
        }

        self::$builderModelsCache[$className] = $contentBuilder;
        self::$builderClassesByIdCache[$contentBuilder->getKey()] = $className;

        return $contentBuilder;
    }

    private function getModelForContentBlock(string $className): ContentBlock
    {
        $this->initBlockModelsCache();

        if (isset(self::$blockModelsCache[$className])) {
            return self::$blockModelsCache[$className];
        }

        $contentBlock = ContentBlock::query()->updateOrCreate([
            'class' => $className,
        ]);

        if (self::$blockModelsCache === null) {
            self::$blockModelsCache = [];
        }

        self::$blockModelsCache[$className] = $contentBlock;

        return $contentBlock;
    }
}
