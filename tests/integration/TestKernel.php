<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Ibexa\Bundle\DoctrineMigrations\IbexaDoctrineMigrationsBundle;
use Ibexa\Bundle\Test\Core\IbexaTestCoreBundle;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
use Ibexa\Tests\Integration\DoctrineMigrations\Fixtures\IntegrationTestMigration;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new IbexaTestCoreBundle(),
            new IbexaDoctrineMigrationsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
            ]);

            // Real in-memory SQLite connection so tagged migrations can be instantiated.
            $connectionDef = (new Definition(Connection::class))
                ->setFactory([DriverManager::class, 'getConnection'])
                ->setArguments([['driver' => 'pdo_sqlite', 'memory' => true]]);
            $container->setDefinition('doctrine.migrations.connection', $connectionDef);

            // A distinct connection standing in for ibexa/core's own "ibexa.persistence.connection",
            // to prove IbexaOnlyDependencyFactory really uses it instead of the application's.
            $persistenceConnectionDef = (new Definition(Connection::class))
                ->setFactory([DriverManager::class, 'getConnection'])
                ->setArguments([['driver' => 'pdo_sqlite', 'memory' => true]]);
            $container->setDefinition('ibexa.persistence.connection', $persistenceConnectionDef);

            // Real DependencyFactory (not the doctrine-migrations-bundle's own, which isn't a
            // dependency of this package) so IbexaOnlyDependencyFactory::createFrom() has a real
            // Configuration/Connection/logger to copy from.
            $factoryDef = (new Definition(DependencyFactory::class))
                ->setFactory([DependencyFactory::class, 'fromConnection'])
                ->setArguments([
                    new Definition(ConfigurationArray::class, [[]]),
                    new Definition(ExistingConnection::class, [new Reference('doctrine.migrations.connection')]),
                ]);
            $container->setDefinition('doctrine.migrations.dependency_factory', $factoryDef);

            $container->register('doctrine.migrations.logger', NullLogger::class);

            // Tagged migration wired by RegisterMigrationsPass into ServiceMigrationsRepository.
            $container->register(IntegrationTestMigration::class, IntegrationTestMigration::class)
                ->setAutowired(true)
                ->addTag(IbexaMigrationTag::TAG);

            // Public aliases so the tests can fetch these otherwise-private services
            // directly, following Symfony's test-only alias convention.
            $container->setAlias('test.' . IbexaOnlyMigrationsRepository::SERVICE_ID, IbexaOnlyMigrationsRepository::SERVICE_ID)
                ->setPublic(true);
            $container->setAlias('test.' . IbexaOnlyDependencyFactory::SERVICE_ID, IbexaOnlyDependencyFactory::SERVICE_ID)
                ->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/ibexa_doctrine_migrations_test/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/ibexa_doctrine_migrations_test/logs';
    }
}
