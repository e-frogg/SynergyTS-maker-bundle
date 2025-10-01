<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigTools extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('lcfirst', 'lcfirst'),
            new TwigFilter('toKebabCase', [$this, 'toKebabCase']),
        ];
    }

    public function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $string));
    }
}
