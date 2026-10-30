<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Ibexa\Bundle\DoctrineMigrations\EventSubscriber\DryRunSubscriber;
use Ibexa\Contracts\DoctrineMigrations\Migrations\MigrationRunContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

final class DryRunSubscriberTest extends TestCase
{
    #[DataProvider('providePlatforms')]
    public function testRollsBackAMigrateDryRunWhereSchemaChangesAreTransactional(
        AbstractPlatform $platform,
        bool $rolledBack
    ): void {
        $context = new MigrationRunContext();
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

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
     * @return iterable<string, array{AbstractPlatform, bool}>
     */
    public static function providePlatforms(): iterable
    {
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), true];
        yield 'SQLite' => [new SqlitePlatform(), true];
        yield 'MySQL' => [new MySQLPlatform(), false];
        yield 'MariaDB' => [new MariaDBPlatform(), false];
    }
}
