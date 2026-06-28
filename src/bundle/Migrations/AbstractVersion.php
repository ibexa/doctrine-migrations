<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\AbortMigration;

abstract class AbstractVersion extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->ensureDatabasePlatform();

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MySQLPlatform) {
            $this->upForMysql($schema);
        }

        if ($platform instanceof PostgreSQL100Platform) {
            $this->upForPostgresql($schema);
        }
    }

    /**
     * @throws AbortMigration
     */
    final protected function ensureDatabasePlatform(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->abortIf(
            !$platform instanceof MySQLPlatform && !$platform instanceof PostgreSQL100Platform,
            'Migration can only be executed safely on \'mysql\' or \'postgresql\'.',
        );
    }

    abstract protected function upForMysql(Schema $schema): void;

    abstract protected function upForPostgresql(Schema $schema): void;
}
