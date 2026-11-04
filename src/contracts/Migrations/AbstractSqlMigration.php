<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;
use Psr\Log\LoggerInterface;

/**
 * Base class for Ibexa migrations that build their SQL per database platform, providing
 * named platform-check helpers ({@see isMySQL()}, {@see isMariaDB()}, {@see isPostgreSQL()},
 * {@see isSqlite()}) instead of raw `$this->platform instanceof ...` checks, and a way to load
 * a platform's SQL from an external file ({@see addSqlFile()}) instead of inlining it in PHP.
 *
 * @internal for internal use by Ibexa 1st party packages
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

    /**
     * The table options DBAL wrote into every MySQL and MariaDB CREATE TABLE of the SQL files, from the
     * "ibexa_doctrine_schema.tables.options" a project gets by default.
     */
    private const MYSQL_DEFAULT_TABLE_OPTIONS = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci`';

    private const MYSQL_DEFAULT_TABLE_CHARSET = 'utf8mb4';

    private ?string $resolvedPlatform = null;

    private MigrationRunContext $migrationRunContext;

    /**
     * RegisterMigrationsPass binds $migrationRunContext for every migration service; a migration created without it
     * never runs in a dry run.
     */
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        ?MigrationRunContext $migrationRunContext = null
    ) {
        parent::__construct($connection, $logger);

        $this->migrationRunContext = $migrationRunContext ?? new MigrationRunContext();
    }

    /**
     * True when Doctrine Migrations executes this migration in a dry run, e.g. "ibexa:doctrine:migrations:migrate
     * --dry-run". The migrations before this one haven't executed then: the schema this migration gets, and the
     * database, are as they were before the run.
     */
    final protected function isDryRun(): bool
    {
        return $this->migrationRunContext->isDryRun();
    }

    /**
     * In a dry run, a migration that found nothing to do may only have found that because the migrations before it
     * haven't executed. Skipped, it isn't recorded, and the next migrate checks it against the database.
     */
    public function postUp(Schema $schema): void
    {
        parent::postUp($schema);

        $this->skipIf(
            $this->isDryRun() && $this->getSql() === [],
            'Nothing to do against the schema from before this dry run; the next migrate checks it against the database.'
        );
    }

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
     * On MySQL and MariaDB, the file's default table options become the charset and collation configured in
     * "ibexa_doctrine_schema.tables.options", which the SchemaBuilderEvent install applies to its tables too.
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

            $this->addSql($this->applyConfiguredTableOptions($statement));
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

    /**
     * Like DBAL, a charset configured without a collation gets the charset's "_unicode_ci" one. With neither configured
     * the file's options stay, instead of DBAL's utf8 ones.
     */
    private function applyConfiguredTableOptions(string $statement): string
    {
        $charset = $this->migrationRunContext->getTableCharset();
        $collation = $this->migrationRunContext->getTableCollation();

        if ((!$this->isMySQL() && !$this->isMariaDB()) || ($charset === null && $collation === null)) {
            return $statement;
        }

        $charset ??= self::MYSQL_DEFAULT_TABLE_CHARSET;
        $collation ??= $charset . '_unicode_ci';

        return str_replace(
            self::MYSQL_DEFAULT_TABLE_OPTIONS,
            sprintf('DEFAULT CHARACTER SET %s COLLATE `%s`', $charset, $collation),
            $statement
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
