<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * "ibexa:doctrine:migrations:migrate --dry-run" on SQLite, where schema changes are transactional: the migrations run
 * for real inside a transaction that is rolled back, so each one sees what the ones before it did, and the file gets
 * everything they did.
 */
final class RolledBackDryRunIntegrationTest extends KernelTestCase
{
    private string $file;

    protected function setUp(): void
    {
        self::bootKernel();

        $file = tempnam(sys_get_temp_dir(), 'ibexa_rolled_back_dry_run_');
        self::assertIsString($file);
        $this->file = $file;
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
        // FrameworkBundle::boot() registers Symfony's ErrorHandler as an exception handler.
        // Kernel::shutdown() does not restore it, so we do it here to keep PHPUnit happy.
        restore_exception_handler();
    }

    public function testKeepsNothingAndWritesWhatEachMigrationDidAfterTheOnesBeforeIt(): void
    {
        self::assertNotNull(self::$kernel);
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();

        $exitCode = $application->run(new ArrayInput([
            'command' => 'ibexa:doctrine:migrations:migrate',
            '--dry-run' => true,
            '--write-sql' => $this->file,
            '--no-interaction' => true,
        ]), $output);

        $display = $output->fetch();
        self::assertSame(0, $exitCode, $display);
        self::assertStringContainsString('Rolled back: the database is as it was before the dry run.', $display);

        $connection = self::getContainer()->get('test.ibexa.persistence.connection');
        self::assertInstanceOf(Connection::class, $connection);
        $schemaManager = method_exists($connection, 'createSchemaManager')
            ? $connection->createSchemaManager()
            : $connection->getSchemaManager();
        self::assertInstanceOf(AbstractSchemaManager::class, $schemaManager);
        self::assertFalse($schemaManager->tablesExist(['integration_test_table']));
        self::assertTrue($schemaManager->tablesExist(['doctrine_migration_versions']));
        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions'));

        $sql = file_get_contents($this->file);
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE integration_test_table (id INTEGER NOT NULL);', $sql);
        // only there because the follow-up migration saw the table the one before it created
        self::assertStringContainsString('INSERT INTO integration_test_table (id) VALUES (1);', $sql);
        self::assertMatchesRegularExpression('/INSERT INTO doctrine_migration_versions \(version, executed_at, execution_time\) VALUES \(\'[^\']*IntegrationTestMigration\'/', $sql);
    }
}
