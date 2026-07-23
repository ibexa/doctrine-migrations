<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigrationsRepository;
use Ibexa\Bundle\DoctrineMigrations\DependencyInjection\Compiler\RegisterMigrationsPass;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyMigrationsRepository;
use Ibexa\DoctrineMigrations\Migration\ServiceMigrationsRepository;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\IbexaMigrationV500A;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\IbexaMigrationV500B;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterMigrationsPassTest extends TestCase
{
    public function testPassIsNoOpWhenIbexaOnlyRepositoryServiceNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.migrations.dependency_factory');

        (new RegisterMigrationsPass())->process($container);

        // No exception — pass silently skipped
        $this->addToAssertionCount(1);
    }

    public function testTaggedMigrationsAreWiredIntoIbexaOnlyRepositoryServiceLocator(): void
    {
        $container = $this->buildBaseContainer();
        $container->register(IbexaMigrationV500A::class, IbexaMigrationV500A::class)
            ->addTag(IbexaMigrationTag::TAG);
        $container->register(IbexaMigrationV500B::class, IbexaMigrationV500B::class)
            ->addTag(IbexaMigrationTag::TAG);

        (new RegisterMigrationsPass())->process($container);

        $arg0 = $container->getDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $arg0);

        $values = $arg0->getValues();
        self::assertArrayHasKey(IbexaMigrationV500A::class, $values);
        self::assertArrayHasKey(IbexaMigrationV500B::class, $values);
    }

    public function testEmptyServiceLocatorWhenNoTaggedMigrations(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        $arg0 = $container->getDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $arg0);
        self::assertEmpty($arg0->getValues());
    }

    public function testConnectionAndLoggerBindingsAreAddedToTaggedMigrations(): void
    {
        $container = $this->buildBaseContainer();
        $container->register(IbexaMigrationV500A::class, IbexaMigrationV500A::class)
            ->addTag(IbexaMigrationTag::TAG);

        (new RegisterMigrationsPass())->process($container);

        $bindings = $container->getDefinition(IbexaMigrationV500A::class)->getBindings();

        self::assertArrayHasKey(Connection::class, $bindings);
        self::assertArrayHasKey(LoggerInterface::class, $bindings);

        $connectionBinding = $bindings[Connection::class];
        self::assertInstanceOf(BoundArgument::class, $connectionBinding);

        $loggerBinding = $bindings[LoggerInterface::class];
        self::assertInstanceOf(BoundArgument::class, $loggerBinding);
    }

    public function testApplicationDependencyFactoryReceivesNoSetDefinitionCalls(): void
    {
        $container = $this->buildBaseContainer();
        $container->register(IbexaMigrationV500A::class, IbexaMigrationV500A::class)
            ->addTag(IbexaMigrationTag::TAG);

        (new RegisterMigrationsPass())->process($container);

        // The application's own DependencyFactory is never touched — Ibexa's migrations
        // are visible exclusively through IbexaOnlyMigrationsRepository/IbexaOnlyDependencyFactory.
        self::assertSame(
            [],
            $container->getDefinition('doctrine.migrations.dependency_factory')->getMethodCalls()
        );
    }

    public function testIbexaOnlyDependencyFactoryConfigurationIsFetchedFromApplicationDependencyFactory(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        $existingConfigurationDefinition = $container->getDefinition(IbexaOnlyDependencyFactory::SERVICE_ID)->getArgument(0);
        self::assertInstanceOf(Definition::class, $existingConfigurationDefinition);

        $configurationDefinition = $existingConfigurationDefinition->getArgument(0);
        self::assertInstanceOf(Definition::class, $configurationDefinition);

        $factory = $configurationDefinition->getFactory();
        self::assertIsArray($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('doctrine.migrations.dependency_factory', (string) $factory[0]);
        self::assertSame('getConfiguration', $factory[1]);
    }

    public function testIbexaOnlyDependencyFactoryLoggerIsFetchedFromApplicationDependencyFactory(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        $loggerDefinition = $container->getDefinition(IbexaOnlyDependencyFactory::SERVICE_ID)->getArgument(2);
        self::assertInstanceOf(Definition::class, $loggerDefinition);

        $factory = $loggerDefinition->getFactory();
        self::assertIsArray($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('doctrine.migrations.dependency_factory', (string) $factory[0]);
        self::assertSame('getLogger', $factory[1]);
    }

    public function testIbexaOnlyDependencyFactoryUsesTheIbexaPersistenceConnection(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        // Wired directly in services.php — the pass doesn't need to touch it.
        $existingConnectionDefinition = $container->getDefinition(IbexaOnlyDependencyFactory::SERVICE_ID)->getArgument(1);
        self::assertInstanceOf(Definition::class, $existingConnectionDefinition);

        $connectionArg = $existingConnectionDefinition->getArgument(0);
        self::assertInstanceOf(Reference::class, $connectionArg);
        self::assertSame('ibexa.persistence.connection', (string) $connectionArg);
    }

    public function testPassIsNoOpForIbexaOnlyDependencyFactoryWhenNotDefined(): void
    {
        $container = $this->buildBaseContainer();
        $container->removeDefinition(IbexaOnlyDependencyFactory::SERVICE_ID);
        $container->register(IbexaMigrationV500A::class, IbexaMigrationV500A::class)
            ->addTag(IbexaMigrationTag::TAG);

        (new RegisterMigrationsPass())->process($container);

        // No exception, and the Ibexa-only repository is still wired as usual.
        $arg0 = $container->getDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $arg0);
        self::assertArrayHasKey(IbexaMigrationV500A::class, $arg0->getValues());
    }

    public function testPassIsNoOpWhenApplicationDependencyFactoryNotDefined(): void
    {
        $container = $this->buildBaseContainer();
        $container->removeDefinition('doctrine.migrations.dependency_factory');

        (new RegisterMigrationsPass())->process($container);

        // No exception, and the Ibexa-only repository is still wired as usual.
        $arg0 = $container->getDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $arg0);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function buildBaseContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->register(IbexaOnlyMigrationsRepository::SERVICE_ID, ServiceMigrationsRepository::class)
            ->addArgument(new AbstractArgument('migrations service locator'))
            ->addArgument(null);

        $container->register(IbexaOnlyDependencyFactory::SERVICE_ID, DependencyFactory::class)
            ->setFactory([DependencyFactory::class, 'fromConnection'])
            ->setArguments([
                (new Definition(ExistingConfiguration::class))
                    ->setArguments([
                        (new Definition(Configuration::class))
                            ->setFactory([new AbstractArgument('the application\'s doctrine.migrations.dependency_factory service'), 'getConfiguration']),
                    ]),
                new Definition(ExistingConnection::class, [new Reference('ibexa.persistence.connection')]),
                (new Definition())
                    ->setFactory([new AbstractArgument('the application\'s doctrine.migrations.dependency_factory service'), 'getLogger']),
            ])
            ->addMethodCall('setService', [MigrationsRepository::class, new Reference(IbexaOnlyMigrationsRepository::SERVICE_ID)]);

        $container->register('doctrine.migrations.dependency_factory');
        $container->register('doctrine.migrations.connection');
        $container->register('doctrine.migrations.logger');
        $container->register('ibexa.persistence.connection');

        return $container;
    }
}
