<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\EventDispatcher;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Version\Comparator;
use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
use Ibexa\Bundle\DoctrineMigrations\Configuration\EventDispatcherFactory;
use Ibexa\Bundle\DoctrineMigrations\Configuration\IbexaMigrationConfigurationFactory;
use Ibexa\Bundle\DoctrineMigrations\Configuration\MetadataStorageFactory;
use Ibexa\Bundle\DoctrineMigrations\EventListener\DryRunListener;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use Ibexa\DoctrineMigrations\Migration\ServiceMigrationsRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure(false);

    // Never decorates an inner MigrationsRepository — see IbexaOnlyMigrationsRepository. This is
    // the only ServiceMigrationsRepository this bundle registers: Ibexa's migrations are never
    // combined into the application's own MigrationsRepository -- see RegisterMigrationsPass.
    $services->set(IbexaOnlyMigrationsRepository::SERVICE_ID, ServiceMigrationsRepository::class)
        ->args([
            abstract_arg('migrations service locator'),
            null,
        ]);

    $services->set(IbexaOnlyDependencyFactory::SERVICE_ID, DependencyFactory::class)
        ->factory([DependencyFactory::class, 'fromConnection'])
        ->args([
            inline_service(ExistingConfiguration::class)
                ->args([
                    inline_service(Configuration::class)
                        ->factory([IbexaMigrationConfigurationFactory::class, 'createFromApplicationConfiguration'])
                        ->args([abstract_arg('the application\'s doctrine.migrations.dependency_factory service')]),
                ]),
            inline_service(ExistingConnection::class)
                ->args([service('ibexa.persistence.connection')]),
            inline_service()
                ->factory([abstract_arg('the application\'s doctrine.migrations.dependency_factory service'), 'getLogger']),
        ])
        ->call('setService', [MigrationsRepository::class, service(IbexaOnlyMigrationsRepository::SERVICE_ID)])
        ->call('setService', [Comparator::class, service(IbexaMigrationComparator::class)])
        ->call('setDefinition', [EventDispatcher::class, [service(EventDispatcherFactory::class), 'create']])
        ->call('setDefinition', [MetadataStorage::class, [service(MetadataStorageFactory::class), 'create']]);

    $services->set(IbexaMigrationComparator::class);

    $services->set(MigrationRunContext::class);

    $services->set(DryRunListener::class)
        ->args([service(MigrationRunContext::class)]);

    $services->set(MetadataStorageFactory::class)
        ->args([abstract_arg('the application\'s doctrine.migrations.dependency_factory service')]);

    $services->set(EventDispatcherFactory::class)
        ->args([service(DryRunListener::class)]);
};
