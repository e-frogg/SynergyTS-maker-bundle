<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\Generator;

use DateTimeInterface;
use Efrogg\Synergy\Entity\SynergyEntityInterface;

class CrudFormGenerator extends AbstractCodeGenerator
{
    protected bool $overwriteFiles = false;

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $predefinedAttributes = [
        'id'         => ['ignore' => true, 'disabled' => true],
        'createdAt'  => ['ignore' => true],
        'updatedAt'  => ['ignore' => true],
        'createdBy'  => ['ignore' => true],
        'updatedBy'  => ['ignore' => true],
        'entityName' => ['ignore' => true],
    ];

    /**
     * @param bool $overwriteFiles
     */
    public function setOverwriteFiles(bool $overwriteFiles): void
    {
        $this->overwriteFiles = $overwriteFiles;
    }

    public function generate(string $className): void
    {
        $this->checkDirectory();
        $shortClassName = $this->entityHelper->findEntityName($className);
        $fileName = $this->outputDir . '/' . $this->getEditFormFileName($shortClassName) . '.vue';
        if (file_exists($fileName)) {
            $this->logger->warning('File already exists: ' . $fileName);
            if (!$this->overwriteFiles) {
                $fileName .= '-generated';
                $this->logger->warning('renaming the file to ' . $fileName);
            }
        }

        $content = $this->twig->render('@SynergyMaker/typescript/crud-form.vue.twig', $this->generateDataForTemplate($className));

        file_put_contents($fileName, $content);
    }

    /**
     * @param string $className
     *
     * @return array<string,mixed>
     */
    private function generateDataForTemplate(string $className): array
    {
        $entityClass = $this->entityHelper->findEntityName($className);
        $entityName = lcfirst($entityClass);

        $metadata = $this->classMetadataFactory->getMetadataFor($className);
        $formFields = [];
        $relations = [];
        foreach ($metadata->getAttributesMetadata() as $attributeMetadata) {
            if ($attributeMetadata->isIgnored()) {
                continue;
            }

            $fieldName = $attributeMetadata->getName();

            $types = $this->propertyTypeExtractor->getTypes($className, $fieldName);
            foreach ($types ?? [] as $type) {
                if ($type->isCollection()) {
                    continue;
                }
                $fieldClassName = $type->getClassName();
                $type = $type->getBuiltinType();

                $predefinedAttributes = $this->predefinedAttributes[$fieldName] ?? [];
                if ((bool)($predefinedAttributes['ignore'] ?? false)) {
                    continue;
                }

                //TODO : prefix from config
                $prefix = 'fse';
                $translationLabel = $prefix.".entities.$entityClass.fields.$fieldName";
                if ($fieldClassName && is_a($fieldClassName, SynergyEntityInterface::class, true)) {
                    $relationEntityName = lcfirst($this->entityHelper->findEntityName($fieldClassName));
                    $shortFieldClassName = $this->entityHelper->findEntityName($fieldClassName);
                    $relations[] = [
                        'fieldName'        => $fieldName,
                        'entityName'       => $relationEntityName,
                        'entityClass'      => ucfirst($relationEntityName),
                        'fieldNameKebab' => $this->toKebabCase($shortFieldClassName),
                        'repository'     => lcfirst($shortFieldClassName) . 'Repository',
                        'editFormFile'     => $this->getEditFormFileName($relationEntityName),
                        'translationLabel' => $translationLabel
                    ];
                } else {
                    $formFields[] = [
                        'ignore'           => false,
                        'disabled'         => false,
                        'required'         => true,
                        ...$predefinedAttributes,
                        'fieldName'        => $fieldName,
                        'type'             => $type,
                        'fieldClassName'   => $fieldClassName,
                        'translationLabel' => $translationLabel,
                        'formType'         => $this->getVuetifyFieldTypeFromPhpType($type,$fieldClassName),
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
            'entityName'  => $entityName,
            'entityClass' => $entityClass,
            'formFields'  => $formFields,
            'relations'   => $relations,
        ];
    }

    private function toKebabCase(string $fieldName): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $fieldName));
    }

    private function getEditFormFileName(?string $shortClassName): string
    {
        return 'Sp' . ucfirst($shortClassName) . 'EditForm';
    }

    /**
     * converts a PHP type to a Vuetify form type
     *
     * @param string      $type
     * @param string|null $class
     *
     * @return string|null
     */
    private function getVuetifyFieldTypeFromPhpType(string $type, ?string $class): ?string
    {
        echo "type: $type, class: $class\n";
        if($class !== null) {
            if(is_a($class, DateTimeInterface::class, true)) {
                return 'datetime-local';
            }
        }
        return match($type) {
            'int' => 'number',
            'date', 'datetime' => 'date',
            'boolean' => 'checkbox',
            default => null,
        };
    }
}
