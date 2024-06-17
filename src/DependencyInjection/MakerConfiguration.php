<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\DependencyInjection;

use Efrogg\SynergyMaker\Generator\CrudFormGenerator;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class MakerConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('synergy_maker');

        /* @phpstan-ignore-next-line */
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->variableNode('bundleName')->defaultValue('@efrogg/synergy')->end()
                    ->variableNode('snippetPrefix')->defaultValue('synergy')->end()
                    ->variableNode('editFormPrefix')->defaultValue(CrudFormGenerator::DEFAULT_EDIT_FORM_PREFIX)->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
