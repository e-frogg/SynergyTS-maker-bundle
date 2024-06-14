<?php

namespace Efrogg\SynergyMaker\Event;

class EntityClassGeneratedEvent
{


    /** @var array<array<mixed>>  */
    protected array $imports = [];

    /**
     * @param array<string> $implements
     */
    public function __construct(
        private readonly string $className,
        private readonly  string $shortClassName,
        private ?string $extends = null,
        private array $implements = [],
    ) {
    }

    /**
     * @param string|null $extends
     */
    public function setExtends(?string $extends): void
    {
        $this->extends = $extends;
    }

    /**
     * @return string|null
     */
    public function getExtends(): ?string
    {
        return $this->extends;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function getShortClassName(): string
    {
        return $this->shortClassName;
    }

    /**
     * @param array<string>       $named
     */
    public function addImport(string $importFile, ?string $defaultImport = null, array $named = []): void
    {
        $this->imports[] = func_get_args();
    }


    /**
     * @return array<string>
     */
    public function getImplements(): array
    {
        return $this->implements;
    }

    public function addImplements(string $string): void
    {
        $this->implements[] = $string;
    }

    /**
     * @return array<array<mixed>>
     */
    public function getImports(): array
    {
        return $this->imports;
    }
}
