<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineMigrations\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\QueryWriter;
use Ibexa\DoctrineMigrations\Migration\LiteralParametersQueryWriter;
use PHPUnit\Framework\TestCase;

final class LiteralParametersQueryWriterTest extends TestCase
{
    /**
     * @dataProvider provideStatementsWithParameters
     *
     * @param class-string<AbstractPlatform> $platformClass
     * @param array<int|string, mixed> $params
     * @param array<int|string, mixed> $types
     */
    public function testWritesParametersAsLiterals(
        string $platformClass,
        string $sql,
        array $params,
        array $types,
        string $expectedSql
    ): void {
        $query = $this->writeOne($platformClass, new Query($sql, $params, $types));

        self::assertSame($expectedSql, $query->getStatement());
        self::assertSame([], $query->getParameters());
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, string, array<int|string, mixed>, array<int|string, mixed>, string}>
     */
    public static function provideStatementsWithParameters(): iterable
    {
        yield 'string and integer' => [
            self::getMysqlPlatformClass(),
            'UPDATE ibexa_taxonomy_entry SET names = ? WHERE id = ?',
            ['{"eng-GB":"O\'Brien"}', 5],
            [ParameterType::STRING, ParameterType::INTEGER],
            'UPDATE ibexa_taxonomy_entry SET names = \'{"eng-GB":"O\'\'Brien"}\' WHERE id = 5',
        ];
        yield 'no types given' => [
            self::getPostgresqlPlatformClass(),
            'UPDATE t SET a = ?',
            ['x'],
            [],
            'UPDATE t SET a = \'x\'',
        ];
        yield 'question mark inside a string literal' => [
            self::getPostgresqlPlatformClass(),
            'UPDATE t SET a = ? WHERE b = \'?\' AND c = \'it\'\'s ?\'',
            ['x'],
            [ParameterType::STRING],
            'UPDATE t SET a = \'x\' WHERE b = \'?\' AND c = \'it\'\'s ?\'',
        ];
        yield 'question mark in a comment and a PostgreSQL cast' => [
            self::getPostgresqlPlatformClass(),
            "UPDATE t SET a = ?::text -- why?\nWHERE id = ?",
            ['x', 2],
            [ParameterType::STRING, ParameterType::INTEGER],
            "UPDATE t SET a = 'x'::text -- why?\nWHERE id = 2",
        ];
        yield 'escaped quote on MySQL' => [
            self::getMysqlPlatformClass(),
            'UPDATE t SET a = ? WHERE b = \'\\\' ?\'',
            ['x'],
            [ParameterType::STRING],
            'UPDATE t SET a = \'x\' WHERE b = \'\\\' ?\'',
        ];
        yield 'null' => [
            self::getMysqlPlatformClass(),
            'UPDATE t SET a = ? WHERE id = ?',
            [null, 1],
            [ParameterType::STRING, ParameterType::INTEGER],
            'UPDATE t SET a = NULL WHERE id = 1',
        ];
        yield 'Doctrine type' => [
            self::getPostgresqlPlatformClass(),
            'UPDATE t SET a = ?',
            [['eng-GB' => 'Name']],
            [Types::JSON],
            'UPDATE t SET a = \'{"eng-GB":"Name"}\'',
        ];
        yield 'named parameters' => [
            self::getMysqlPlatformClass(),
            'UPDATE t SET a = :value WHERE id = :id',
            ['id' => 3, 'value' => 'x'],
            ['id' => ParameterType::INTEGER],
            'UPDATE t SET a = \'x\' WHERE id = 3',
        ];
        yield 'positional parameters in array order, not key order' => [
            self::getMysqlPlatformClass(),
            'UPDATE t SET a = ? WHERE b = ?',
            [1 => 'x', 0 => 'y'],
            [],
            'UPDATE t SET a = \'x\' WHERE b = \'y\'',
        ];
        yield 'positional types counted from 1' => [
            self::getMysqlPlatformClass(),
            'UPDATE t SET a = ? WHERE id = ?',
            ['x', 5],
            [1 => ParameterType::STRING, 2 => ParameterType::INTEGER],
            'UPDATE t SET a = \'x\' WHERE id = 5',
        ];
        yield 'Doctrine boolean type on MySQL' => [
            self::getMysqlPlatformClass(),
            'UPDATE t SET a = ? WHERE b = ?',
            [true, false],
            [Types::BOOLEAN, Types::BOOLEAN],
            'UPDATE t SET a = 1 WHERE b = 0',
        ];
        yield 'Doctrine boolean type on PostgreSQL' => [
            self::getPostgresqlPlatformClass(),
            'UPDATE t SET a = ? WHERE b = ?',
            [true, false],
            [Types::BOOLEAN, Types::BOOLEAN],
            'UPDATE t SET a = true WHERE b = false',
        ];
        yield 'question marks in a quoted identifier and a block comment' => [
            self::getPostgresqlPlatformClass(),
            'SELECT "a?" FROM t /* ? */ WHERE id = ?',
            [5],
            [ParameterType::INTEGER],
            'SELECT "a?" FROM t /* ? */ WHERE id = 5',
        ];
        yield 'question mark in a backtick identifier on MySQL' => [
            self::getMysqlPlatformClass(),
            'SELECT `a?` FROM t WHERE id = ?',
            [5],
            [ParameterType::INTEGER],
            'SELECT `a?` FROM t WHERE id = 5',
        ];
    }

    public function testKeepsParametersBoundWithoutALiteralForm(): void
    {
        $query = $this->writeOne(
            self::getMysqlPlatformClass(),
            new Query('UPDATE t SET a = ? WHERE id = ?', ['binary', 5], [ParameterType::LARGE_OBJECT, ParameterType::INTEGER])
        );

        self::assertSame('UPDATE t SET a = ? WHERE id = ?', $query->getStatement());
        self::assertSame(['binary', 5], $query->getParameters());
    }

    public function testPassesEverythingElseToTheInnerWriter(): void
    {
        $plain = new Query('DELETE FROM t');
        $innerWriter = $this->createMock(QueryWriter::class);
        $innerWriter->expects(self::once())
            ->method('write')
            ->with('upgrade.sql', 'down', ['Version1' => [$plain], 'Version2' => []])
            ->willReturn(true);

        $writer = new LiteralParametersQueryWriter($innerWriter, $this->buildConnection(self::getMysqlPlatformClass()));

        self::assertTrue($writer->write('upgrade.sql', 'down', ['Version1' => [$plain], 'Version2' => []]));
    }

    /**
     * @param class-string<AbstractPlatform> $platformClass
     */
    private function writeOne(
        string $platformClass,
        Query $query
    ): Query {
        $written = [];
        $innerWriter = $this->createMock(QueryWriter::class);
        $innerWriter->method('write')->willReturnCallback(
            static function (
                string $path,
                string $direction,
                array $queriesByVersion
            ) use (&$written): bool {
                $written = $queriesByVersion;

                return true;
            }
        );

        (new LiteralParametersQueryWriter($innerWriter, $this->buildConnection($platformClass)))
            ->write('upgrade.sql', 'up', ['Version1' => [$query]]);

        $queries = $written['Version1'] ?? null;
        self::assertIsArray($queries);
        self::assertCount(1, $queries);
        self::assertInstanceOf(Query::class, $queries[0]);

        return $queries[0];
    }

    /**
     * @param class-string<AbstractPlatform> $platformClass
     */
    private function buildConnection(string $platformClass): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());
        $connection->method('quote')->willReturnCallback(
            static function (string $value): string {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        );

        return $connection;
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getMysqlPlatformClass(): string
    {
        // DBAL 2 uses MySqlPlatform; DBAL 3 renamed it to MySQLPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\MySqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySQLPlatform';
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function getPostgresqlPlatformClass(): string
    {
        // DBAL 2 uses PostgreSqlPlatform; DBAL 3 renamed it to PostgreSQLPlatform
        return class_exists('Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform';
    }
}
