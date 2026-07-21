<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineMigrations\Migrations\AbstractSqlMigration;

final class ConcreteAbstractSqlMigration extends AbstractSqlMigration
{
    public function getDescription(): string
    {
        return 'Test SQL-based multi-platform migration';
    }

    public function up(Schema $schema): void
    {
    }

    public function isMySQLPublic(): bool
    {
        return $this->isMySQL();
    }

    public function isPostgreSQLPublic(): bool
    {
        return $this->isPostgreSQL();
    }

    public function isSqlitePublic(): bool
    {
        return $this->isSqlite();
    }

    public function isPlatformPublic(string $platform): bool
    {
        return $this->isPlatform($platform);
    }

    /**
     * @param non-empty-string $delimiter
     */
    public function addSqlFilePublic(string $file, string $delimiter = self::DEFAULT_SQL_STATEMENT_DELIMITER): void
    {
        $this->addSqlFile($file, $delimiter);
    }
}
