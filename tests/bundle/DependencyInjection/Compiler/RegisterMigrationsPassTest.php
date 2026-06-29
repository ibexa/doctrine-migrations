<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Version\Comparator;
use Ibexa\Bundle\DoctrineMigrations\Comparator\IbexaMigrationComparator;
use Ibexa\Bundle\DoctrineMigrations\DependencyInjection\Compiler\RegisterMigrationsPass;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
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

        $container->register('doctrine.migrations.dependency_factory');
        $container->register('doctrine.migrations.connection');
        $container->register('doctrine.migrations.logger');

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
