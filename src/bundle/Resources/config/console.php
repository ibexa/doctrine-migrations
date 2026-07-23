<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\DumpSchemaCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\GenerateCommand;
use Doctrine\Migrations\Tools\Console\Command\LatestCommand;
use Doctrine\Migrations\Tools\Console\Command\ListCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\RollupCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Doctrine\Migrations\Tools\Console\Command\SyncMetadataCommand;
use Doctrine\Migrations\Tools\Console\Command\UpToDateCommand;
use Doctrine\Migrations\Tools\Console\Command\VersionCommand;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * A parallel copy of every command doctrine/doctrine-migrations-bundle registers under
 * "doctrine:migrations:*", each wired to IbexaOnlyDependencyFactory::SERVICE_ID instead of the
 * application's own "doctrine.migrations.dependency_factory" -- so these operate exclusively on
 * Ibexa's own tagged migrations (see IbexaMigrationTag), never the application's "migrations/"
 * directory. Every doctrine/migrations command class shares the same
 * DoctrineCommand::__construct(?DependencyFactory, ?string $name) signature, so no new command
 * classes are needed -- only new service definitions pointing at a different DependencyFactory.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('ibexa.doctrine_migrations.diff_command', DiffCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:diff',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:diff'])

        ->set('ibexa.doctrine_migrations.sync_metadata_command', SyncMetadataCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:sync-metadata-storage',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:sync-metadata-storage'])

        ->set('ibexa.doctrine_migrations.versions_command', ListCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:versions',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:versions'])

        ->set('ibexa.doctrine_migrations.current_command', CurrentCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:current',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:current'])

        ->set('ibexa.doctrine_migrations.dump_schema_command', DumpSchemaCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:dump-schema',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:dump-schema'])

        ->set('ibexa.doctrine_migrations.execute_command', ExecuteCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:execute',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:execute'])

        ->set('ibexa.doctrine_migrations.generate_command', GenerateCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:generate',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:generate'])

        ->set('ibexa.doctrine_migrations.latest_command', LatestCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:latest',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:latest'])

        ->set('ibexa.doctrine_migrations.migrate_command', MigrateCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:migrate',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:migrate'])

        ->set('ibexa.doctrine_migrations.rollup_command', RollupCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:rollup',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:rollup'])

        ->set('ibexa.doctrine_migrations.status_command', StatusCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:status',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:status'])

        ->set('ibexa.doctrine_migrations.up_to_date_command', UpToDateCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:up-to-date',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:up-to-date'])

        ->set('ibexa.doctrine_migrations.version_command', VersionCommand::class)
            ->args([
                service(IbexaOnlyDependencyFactory::SERVICE_ID),
                'ibexa:doctrine:migrations:version',
            ])
            ->tag('console.command', ['command' => 'ibexa:doctrine:migrations:version']);
};
