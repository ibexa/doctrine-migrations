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

final class IntegrationTestMigration extends AbstractMigration implements IbexaMigrationInterface
{
    public static function getTargetVersion(): string
    {
        return '5.0.0';
    }

    public static function getCreationDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01 00:00:00');
    }

    public function getDescription(): string
    {
        return 'Integration test migration fixture';
    }

    public function up(Schema $schema): void {}

    public function down(Schema $schema): void {}
}
