<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;

/**
 * Base class for Ibexa migrations that build their SQL per database platform, providing
 * named platform-check helpers ({@see isMySQL()}, {@see isMariaDB()}, {@see isPostgreSQL()},
 * {@see isSqlite()}) instead of raw `$this->platform instanceof ...` checks, and a way to load
 * a platform's SQL from an external file ({@see addSqlFile()}) instead of inlining it in PHP.
 */
abstract class AbstractSqlMigration extends AbstractMigration
{
    public const DEFAULT_SQL_STATEMENT_DELIMITER = '-- ibexa:sql-statement-separator';

    /**
     * MariaDB platform classes, ordered from the most to the least specific DBAL version. Only the
     * class name matters, so a candidate missing from the installed DBAL is simply never matched.
     */
    private const MARIADB_PLATFORM_CLASS_CANDIDATES = [
        // DBAL >= 3.3, base class of every MariaDB platform
        'Doctrine\\DBAL\\Platforms\\MariaDBPlatform',
        // DBAL 2.x and 3.0 - 3.2
        'Doctrine\\DBAL\\Platforms\\MariaDb1027Platform',
    ];

    private const PLATFORMS_BY_DATABASE_PLATFORM_NAME = [
        DatabasePlatformName::MYSQL => SqlPlatform::MYSQL,
        DatabasePlatformName::POSTGRESQL => SqlPlatform::POSTGRESQL,
        DatabasePlatformName::SQLITE => SqlPlatform::SQLITE,
    ];

    private ?string $resolvedPlatform = null;

    /**
     * MySQL and MariaDB commit implicitly on every DDL statement, so a transaction can't protect a
     * migration there. Worse, Doctrine's executor then finds its transaction already gone and skips commit(),
     * leaving the connection's transaction nesting level raised - so a later transaction on the same
     * connection is only "nested", and its rollback no longer rolls anything back.
     *
     * Override to return true in a migration that doesn't change the schema on MySQL or MariaDB.
     */
    public function isTransactional(): bool
    {
        return !$this->isMySQL() && !$this->isMariaDB();
    }

    /**
     * True on MySQL only, not on MariaDB - see {@see isMariaDB()}.
     */
    final protected function isMySQL(): bool
    {
        return $this->isPlatform(SqlPlatform::MYSQL);
    }

    final protected function isMariaDB(): bool
    {
        return $this->isPlatform(SqlPlatform::MARIADB);
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
     *
     * @param non-empty-string $delimiter
     */
    final protected function addSqlFile(
        string $file,
        string $delimiter = self::DEFAULT_SQL_STATEMENT_DELIMITER
    ): void {
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

    /**
     * Aborts the migration with a clear error message unless the current connection is one of
     * the given platform identifiers (see {@see SqlPlatform}'s constants) — e.g.
     * `$this->abortIfUnsupportedPlatform(SqlPlatform::MYSQL, SqlPlatform::MARIADB, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);`.
     * MariaDB is a platform of its own, so a migration that supports it has to list it.
     *
     * @param string ...$supportedPlatforms one or more of {@see SqlPlatform}'s constants
     */
    final protected function abortIfUnsupportedPlatform(string ...$supportedPlatforms): void
    {
        foreach ($supportedPlatforms as $platform) {
            if ($this->isPlatform($platform)) {
                return;
            }
        }

        $this->abortIf(
            true,
            sprintf(
                'Unsupported database platform. This migration only supports: %s.',
                implode(', ', $supportedPlatforms)
            )
        );
    }

    private function resolvePlatform(): ?string
    {
        return $this->resolvedPlatform ??= self::resolveSqlPlatform($this->connection->getDatabasePlatform());
    }

    /**
     * {@see DatabasePlatformResolver} reports MariaDB as MySQL, so MariaDB is recognized here first.
     */
    private static function resolveSqlPlatform(AbstractPlatform $platform): ?string
    {
        foreach (self::MARIADB_PLATFORM_CLASS_CANDIDATES as $candidate) {
            // is_a() with an object subject compares against the already loaded class hierarchy,
            // so a candidate absent from the installed DBAL never matches and is never autoloaded.
            if (is_a($platform, $candidate)) {
                return SqlPlatform::MARIADB;
            }
        }

        $name = DatabasePlatformResolver::resolveName($platform);

        return $name === null ? null : self::PLATFORMS_BY_DATABASE_PLATFORM_NAME[$name] ?? null;
    }
}
