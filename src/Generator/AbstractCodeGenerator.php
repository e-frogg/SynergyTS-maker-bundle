<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\Generator;

use Efrogg\Synergy\Helper\EntityHelper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Twig\Environment;

abstract class AbstractCodeGenerator
{
    /**
     * @param array<string,mixed> $synergyConfig
     */
    public function __construct(
        protected readonly string $outputDir,
        protected readonly array $synergyConfig,
        protected readonly ClassMetadataFactoryInterface $classMetadataFactory,
        protected readonly PropertyTypeExtractorInterface $propertyTypeExtractor,
        protected readonly PropertyAccessorInterface $propertyAccessor,
        protected readonly Environment $twig,
        protected readonly LoggerInterface $logger,
        protected readonly EntityHelper $entityHelper,
        protected readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    abstract public function generate(string $className): void;


    public function checkDirectory(): void
    {
        if (!is_dir($this->outputDir)) {
            if (!mkdir($concurrentDirectory = $this->outputDir, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
    }
}
