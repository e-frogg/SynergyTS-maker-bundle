<?php

declare(strict_types=1);

namespace Efrogg\SynergyMaker\Command;

use Efrogg\Synergy\Helper\EntityHelper;
use Efrogg\SynergyMaker\Generator\CrudFormGenerator;
use Efrogg\SynergyMaker\Generator\TypescriptGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'synergy:generate',
    description: 'Generate typescript entity',
)]
class GenerateEntityCommand extends Command
{
    public function __construct(
        private readonly EntityHelper $entityHelper,
        private readonly TypescriptGenerator $typescriptGenerator,
        private readonly CrudFormGenerator $crudFormGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to generate a typescript entity...')
            ->addArgument('name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The name of the entity')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Generate all entities')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Generate everything (default false)')
            ->addOption('entity', null, InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE, 'Generate entity typescript (default false)')
            ->addOption('form', null, InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE, 'Generate entity vue form (default false)')
            ->addOption('overwrite-crud', null, InputOption::VALUE_NONE, 'writes over existing CRUD vue files')
            ->addOption('ui', null, InputOption::VALUE_REQUIRED, 'UI framework to use for generated forms (vuetify|primevue)', 'vuetify')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->configureGenerator($input);
        $io = new SymfonyStyle($input, $output);
        /** @var array<string> $nameArgument */
        $nameArgument = $input->getArgument('name');
        if ((bool) $input->getOption('all')) {
            $entityClasses = $this->entityHelper->getEntityClasses();
            if (empty($entityClasses)) {
                $io->error('No entity found. Please be sure to tag all your entities with the #[SynergyEntity] annotation');

                return self::FAILURE;
            }
            foreach ($entityClasses as $shortName => $fqn) {
                $this->typescriptGenerator->build($fqn);
                $io->info(sprintf('You have generated the entity "%s"', $shortName));
            }
            $io->success('You have generated all entities');
        } elseif (0 === count($nameArgument)) {
            while ($name = $io->askQuestion(
                new Question('Please enter the name of the entity')->setAutocompleterValues($this->entityHelper->getEntityNames())
            )) {
                $className = $this->entityHelper->findEntityClass($name)
                    ?? throw new \InvalidArgumentException('Entity '.$name.' not found');
                $this->typescriptGenerator->build($className);
                $io->info(sprintf('You have generated the entity "%s"', $name));
            }
        } else {
            foreach ($nameArgument as $entityName) {
                $className = $this->entityHelper->findEntityClass($entityName);
                if (null === $className) {
                    $io->error(sprintf('Entity "%s" not found', $entityName));
                    continue;
                }
                $this->typescriptGenerator->build($className);
                $io->success(sprintf('You have generated the entity "%s"', $entityName));
            }
        }

        return self::SUCCESS;
    }

    private function configureGenerator(InputInterface $input): void
    {
        if ((bool) $input->getOption('overwrite-crud')) {
            $this->crudFormGenerator->setOverwriteFiles(true);
        }

        // UI framework selection for CRUD form generation
        /** @var string|null $uiOpt */
        $uiOpt = $input->getOption('ui');
        $ui = is_string($uiOpt) ? strtolower($uiOpt) : 'vuetify';
        if (!in_array($ui, ['vuetify', 'primevue'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid --ui option "%s". Allowed values: vuetify, primevue', $uiOpt));
        }
        $this->crudFormGenerator->setUiFramework($ui);

        $full = (bool) $input->getOption('full');
        $doForm = (bool) ($input->getOption('form') ?? $full);
        $doEntity = (bool) ($input->getOption('entity') ?? $full);
        $this->typescriptGenerator->activateGenerators(
            entity: $doEntity,
            crudForm: $doForm,                             // false par défaut
        );
    }
}
