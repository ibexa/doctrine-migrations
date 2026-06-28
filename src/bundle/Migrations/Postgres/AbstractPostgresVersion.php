<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations\Postgres;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

abstract class AbstractPostgresVersion extends AbstractMigration implements IbexaMigrationInterface
{
    final public function up(Schema $schema): void
    {
        $this->skipIf(
            !($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform),
            'This migration is PostgreSQL-specific.',
        );

        $this->doUp($schema);
    }

    final public function down(Schema $schema): void
    {
        $this->skipIf(
            !($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform),
            'This migration is PostgreSQL-specific.',
        );

        $this->doDown($schema);
    }

    abstract protected function doUp(Schema $schema): void;

    abstract protected function doDown(Schema $schema): void;
}
