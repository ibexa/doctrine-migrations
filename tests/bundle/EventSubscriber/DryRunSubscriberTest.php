<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Ibexa\Bundle\DoctrineMigrations\EventSubscriber\DryRunSubscriber;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

final class DryRunSubscriberTest extends TestCase
{
    /**
     * @dataProvider providePlatforms
     *
     * @param class-string<AbstractPlatform> $platformClass
     */
    public function testRollsBackAMigrateDryRunWhereSchemaChangesAreTransactional(
        string $platformClass,
        bool $rolledBack
    ): void {
        $context = new MigrationRunContext();
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new $platformClass());

        $command = new Command('ibexa:doctrine:migrations:migrate');
        $command->addOption('dry-run', null, InputOption::VALUE_NONE);
        $command->addOption('write-sql', null, InputOption::VALUE_OPTIONAL, '', false);
        $input = new ArrayInput(['--dry-run' => true, '--write-sql' => 'upgrade.sql'], $command->getDefinition());

        (new DryRunSubscriber($context, $connection))->onCommand(new ConsoleCommandEvent($command, $input, new BufferedOutput()));

        self::assertSame($rolledBack, $context->isRolledBack());
        self::assertSame(!$rolledBack, $context->isDryRun());
        self::assertSame(!$rolledBack, $input->isInteractive());
        self::assertTrue($context->writesSql());
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, bool}>
     */
    public static function providePlatforms(): iterable
    {
        // DBAL 2 class names first, then their DBAL 3 renames
        yield 'PostgreSQL' => [self::firstExisting('PostgreSqlPlatform', 'PostgreSQLPlatform'), true];
        yield 'SQLite' => [self::firstExisting('SqlitePlatform', 'SQLitePlatform'), true];
        yield 'MySQL' => [self::firstExisting('MySqlPlatform', 'MySQLPlatform'), false];
        yield 'MariaDB' => [self::firstExisting('MariaDb1027Platform', 'MariaDBPlatform'), false];
    }

    /**
     * @return class-string<AbstractPlatform>
     */
    private static function firstExisting(string ...$names): string
    {
        foreach ($names as $name) {
            $class = 'Doctrine\\DBAL\\Platforms\\' . $name;
            if (is_subclass_of($class, AbstractPlatform::class)) {
                return $class;
            }
        }

        self::fail(sprintf('The installed Doctrine DBAL has none of %s.', implode(', ', $names)));
    }
}
