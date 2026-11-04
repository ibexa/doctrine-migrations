<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineMigrations\Migrations\AbstractSqlMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

/**
 * Finds nothing to do, like a migration whose table comes from an earlier migration of the same dry run.
 */
final class NothingToDoMigration extends AbstractSqlMigration implements IbexaMigrationInterface
{
    public static function getTargetVersion(): string
    {
        return '5.0.0';
    }

    public static function getCreationDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-02 00:00:00');
    }

    public function getDescription(): string
    {
        return 'Integration test migration fixture with nothing to do';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('integration_test_table')) {
            $this->addSql('DELETE FROM integration_test_table');
        }
    }
}
