<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\IrreversibleYamlSqlFileMigrationFixture;
use Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures\YamlSqlFileMigrationFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class YamlSqlFileMigrationTraitTest extends TestCase
{
    public function testAddUpSqlFromYamlFileQueuesOneQueryPerParameterSet(): void
    {
        $connection = $this->createMock(Connection::class);
        $migration = new YamlSqlFileMigrationFixture($connection, new NullLogger());

        $migration->up($this->createMock(Schema::class));

        $queries = $migration->getSql();

        // definitions.yaml declares 5 "up" entries: 1 without parameters, 1 with a single
        // parameter set, 1 with two parameter sets, 1 with positional parameters, and
        // 1 inline `sql` entry, i.e. 1 + 1 + 2 + 1 + 1 = 6 queued queries in total.
        self::assertCount(6, $queries);

        self::assertSame('SELECT 1;', $queries[0]->getStatement());
        self::assertSame([], $queries[0]->getParameters());

        self::assertSame(['name' => 'my_setting', 'value' => '42'], $queries[1]->getParameters());

        self::assertSame(['name' => 'setting_a', 'value' => '1'], $queries[2]->getParameters());
        self::assertSame(['name' => 'setting_b', 'value' => '2'], $queries[3]->getParameters());

        self::assertSame([1, 2], $queries[4]->getParameters());

        self::assertSame('DELETE FROM setting WHERE name = :name', $queries[5]->getStatement());
        self::assertSame(['name' => 'obsolete_setting'], $queries[5]->getParameters());
    }

    public function testAddDownSqlFromYamlFileQueuesDownStatements(): void
    {
        $connection = $this->createMock(Connection::class);
        $migration = new YamlSqlFileMigrationFixture($connection, new NullLogger());

        $migration->down($this->createMock(Schema::class));

        $queries = $migration->getSql();

        // up-and-down.yaml declares a single "down" entry.
        self::assertCount(1, $queries);
        self::assertSame('DROP TABLE t;', $queries[0]->getStatement());
    }

    public function testAddDownSqlFromYamlFileThrowsWhenYamlFileDeclaresNoDownSection(): void
    {
        $connection = $this->createMock(Connection::class);
        $migration = new IrreversibleYamlSqlFileMigrationFixture($connection, new NullLogger());

        $this->expectException(IrreversibleMigration::class);

        $migration->down($this->createMock(Schema::class));
    }
}
