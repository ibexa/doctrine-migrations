<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Common\EventManager;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\EventDispatcher;
use Doctrine\Migrations\FileQueryWriter;
use Doctrine\Migrations\Generator\ConcatenationFileBuilder;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\QueryWriter;
use Doctrine\Migrations\Tools\Console\ConsoleInputMigratorConfigurationFactory;
use Doctrine\Migrations\Tools\Console\MigratorConfigurationFactory;
use Ibexa\Bundle\DoctrineMigrations\EventSubscriber\RolledBackDryRunSubscriber;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Ibexa\DoctrineMigrations\Migration\RecordedMigrations;
use Ibexa\DoctrineMigrations\Migration\RecordedMigrationsQueryWriter;

/**
 * Builds the services the Ibexa-only DependencyFactory takes from here (registered with its setDefinition()), from
 * that factory's own logger, connection, configuration and metadata storage.
 */
final class DependencyFactoryServices
{
    private RecordedMigrations $recordedMigrations;

    private RolledBackDryRunSubscriber $rolledBackDryRunSubscriber;

    private MigrationRunContext $migrationRunContext;

    public function __construct(
        RecordedMigrations $recordedMigrations,
        RolledBackDryRunSubscriber $rolledBackDryRunSubscriber,
        MigrationRunContext $migrationRunContext
    ) {
        $this->recordedMigrations = $recordedMigrations;
        $this->rolledBackDryRunSubscriber = $rolledBackDryRunSubscriber;
        $this->migrationRunContext = $migrationRunContext;
    }

    public function createMigratorConfigurationFactory(DependencyFactory $dependencyFactory): MigratorConfigurationFactory
    {
        return new RolledBackDryRunConfigurationFactory(
            new ConsoleInputMigratorConfigurationFactory($dependencyFactory->getConfiguration()),
            $this->migrationRunContext
        );
    }

    public function createQueryWriter(DependencyFactory $dependencyFactory): QueryWriter
    {
        $storageConfiguration = $dependencyFactory->getConfiguration()->getMetadataStorageConfiguration();

        return new RecordedMigrationsQueryWriter(
            new FileQueryWriter(new ConcatenationFileBuilder(), $dependencyFactory->getLogger()),
            $dependencyFactory->getMetadataStorage(),
            $storageConfiguration instanceof TableMetadataStorageConfiguration ? $storageConfiguration : new TableMetadataStorageConfiguration(),
            $dependencyFactory->getConnection(),
            $this->recordedMigrations
        );
    }

    /**
     * The migrations' own events go to a manager of their own, which has the two subscribers.
     */
    public function createEventDispatcher(DependencyFactory $dependencyFactory): EventDispatcher
    {
        $eventManager = new EventManager();
        $eventManager->addEventSubscriber($this->recordedMigrations);
        $eventManager->addEventSubscriber($this->rolledBackDryRunSubscriber);

        return new EventDispatcher($dependencyFactory->getConnection(), $eventManager);
    }
}
