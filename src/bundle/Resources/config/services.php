<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
use Ibexa\DoctrineMigrations\Migration\ServiceMigrationsRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;

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

    $services->set(IbexaMigrationComparator::class);
};
