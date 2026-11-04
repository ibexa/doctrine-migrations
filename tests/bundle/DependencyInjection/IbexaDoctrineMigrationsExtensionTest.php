<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\DependencyInjection;

use Ibexa\Bundle\DoctrineMigrations\DependencyInjection\IbexaDoctrineMigrationsExtension;
use Ibexa\Bundle\DoctrineMigrations\EventListener\TableCollationCheckListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class IbexaDoctrineMigrationsExtensionTest extends TestCase
{
    public function testChecksTheTableCollationByDefault(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->hasDefinition(TableCollationCheckListener::class));
        self::assertSame([], $container->getParameter(IbexaDoctrineMigrationsExtension::TABLE_COLLATION_CHECK_IGNORED_TABLES));
        self::assertSame([], $container->getParameter(IbexaDoctrineMigrationsExtension::TABLE_COLLATION_CHECK_IGNORED_PREFIXES));
    }

    public function testPassesTheIgnoredTables(): void
    {
        $container = $this->load([['table_collation_check' => ['ignored_tables' => ['ezcustom_report', 'ibexa_app_*']]]]);

        self::assertTrue($container->hasDefinition(TableCollationCheckListener::class));
        self::assertSame(
            ['ezcustom_report', 'ibexa_app_*'],
            $container->getParameter(IbexaDoctrineMigrationsExtension::TABLE_COLLATION_CHECK_IGNORED_TABLES)
        );
    }

    public function testPassesTheIgnoredPrefixes(): void
    {
        $container = $this->load([['table_collation_check' => ['ignored_prefixes' => ['ezcustom_', 'ibexa_app_']]]]);

        self::assertSame(
            ['ezcustom_', 'ibexa_app_'],
            $container->getParameter(IbexaDoctrineMigrationsExtension::TABLE_COLLATION_CHECK_IGNORED_PREFIXES)
        );
    }

    public function testLeavesTheCheckOutWhenDisabled(): void
    {
        $container = $this->load([['table_collation_check' => ['enabled' => false]]]);

        self::assertFalse($container->hasDefinition(TableCollationCheckListener::class));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new IbexaDoctrineMigrationsExtension())->load($configs, $container);

        return $container;
    }
}
