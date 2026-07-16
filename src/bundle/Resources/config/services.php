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
use Doctrine\Migrations\MigrationsRepository;
use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
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

    $services->set(ServiceMigrationsRepository::class)
        ->args([
            abstract_arg('migrations service locator'),
            abstract_arg('optional inner MigrationsRepository'),
        ]);

    // Same repository, but never decorates an inner MigrationsRepository — see IbexaOnlyMigrationsRepository.
    $services->set(IbexaOnlyMigrationsRepository::SERVICE_ID, ServiceMigrationsRepository::class)
        ->args([
            abstract_arg('migrations service locator'),
            null,
        ]);

    // Independent copy of the application's own DependencyFactory — same Configuration and logger,
    // but always running against "ibexa.persistence.connection" (created by ibexa/core) and using
    // the Ibexa-only repository above — see IbexaOnlyDependencyFactory. The two abstract_arg
    // placeholders (both standing in for the application's own DependencyFactory) are replaced by
    // RegisterMigrationsPass.
    $services->set(IbexaOnlyDependencyFactory::SERVICE_ID, DependencyFactory::class)
        ->factory([DependencyFactory::class, 'fromConnection'])
        ->args([
            inline_service(ExistingConfiguration::class)
                ->args([
                    inline_service(Configuration::class)
                        ->factory([abstract_arg('the application\'s doctrine.migrations.dependency_factory service'), 'getConfiguration']),
                ]),
            inline_service(ExistingConnection::class)
                ->args([service('ibexa.persistence.connection')]),
            inline_service()
                ->factory([abstract_arg('the application\'s doctrine.migrations.dependency_factory service'), 'getLogger']),
        ])
        ->call('setService', [MigrationsRepository::class, service(IbexaOnlyMigrationsRepository::SERVICE_ID)]);

    $services->set(IbexaMigrationComparator::class);
};
