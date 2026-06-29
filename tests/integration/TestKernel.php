<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\DependencyFactory;
use Ibexa\Bundle\DoctrineMigrations\IbexaDoctrineMigrationsBundle;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
use Ibexa\Tests\Integration\DoctrineMigrations\Fixtures\IntegrationTestMigration;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
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

            // Synthetic DependencyFactory: RegisterMigrationsPass wires setDefinition()
            // calls into it at compile time, but integration tests do not exercise
            // the factory itself — only the ServiceMigrationsRepository wiring.
            $factoryDef = new Definition(DependencyFactory::class);
            $factoryDef->setSynthetic(true);
            $container->setDefinition('doctrine.migrations.dependency_factory', $factoryDef);

            // Real in-memory SQLite connection so tagged migrations can be instantiated.
            $connectionDef = (new Definition(Connection::class))
                ->setFactory([DriverManager::class, 'getConnection'])
                ->setArguments([['driver' => 'pdo_sqlite', 'memory' => true]]);
            $container->setDefinition('doctrine.migrations.connection', $connectionDef);

            $container->register('doctrine.migrations.logger', NullLogger::class);

            // Tagged migration wired by RegisterMigrationsPass into ServiceMigrationsRepository.
            $container->register(IntegrationTestMigration::class, IntegrationTestMigration::class)
                ->setAutowired(true)
                ->addTag(IbexaMigrationTag::TAG);
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
