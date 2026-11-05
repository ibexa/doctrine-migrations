<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Ibexa\Tests\Integration\DoctrineMigrations\Fixtures\NothingToDoMigration;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Runs "ibexa:doctrine:migrations:migrate" through the console, so the dry-run flag travels the whole way: Doctrine's
 * migrator configuration, its onMigrationsVersionExecuting event, DryRunListener, and AbstractSqlMigration::postUp().
 */
final class DryRunIntegrationTest extends KernelTestCase
{
    private const SKIP_MESSAGE = 'Nothing to do against the schema from before this dry run';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // FrameworkBundle::boot() registers Symfony's ErrorHandler as an exception handler.
        // Kernel::shutdown() does not restore it, so we do it here to keep PHPUnit happy.
        restore_exception_handler();
    }

    public function testDryRunSkipsAMigrationWithNothingToDo(): void
    {
        $display = $this->migrate(['--dry-run' => true]);

        self::assertStringContainsString(self::SKIP_MESSAGE, $display);
    }

    public function testRealRunRecordsAMigrationWithNothingToDo(): void
    {
        $display = $this->migrate([]);

        self::assertStringNotContainsString(self::SKIP_MESSAGE, $display);
        $connection = self::getContainer()->get('test.ibexa.persistence.connection');
        self::assertInstanceOf(Connection::class, $connection);
        self::assertEquals(
            1,
            $connection->fetchOne(
                'SELECT COUNT(*) FROM ibexa_doctrine_migration_versions WHERE version = ?',
                [NothingToDoMigration::class]
            )
        );
        // The application's own versions table is never shared with Ibexa's.
        self::assertEquals(
            0,
            $connection->fetchOne(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
                ['doctrine_migration_versions']
            )
        );
    }

    /**
     * @param array<string, bool> $options
     */
    private function migrate(array $options): string
    {
        self::assertNotNull(self::$kernel);
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();

        $exitCode = $application->run(
            new ArrayInput(['command' => 'ibexa:doctrine:migrations:migrate', '--no-interaction' => true] + $options),
            $output
        );

        $display = $output->fetch();
        self::assertSame(0, $exitCode, $display);

        return $display;
    }
}
