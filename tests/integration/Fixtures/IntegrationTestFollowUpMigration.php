<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\DoctrineMigrations\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

/**
 * Has something to do only once {@see IntegrationTestMigration} has created its table, so its SQL shows whether it saw
 * what the migration before it did.
 */
final class IntegrationTestFollowUpMigration extends AbstractMigration implements IbexaMigrationInterface
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
        return 'Integration test migration fixture depending on the one before it';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('integration_test_table')) {
            $this->addSql('INSERT INTO integration_test_table (id) VALUES (1)');
        }
    }

    public function down(Schema $schema): void {}
}
