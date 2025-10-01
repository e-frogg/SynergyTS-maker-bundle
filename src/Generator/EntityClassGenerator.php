<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\Generator;

use Efrogg\Synergy\Entity\SynergyEntityInterface;
use Efrogg\Synergy\Serializer\Normalizer\EntityNormalizer;
use Efrogg\SynergyMaker\Event\EntityClassGeneratedEvent;
use Efrogg\SynergyMaker\Exception\PatternNotFoundException;
use Symfony\Component\PropertyInfo\Type;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

// use App\Entity\Interface\ScheduledEventInterface;
// use App\Entity\Interface\SimulationAwareInterface;

class EntityClassGenerator extends AbstractCodeGenerator
{
    private const string GETTER_TYPE_NORMAL = 'normal';
    private const string GETTER_TYPE_RELATION_ID = 'relationId';
    private const string GETTER_TYPE_RELATION = 'relation';
    /**
     * @var array<string,array<string,string|array<string>>>
     */
    protected array $existingExtends;
    /**
     * @var array<string>
     */
    protected array $existingImports;
    /**
     * @var array<string,array<string>>
     */
    protected array $existingGetters;
    /**
     * @var string[]
     */
    protected array $existingProperties;
    protected string $baseContent;
    protected string $generatingClassName;

    /**
     * @var string[]
     */
    private array $skippedAttributes = [
        'id',
        'createdAt',
        'updatedAt',
        'createdBy',
        'updatedBy',
        'entityName',
        'scheduledAt',
        'executedAt',
        '_entityName',
    ];

    /**
     * @throws SyntaxError
     * @throws \ReflectionException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws \Exception
     */
    public function generate(string $className): void
    {
        $shortClassName = new \ReflectionClass($className)->getShortName();
        $fileName = $this->outputDir.'/'.$shortClassName.'.ts';
        $this->generatingClassName = $shortClassName;

        $this->checkDirectory();
        if (!file_exists($fileName)) {
            // generate the base file

            $this->baseContent = $this->generateBaseContent($className, $shortClassName);
        } else {
            $content = file_get_contents($fileName);
            if (false === $content) {
                throw new \RuntimeException('file is not readable : '.$fileName);
            }
            $this->baseContent = $content;
        }

        $metadata = $this->classMetadataFactory->getMetadataFor($className);
        // read existing
        $this->readExistingProperties();
        $this->readExistingGetters();
        $this->readExistingImports();
        $this->readExtends();

        $newExtends = null;
        $newImplements = [];

        /** @var EntityClassGeneratedEvent $event */
        $event = $this->eventDispatcher->dispatch(new EntityClassGeneratedEvent($className, $shortClassName, $newExtends, $newImplements));
        foreach ($event->getImports() as $import) {
            $this->addImport(...$import);
        }
        $this->needExtends($event->getShortClassName(), $event->getExtends(), $event->getImplements());

        foreach ($metadata->getAttributesMetadata() as $attributesMetadatum) {
            if ($attributesMetadatum->isIgnored() || in_array($attributesMetadatum->getName(), $this->skippedAttributes, true)) {
                $this->logger->debug('ignored : '.$attributesMetadatum->getName());
                continue;
            }
            $attributeName = $attributesMetadatum->getName();
            if (in_array($attributeName, $this->existingProperties, true)) {
                $this->logger->info('exists : '.$attributeName);
                continue;
            }

            $types = $this->propertyTypeExtractor->getTypes($className, $attributeName);
            if (null === $types) {
                $this->logger->error('no type for : '.$attributeName);
                continue;
            }
            foreach ($types as $type) {
                if (is_a($type->getClassName(), SynergyEntityInterface::class, true)) {
                    $typeScriptRelation = $this->entityHelper->findEntityName($type->getClassName()) ?? throw new \Exception('no entity found for '.$type->getClassName());
                    // find the relation id type
                    $this->addPropertyWithGetterSetter($attributeName.'Id', 'string', self::GETTER_TYPE_RELATION_ID, true, ['relationName' => $attributeName]); // TODO : type int | string selon le cas !
                    $this->addPropertyWithGetterSetter($attributeName, $typeScriptRelation, self::GETTER_TYPE_RELATION);
                } elseif (EntityNormalizer::isRelationCollection($type)) {
                    $this->logger->warning('skip Collection : '.$attributeName);
                } else {
                    try {
                        $this->addProperty($attributeName, $this->convertType($type->getBuiltinType(), $type->getClassName()), $type->isNullable());
                    } catch (\Exception $e) {
                        //                        dump($e);
                        $this->logger->warning($attributeName.' : '.$e->getMessage());
                    }
                }
            }
        }

        file_put_contents($fileName, $this->baseContent);
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    private function generateBaseContent(string $className, string $shortClassName): string
    {
        return $this->twig->render('@SynergyMaker/typescript/baseEntity.ts.twig', [
            'className' => $className,
            'shortClassName' => $shortClassName,
            'synergyConfig' => $this->synergyConfig,
        ]);
    }

    private function readExistingProperties(): void
    {
        //        $propertyPattern = '#(public|protected|private)\s+(?<name>\w+)(\s*:\s*(?<type>[a-zA-Z_\[\]|-{?:}]+))?([,; ][^()]*)?$#Um';
        $propertyPattern = '#(public|protected|private)\s+(?<name>\w+)\s*:\s*([^;]*)[,; ]$#Um';
        preg_match_all($propertyPattern, $this->baseContent, $matches);
        $this->existingProperties = $matches['name'];
    }

    private function readExistingGetters(): void
    {
        $getterSetterPattern = '#^(?<start>.*)(get|set)\s+(?<name>\w+)\s*\(.*$#Um';
        preg_match_all($getterSetterPattern, $this->baseContent, $matches);
        $this->existingGetters = ['get' => [], 'set' => []];
        foreach ($matches[2] as $k => $getOrSet) {
            $isCommented = (bool) preg_match('#^\s*//.*$#Um', $matches['start'][$k]);
            if ($isCommented) {
                $this->logger->debug('getter is commented : '.$matches['name'][$k]);
                continue;
            }
            $this->existingGetters[$getOrSet][] = $matches['name'][$k];
        }
    }

    private function readExistingImports(): void
    {
        $pattern = '#\s*import\s+(.*),?({(.*)})?\s+from\s+"(.*)";?$#Usm';
        preg_match_all($pattern, $this->baseContent, $matches);
        $this->existingImports = [];
        foreach ($matches[4] as $k => $importFile) {
            $this->existingImports[$importFile] = [
                'default' => $matches[1][$k],
                'named' => array_map(fn ($repo) => trim($repo, "\r\n\t "), explode(',', $matches[3][$k])),
            ];
        }
    }

    private function readExtends(): void
    {
        $pattern = '#export(.*)class\s+(?<name>\w+)(\s+extends\s+(?<extends>\w+))?(\s+implements\s+(?<implements>[a-zA-Z, ]*))?\s*{#Usm';
        preg_match_all($pattern, $this->baseContent, $matches);
        $this->existingExtends = [];
        foreach ($matches['name'] as $k => $className) {
            $this->existingExtends[$className] = [
                'extends' => $matches['extends'][$k] ?: null,
                'implements' => array_map(fn ($repo) => trim($repo, "\r\n\t "), explode(',', $matches['implements'][$k])),
            ];
        }
    }

    /**
     * @param array<string> $named
     *
     * @throws \Exception
     */
    private function addImport(string $importFile, ?string $defaultImport = null, array $named = []): void
    {
        $alreadyExisted = isset($this->existingImports[$importFile]);
        $this->existingImports[$importFile] ??= [
            'default' => null,
            'named' => [],
        ];
        $tmp = $this->existingImports[$importFile];
        $tmp['default'] = $defaultImport ?? $tmp['default'];
        $tmp['named'] = array_filter(array_map('trim', array_unique([...$tmp['named'], ...$named])));

        // generating the import string
        $defaultString = $tmp['default'] ?? '';
        if (count($tmp['named']) > 0) {
            $namedString = '{'.implode(', ', $tmp['named']).'}';
        } else {
            $namedString = '';
        }
        $separator = '' === $defaultString || '' === $namedString ? '' : ', ';

        // import ScheduledEventEntity from "./Common/ScheduledEventEntity";
        $importString = "import {$defaultString}{$separator}{$namedString} from \"$importFile\";";

        // injecting the import in the file
        if ($alreadyExisted) {
            // replace the line
            $this->baseContent = preg_replace('#import\s+([^;]*),?({([^;]*)})?\s+from\s+"'.$importFile.'";?$#Usm', $importString, $this->baseContent)
                ?? $this->baseContent;
        } else {
            // add the line
            if (!preg_match('#^(.*--imports--.*)$#Um', $this->baseContent."\n$1")) {
                throw new PatternNotFoundException('pattern --imports-- introuvable');
            }
            $this->baseContent = preg_replace(
                '#^(.*--imports--.*)$#Um',
                $importString."\n$1",
                $this->baseContent
            ) ?? $this->baseContent;
        }
    }

    /**
     * @param array<string>|null $implements
     */
    private function needExtends(string $className, ?string $extends = null, ?array $implements = []): void
    {
        $allowOverwrite = [
            'Entity' => ['ScheduledEventEntity', 'SimulationEntity', 'TimeEventEntity'],
            'TimeEventEntity' => ['ScheduledEventEntity'],
        ];
        /** @var string $alreadyExtends */
        $alreadyExtends = $this->existingExtends[$className]['extends'] ?? 'Entity';

        $extends ??= $alreadyExtends;

        if (((bool) $alreadyExtends) && $extends !== $alreadyExtends) {
            // changing the extends
            if (isset($allowOverwrite[$alreadyExtends]) && in_array($extends, $allowOverwrite[$alreadyExtends], true)) {
                $this->logger->debug('overwriting extends : '.$extends);
            } else {
                $this->logger->error('the class already extends '.$alreadyExtends.' and not '.$extends);
                // rollback the extends
                $extends = $alreadyExtends;
            }
        }

        $implements = array_filter(array_unique([...$implements, ...$this->existingExtends[$className]['implements'] ?? []]));

        $extendsString = ((bool) $extends) ? 'extends '.$extends : '';
        $implementsString = count($implements) > 0 ? 'implements '.implode(', ', $implements) : '';

        $string = "export default class {$className} {$extendsString} {$implementsString} {";

        // replacing if exists
        $this->baseContent = preg_replace(
            '#export(.*)class\s+'.$className.'\s+.*{#Usm',
            $string,
            $this->baseContent
        );
    }

    private function addProperty(string $propertyName, string $type, bool $nullable = false, string $visibility = 'public'): void
    {
        if ($this->propertyExists($propertyName)) {
            $this->logger->info('property '.$propertyName.' already exists');

            return;
        }
        if ($this->getterExists($propertyName)) {
            $this->logger->info('property '.$propertyName.' already exists as getter');

            return;
        }
        if ($nullable) {
            $type .= ' | null';
            $default = 'null';
        } else {
            $default = $this->getTypeDefault($type);
        }

        // conversions
        if ('Date' === $type) {
            $this->addTypedProperty($propertyName, 'date');
        }

        $string = "    {$visibility} {$propertyName}: {$type} = {$default};";

        $this->logger->info('adding property : '.$propertyName.' of type '.$type);
        // replacing if exists
        if (preg_match("#^.*(public|private) {$propertyName}\s*:#mU", $this->baseContent, $matches)) {
            $this->baseContent = preg_replace("#^.*(public|private) {$propertyName}\s*:.*$#mU", $string, $this->baseContent) ?? $this->baseContent;

            return;
        }

        // injecting the property in the file
        if (!preg_match('#^(.*---properties---.*)$#Um', $this->baseContent."\n$1")) {
            throw new \Exception('pattern ---properties--- introuvable');
        }

        $this->baseContent = preg_replace(
            '#^(.*---properties---.*)$#Um',
            $string."\n$1",
            $this->baseContent
        ) ?? $this->baseContent;

        $this->logger->info('adding property : '.$propertyName.' of type '.$type);
    }

    /**
     * @throws PatternNotFoundException
     */
    private function addPropertyWithGetterSetter(string $propertyName, string $type, string $getterType = self::GETTER_TYPE_NORMAL, bool $nullable = true, array $additionalParameters = []): void
    {
        if ($this->getterExists($propertyName)) {
            $this->logger->debug('getter already exists : '.$propertyName);

            return;
        }

        $privatePropertyName = '_'.$propertyName;
        $this->addProperty($privatePropertyName, $type, $nullable, 'private');

        //        $repository = lcfirst($type) . 'Repository';
        //        $this->addImport('../RepositoryManager', null, [$repository]);
        if ($type !== $this->generatingClassName && 'string' !== $type && 'number' !== $type && 'boolean' !== $type && 'Date' !== $type && 'object' !== $type) {
            // avoid importing itself (Category > Category
            $this->addImport('./'.$type, $type);
        }

        $typeString = $nullable ? $type.' | null' : $type;

        switch ($getterType) {
            case self::GETTER_TYPE_NORMAL:
                $this->addGetter($propertyName, $typeString);
                $this->addSetter($propertyName, $typeString);
                break;
            case self::GETTER_TYPE_RELATION_ID:
                $this->addGetter($propertyName, $typeString);
                $this->addSetter($propertyName, $typeString, [    // set the relation _budgetId
                    '_'.$additionalParameters['relationName'] => 'null',                             // reset the relation _budget = null
                ]);
                break;
            case self::GETTER_TYPE_RELATION:
                $this->addMethod(
                    <<<EOT
                            public get {$propertyName}(): {$typeString} {
                                return this.{$privatePropertyName} ??= this.getRelation({$type},this.{$propertyName}Id);
                            }
                        EOT
                );
                break;
        }
        //        $getterString = match($getterType) {
        //
        //            self::GETTER_TYPE_NORMAL => <<<EOT
        //    public get {$propertyName}(): {$typeString} {
        //        return this.{$privatePropertyName};
        //    }
        //    public set {$propertyName}(value: {$typeString}) {
        //        this.{$privatePropertyName} = value;
        //    }
        // EOT,
        //            self::GETTER_TYPE_RELATION_ID => <<<EOT
        // //    public get {$propertyName}(): {$typeString} {
        // //        return this.{$privatePropertyName};
        // //    }
        //    public set {$propertyName}(value: {$typeString}) {
        //        this.{$privatePropertyName} = value;
        //        this._{$additionalParameters['relationName']} = null;
        //    }
        // EOT,
        //            self::GETTER_TYPE_RELATION => <<<EOT
        //    public get {$propertyName}(): {$typeString} {
        //        return this.{$privatePropertyName} ??= this.getRelation({$type},this.{$propertyName}Id);
        //    }
        // EOT
        //        };
        //        $this->addMethod($getterString);
    }

    /**
     * @throws PatternNotFoundException
     */
    private function addGetter(string $propertyName, string $typeString): void
    {
        if ($this->getterExists($propertyName)) {
            $this->logger->info('getter already exists : '.$propertyName);

            return;
        }

        $privatePropertyName = '_'.$propertyName;
        $this->addMethod(<<<EOT
                public get {$propertyName}(): {$typeString} {
                    return this.{$privatePropertyName};
                }
            EOT);
    }

    /**
     * @param array<string,string> $additionalSets
     *
     * @throws PatternNotFoundException
     */
    private function addSetter(string $propertyName, string $typeString, array $additionalSets = []): void
    {
        $privatePropertyName = '_'.$propertyName;
        $additionalSetStrings = [];
        foreach ($additionalSets as $key => $value) {
            $additionalSetStrings[] = "        this.{$key} = {$value};";
        }
        $additionalSetString = implode("\n", $additionalSetStrings);
        $this->addMethod(<<<EOT
                public set {$propertyName}(value: {$typeString}) {
                    this.{$privatePropertyName} = value;
            $additionalSetString
                }
            EOT);
    }

    private function convertType(string $builtinType, ?string $className): string
    {
        return match ($builtinType) {
            'string' => 'string',
            'int', 'float' => 'number',
            'bool' => 'boolean',
            'object' => $this->convertObjectClass($className),
            'array' => 'object',
            default => throw new \Exception('unknown type : '.$builtinType),
        };
    }

    private function convertObjectClass(?string $className): string
    {
        if ($className) {
            if (is_a($className, \DateTimeInterface::class, true)) {
                return 'Date';
            }

            //            return 'object';
        }
        throw new \Exception('could not convert '.$className);
    }

    private function getTypeDefault(string $type): string
    {
        return match ($type) {
            'string' => "''",
            'number' => '0',
            'boolean' => 'true',
            'Date' => 'new Date()',
            'array' => '[]',
            'object' => '{}',
            default => throw new \Exception('no default value for type '.$type)
        };
    }

    private function addTypedProperty(string $propertyName, string $string): void
    {
        // todo : objectif :
        //    protected static _properties= {
        //        'startDate': 'date',
        //        'endDate': 'date'
        //    }
    }

    /**
     * @throws PatternNotFoundException
     */
    private function addMethod(string $getterString): void
    {
        // injecting the getter in the file
        // at the end of the class
        if (!preg_match('#^(.*---methods---.*)$#Um', $this->baseContent."\n$1")) {
            throw new PatternNotFoundException('pattern ---methods--- introuvable');
        }
        $this->baseContent = preg_replace(
            '#^(.*---methods---.*)$#Um',
            $getterString."\n$1",
            $this->baseContent
        ) ?? $this->baseContent;
    }

    private function getterExists(string $propertyName): bool
    {
        return in_array($propertyName, $this->existingGetters['get'], true);
    }

    private function propertyExists(string $propertyName): bool
    {
        return in_array($propertyName, $this->existingProperties, true);
    }
}
