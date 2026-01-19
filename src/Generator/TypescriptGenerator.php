<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\Generator;

use Efrogg\Synergy\Entity\SynergyEntityInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TypescriptGenerator
{
    private bool $generateEntity = true;

    private bool $addRepository = true;

    private bool $generateCrudForm = true;

    public function __construct(
        private readonly EntityClassGenerator $entityGenerator,
        private readonly RepositoryGenerator $repositoryGenerator,
        private readonly CrudFormGenerator $crudFormGenerator,
    ) {
    }

    public function setGenerateEntity(bool $generateEntity): void
    {
        $this->generateEntity = $generateEntity;
    }

    public function setAddRepository(bool $addRepository): void
    {
        $this->addRepository = $addRepository;
    }

    public function setGenerateCrudForm(bool $generateCrudForm): void
    {
        $this->generateCrudForm = $generateCrudForm;
    }

    /**
     * @param class-string<SynergyEntityInterface> $className
     *
     * @throws \ReflectionException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function build(string $className): void
    {
        if (!is_a($className, SynergyEntityInterface::class, true)) {
            throw new \InvalidArgumentException('The class must be a subclass of SynergyEntityInterface');
        }

        if ($this->generateEntity) {
            $this->entityGenerator->generate($className);
        }

        if ($this->addRepository) {
            $this->repositoryGenerator->generate($className);
        }

        if ($this->generateCrudForm) {
            $this->crudFormGenerator->generate($className);
        }
    }

    public function activateGenerators(bool $entity = false, bool $repository = false, bool $crudForm = false): void
    {
        $this->generateEntity = $entity;
        $this->addRepository = $repository;
        $this->generateCrudForm = $crudForm;
    }
}
