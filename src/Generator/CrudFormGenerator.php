<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\Generator;

use Efrogg\Synergy\Entity\SynergyEntityInterface;
use Efrogg\Synergy\Mapping\SynergyFormField;

class CrudFormGenerator extends AbstractCodeGenerator
{
    public const string DEFAULT_SNIPPET_PREFIX = 'synergy';
    public const string DEFAULT_EDIT_FORM_PREFIX = 'Sy';
    protected bool $overwriteFiles = false;

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $predefinedAttributes = [
        'id' => ['ignore' => true, 'disabled' => true],
        'createdAt' => ['ignore' => true],
        'updatedAt' => ['ignore' => true],
        'createdBy' => ['ignore' => true],
        'updatedBy' => ['ignore' => true],
        'entityName' => ['ignore' => true],
    ];

    public function setOverwriteFiles(bool $overwriteFiles): void
    {
        $this->overwriteFiles = $overwriteFiles;
    }

    public function generate(string $className): void
    {
        $this->checkDirectory();
        $shortClassName = $this->entityHelper->findEntityName($className)
            ?? throw new \RuntimeException('failed to find entity name');
        $fileName = $this->outputDir.'/'.$this->getEditFormFileName($shortClassName).'.vue';
        if (file_exists($fileName)) {
            $this->logger->warning('File already exists: '.$fileName);
            if (!$this->overwriteFiles) {
                $fileName .= '-generated';
                $this->logger->warning('renaming the file to '.$fileName);
            }
        }

        $content = $this->twig->render('@SynergyMaker/typescript/crud-form.vue.twig', $this->generateDataForTemplate($className));

        file_put_contents($fileName, $content);
    }

    /**
     * @param class-string $className
     *
     * @return array<string,mixed>
     */
    private function generateDataForTemplate(string $className): array
    {
        $entityClass = $this->entityHelper->findEntityName($className)
            ?? throw new \RuntimeException('failed to find entity name for '.$className);
        $entityName = lcfirst($entityClass);

        $metadata = $this->classMetadataFactory->getMetadataFor($className);
        $formFields = [];
        $relations = [];
        $reflexionClass = new \ReflectionClass($className);
        foreach ($metadata->getAttributesMetadata() as $attributeMetadata) {
            if ($attributeMetadata->isIgnored()) {
                continue;
            }

            $fieldName = $attributeMetadata->getName();
            if ($reflexionClass->hasProperty($fieldName)) {
                $reflectionProperty = $reflexionClass->getProperty($fieldName);
            } else {
                $reflectionProperty = null;
            }

            $types = $this->propertyTypeExtractor->getTypes($className, $fieldName);
            foreach ($types ?? [] as $type) {
                if ($type->isCollection()) {
                    continue;
                }
                // check form configuration parameters
                $formAttributes = $reflectionProperty?->getAttributes(SynergyFormField::class) ?? [];
                foreach ($formAttributes as $formAttribute) {
                    $formAttributeInstance = $formAttribute->newInstance();
                    if ($formAttributeInstance->ignore) {
                        continue 2;
                    }
                }
                $fieldClassName = $type->getClassName();
                $type = $type->getBuiltinType();

                $predefinedAttributes = $this->predefinedAttributes[$fieldName] ?? [];
                if ((bool) ($predefinedAttributes['ignore'] ?? false)) {
                    continue;
                }

                $prefix = $this->getSnippetPrefix();
                $translationLabel = $prefix.".entities.$entityClass.fields.$fieldName";
                if ($fieldClassName && is_a($fieldClassName, SynergyEntityInterface::class, true)) {
                    $fieldEntityNam = $this->entityHelper->findEntityName($fieldClassName)
                        ?? throw new \RuntimeException('failed to find entity name');
                    $relationEntityName = lcfirst($fieldEntityNam);
                    $shortFieldClassName = $fieldEntityNam;
                    $relations[] = [
                        'fieldName' => $fieldName,                                                         // budget
                        'entityName' => $relationEntityName,
                        'entityClass' => ucfirst($relationEntityName),                                  // Budget
                        'fieldNameKebab' => $this->toKebabCase($shortFieldClassName),
                        'repository' => lcfirst($shortFieldClassName).'Repository',
                        'editFormFile' => $this->getEditFormFileName($relationEntityName),
                        'translationLabel' => $translationLabel,
                        'foreignKey' => $fieldName.'Id',                                                  // budgetId
                    ];
                } else {
                    $formFields[] = [
                        'ignore' => false,
                        'disabled' => false,
                        'required' => true,
                        ...$predefinedAttributes,
                        'fieldName' => $fieldName,
                        'type' => $type,
                        'fieldClassName' => $fieldClassName,
                        'translationLabel' => $translationLabel,
                        'formType' => $this->getVuetifyFieldTypeFromPhpType($type, $fieldClassName),
                    ];
                }
            }
        }

        //        $fields = $this->entityHelper->getFields($className);
        //        $formFields = $this->entityHelper->getFormFields($className);
        //        $formFields = array_map(fn($field) => $this->generateFormField($field), $formFields);
        //        $formFields = array_filter($formFields);
        //        $formFields = array_values($formFields);
        //        $formFields = implode("\n", $formFields);
        return [
            'entityName' => $entityName,
            'entityClass' => $entityClass,
            'formFields' => $formFields,
            'relations' => $relations,
            'synergyConfig' => $this->synergyConfig,
        ];
    }

    private function toKebabCase(string $fieldName): string
    {
        return strtolower(
            preg_replace('/(?<!^)[A-Z]/', '-$0', $fieldName)
            ?? throw new \RuntimeException('failed to convert to kebab case')
        );
    }

    private function getEditFormFileName(string $shortClassName): string
    {
        return $this->getEditFormPrefix().ucfirst($shortClassName).'EditForm';
    }

    /**
     * converts a PHP type to a Vuetify form type.
     */
    private function getVuetifyFieldTypeFromPhpType(string $type, ?string $class): ?string
    {
        //        echo "type: $type, class: $class\n";
        if (null !== $class) {
            if (is_a($class, \DateTimeInterface::class, true)) {
                return 'datetime-local';
            }
        }

        return match ($type) {
            'int' => 'number',
            'date', 'datetime' => 'date',
            'bool' => 'checkbox',
            default => null,
        };
    }

    private function getEditFormPrefix(): string
    {
        return $this->synergyConfig['editFormPrefix'] ?? self::DEFAULT_EDIT_FORM_PREFIX;
    }

    /**
     * @return mixed|string
     */
    private function getSnippetPrefix(): mixed
    {
        return $this->synergyConfig['snippetPrefix'] ?? self::DEFAULT_SNIPPET_PREFIX;
    }
}
