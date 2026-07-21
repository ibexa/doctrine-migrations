<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Ibexa\Bundle\DoctrineMigrations\Migrations\DatabasePlatformResolver;
use Ibexa\DoctrineMigrations\Migration\SqlPlatform;

/**
 * Base class for Ibexa migrations that build their SQL per database platform, providing
 * named platform-check helpers ({@see isMySQL()}, {@see isPostgreSQL()}, {@see isSqlite()})
 * instead of raw `$this->platform instanceof ...` checks, and a way to load a platform's SQL
 * from an external file ({@see addSqlFile()}) instead of inlining it in PHP.
 */
abstract class AbstractSqlMigration extends AbstractMigration
{
    public const DEFAULT_SQL_STATEMENT_DELIMITER = '-- ibexa:sql-statement-separator';

    private ?string $resolvedPlatform = null;

    final protected function isMySQL(): bool
    {
        return $this->isPlatform(SqlPlatform::MYSQL);
    }

    final protected function isPostgreSQL(): bool
    {
        return $this->isPlatform(SqlPlatform::POSTGRESQL);
    }

    final protected function isSqlite(): bool
    {
        return $this->isPlatform(SqlPlatform::SQLITE);
    }

    /**
     * @param string $platform one of {@see SqlPlatform}'s constants
     */
    final protected function isPlatform(string $platform): bool
    {
        return $this->resolvePlatform() === $platform;
    }

    /**
     * Loads a file containing one or more SQL statements separated by $delimiter (on its own
     * line) and queues each non-empty statement for execution via {@see addSql()}.
     */
    final protected function addSqlFile(string $file, string $delimiter = self::DEFAULT_SQL_STATEMENT_DELIMITER): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException(sprintf('Unable to read SQL file "%s".', $file));
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read SQL file "%s".', $file));
        }

        foreach (explode($delimiter, $contents) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            $this->addSql($statement);
        }
    }

    private function resolvePlatform(): ?string
    {
        return $this->resolvedPlatform ??= DatabasePlatformResolver::resolve($this->connection);
    }
}
