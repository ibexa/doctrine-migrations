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
use Doctrine\Migrations\Version\Comparator;
use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterMigrationsPassTest extends TestCase
{
    public function testPassIsNoOpWhenRepositoryServiceNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.migrations.dependency_factory');

        (new RegisterMigrationsPass())->process($container);

        // No exception — pass silently skipped
        $this->addToAssertionCount(1);
    }

    public function testPassIsNoOpWhenDependencyFactoryNotDefined(): void
    {
        $container = new ContainerBuilder();
        $container->register(ServiceMigrationsRepository::class)
            ->addArgument(new AbstractArgument('locator'))
            ->addArgument(new AbstractArgument('inner'));

        (new RegisterMigrationsPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testTaggedMigrationsAreWiredIntoServiceLocatorArgument(): void
    {
        $container = $this->buildBaseContainer();
        $container->register(IbexaMigrationV500A::class, IbexaMigrationV500A::class)
            ->addTag(IbexaMigrationTag::TAG);
        $container->register(IbexaMigrationV500B::class, IbexaMigrationV500B::class)
            ->addTag(IbexaMigrationTag::TAG);

        (new RegisterMigrationsPass())->process($container);

        $arg0 = $container->getDefinition(ServiceMigrationsRepository::class)->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $arg0);

        $values = $arg0->getValues();
        self::assertArrayHasKey(IbexaMigrationV500A::class, $values);
        self::assertArrayHasKey(IbexaMigrationV500B::class, $values);
    }

    public function testTaggedMigrationsAreAlsoWiredIntoIbexaOnlyRepositoryServiceLocator(): void
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

    public function testIbexaOnlyRepositoryNeverHasAnInnerRepository(): void
    {
        $container = $this->buildBaseContainer();
        $container->register('doctrine.migrations.service_migrations_repository');

        (new RegisterMigrationsPass())->process($container);

        // Arg 1 is a literal null in services.php and the pass never touches it.
        self::assertNull($container->getDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID)->getArgument(1));
    }

    public function testIbexaOnlyRepositoryIsNotRegisteredWithDependencyFactory(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        // Only the combined repository is wired as the active MigrationsRepository —
        // the Ibexa-only one stays a plain, separately-fetchable container service.
        $arg = $this->getSetDefinitionArg($container, MigrationsRepository::class);
        self::assertInstanceOf(ServiceClosureArgument::class, $arg);

        $reference = $arg->getValues()[0];
        self::assertInstanceOf(Reference::class, $reference);
        self::assertSame(ServiceMigrationsRepository::class, (string) $reference);
    }

    public function testPassIsNoOpForIbexaOnlyRepositoryWhenNotDefined(): void
    {
        $container = $this->buildBaseContainer();
        $container->removeDefinition(IbexaOnlyMigrationsRepository::SERVICE_ID);

        (new RegisterMigrationsPass())->process($container);

        // No exception, and the combined repository is still wired as usual.
        $arg0 = $container->getDefinition(ServiceMigrationsRepository::class)->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $arg0);
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

        (new RegisterMigrationsPass())->process($container);

        // No exception, and the combined repository is still wired as usual.
        $arg0 = $container->getDefinition(ServiceMigrationsRepository::class)->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $arg0);
    }

    public function testEmptyServiceLocatorWhenNoTaggedMigrations(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        $arg0 = $container->getDefinition(ServiceMigrationsRepository::class)->getArgument(0);
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

    public function testMigrationsRepositoryIsRegisteredWithDependencyFactory(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        self::assertContains(MigrationsRepository::class, $this->getSetDefinitionTypes($container));
    }

    public function testComparatorIsRegisteredWithDependencyFactoryWhenDefined(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        self::assertContains(Comparator::class, $this->getSetDefinitionTypes($container));
    }

    public function testComparatorIsNotRegisteredWhenNotDefined(): void
    {
        $container = $this->buildBaseContainer(false);

        (new RegisterMigrationsPass())->process($container);

        self::assertNotContains(Comparator::class, $this->getSetDefinitionTypes($container));
    }

    public function testInnerRepositoryIsDecoratedWhenDoctrineServicePresent(): void
    {
        $container = $this->buildBaseContainer();
        $container->register('doctrine.migrations.service_migrations_repository');

        (new RegisterMigrationsPass())->process($container);

        $arg1 = $container->getDefinition(ServiceMigrationsRepository::class)->getArgument(1);
        self::assertInstanceOf(Reference::class, $arg1);
        self::assertSame('doctrine.migrations.service_migrations_repository', (string) $arg1);
    }

    public function testInnerRepositoryIsNotSetWhenDoctrineServiceAbsent(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        $arg1 = $container->getDefinition(ServiceMigrationsRepository::class)->getArgument(1);
        // Explicitly set to null — no inner repository wired
        self::assertNull($arg1);
    }

    public function testMigrationsRepositorySetDefinitionUsesServiceClosure(): void
    {
        $container = $this->buildBaseContainer();

        (new RegisterMigrationsPass())->process($container);

        $arg = $this->getSetDefinitionArg($container, MigrationsRepository::class);
        self::assertInstanceOf(ServiceClosureArgument::class, $arg);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function buildBaseContainer(bool $withComparator = true): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->register(ServiceMigrationsRepository::class)
            ->addArgument(new AbstractArgument('migrations service locator'))
            ->addArgument(new AbstractArgument('optional inner MigrationsRepository'));

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

        if ($withComparator) {
            $container->register(IbexaMigrationComparator::class);
        }

        return $container;
    }

    /** @return list<string> */
    private function getSetDefinitionTypes(ContainerBuilder $container): array
    {
        $types = [];
        foreach ($container->getDefinition('doctrine.migrations.dependency_factory')->getMethodCalls() as $call) {
            if (!is_array($call) || $call[0] !== 'setDefinition') {
                continue;
            }
            $args = $call[1];
            if (is_array($args) && isset($args[0]) && is_string($args[0])) {
                $types[] = $args[0];
            }
        }

        return $types;
    }

    /**
     * @return mixed
     */
    private function getSetDefinitionArg(
        ContainerBuilder $container,
        string $type
    ) {
        foreach ($container->getDefinition('doctrine.migrations.dependency_factory')->getMethodCalls() as $call) {
            if (!is_array($call) || $call[0] !== 'setDefinition') {
                continue;
            }
            $args = $call[1];
            if (is_array($args) && isset($args[0]) && $args[0] === $type) {
                return $args[1] ?? null;
            }
        }

        return null;
    }
}
