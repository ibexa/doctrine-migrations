<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Tools\Console\MigratorConfigurationFactory;
use Ibexa\Bundle\DoctrineMigrations\EventSubscriber\DryRunSubscriber;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Turns a rolled-back dry run (see {@see DryRunSubscriber}) into a real run for Doctrine: its migrations execute, and
 * the transaction around them is rolled back at the end. The command re-reads its input after the console event, so
 * the option itself can't be changed there.
 */
final class RolledBackDryRunConfigurationFactory implements MigratorConfigurationFactory
{
    public function __construct(
        private readonly MigratorConfigurationFactory $innerFactory,
        private readonly MigrationRunContext $migrationRunContext
    ) {}

    public function getMigratorConfiguration(InputInterface $input): MigratorConfiguration
    {
        $configuration = $this->innerFactory->getMigratorConfiguration($input);

        return $this->migrationRunContext->isRolledBack() ? $configuration->setDryRun(false) : $configuration;
    }
}
