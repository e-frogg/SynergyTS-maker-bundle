<?php

namespace Efrogg\SynergyMaker\DependencyInjection;

use Efrogg\SynergyMaker\Generator\CrudFormGenerator;
use Efrogg\SynergyMaker\Generator\EntityClassGenerator;
use Efrogg\SynergyMaker\Generator\RepositoryGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SynergyMakerExtension extends Extension
{

    const array GENERATORS = [
        CrudFormGenerator::class,
        EntityClassGenerator::class,
        RepositoryGenerator::class,
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        // load the configuration to inject the services
        $configuration = new MakerConfiguration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        // inject configuration
        foreach (self::GENERATORS as $generatorClass) {
            $container->getDefinition($generatorClass)
                      ->setArgument('$synergyConfig', $config);
        }
    }
}
