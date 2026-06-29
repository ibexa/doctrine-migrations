<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Migrations\Mysql;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

abstract class AbstractMysqlVersion extends AbstractMigration implements IbexaMigrationInterface
{
    final public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->isMysqlPlatform(),
            'This migration is MySQL-specific.',
        );

        $this->doUp($schema);
    }

    final public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->isMysqlPlatform(),
            'This migration is MySQL-specific.',
        );

        $this->doDown($schema);
    }

    abstract protected function doUp(Schema $schema): void;

    abstract protected function doDown(Schema $schema): void;

    private function isMysqlPlatform(): bool
    {
        // DBAL 3 uses MySQLPlatform; DBAL 2 uses MySqlPlatform
        $class = class_exists('Doctrine\\DBAL\\Platforms\\MySQLPlatform')
            ? 'Doctrine\\DBAL\\Platforms\\MySQLPlatform'
            : 'Doctrine\\DBAL\\Platforms\\MySqlPlatform';

        return $this->connection->getDatabasePlatform() instanceof $class;
    }
}
