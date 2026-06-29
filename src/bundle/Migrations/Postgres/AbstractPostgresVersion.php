<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations\Postgres;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

abstract class AbstractPostgresVersion extends AbstractMigration implements IbexaMigrationInterface
{
    final public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->isPostgresPlatform(),
            'This migration is PostgreSQL-specific.',
        );

        $this->doUp($schema);
    }

    final public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->isPostgresPlatform(),
            'This migration is PostgreSQL-specific.',
        );

        $this->doDown($schema);
    }

    private function isPostgresPlatform(): bool
    {
        // DBAL 3 uses PostgreSQLPlatform; DBAL 2 uses PostgreSqlPlatform
        $class = class_exists('Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform';

        return $this->connection->getDatabasePlatform() instanceof $class;
    }

    abstract protected function doUp(Schema $schema): void;

    abstract protected function doDown(Schema $schema): void;
}
