<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\SQL\Parser\Visitor;
use Doctrine\DBAL\Types\Type;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;
use Psr\Log\LoggerInterface;

/**
 * Base class for Ibexa migrations that build their SQL per database platform, providing
 * named platform-check helpers ({@see isMySQL()}, {@see isMariaDB()}, {@see isPostgreSQL()},
 * {@see isSqlite()}) instead of raw `$this->platform instanceof ...` checks, and a way to load
 * a platform's SQL from an external file ({@see addSqlFile()}) instead of inlining it in PHP.
 */
abstract class AbstractSqlMigration extends AbstractMigration
{
    public const DEFAULT_SQL_STATEMENT_DELIMITER = '-- ibexa:sql-statement-separator';

    private ?SqlPlatform $resolvedPlatform = null;

    /**
     * RegisterMigrationsPass binds $migrationRunContext for every migration service; a migration created without it
     * never runs in a dry run.
     */
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        private readonly MigrationRunContext $migrationRunContext = new MigrationRunContext()
    ) {
        parent::__construct($connection, $logger);
    }

    /**
     * True during "ibexa:doctrine:migrations:migrate --dry-run". The migrations before this one haven't executed
     * then: the schema this migration gets, and the database, are as they were before the run.
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
     * Doctrine writes a statement into the --write-sql file as it is, so one with bound parameters ends up there with
     * its "?" placeholders, and the database client stops at it. When the run writes its SQL (--dry-run or
     * --write-sql), the parameters are written into the statement instead, quoted by the connection. Parameters with
     * no literal form here (arrays, binary and large objects) stay bound.
     *
     * @param mixed[] $params
     * @param mixed[] $types
     */
    protected function addSql(
        string $sql,
        array $params = [],
        array $types = [],
    ): void {
        $literalSql = $params !== [] && $this->writesSql() ? $this->withLiteralParameters($sql, $params, $types) : null;
        if ($literalSql !== null) {
            parent::addSql($literalSql);

            return;
        }

        parent::addSql($sql, $params, $types);
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

    final protected function isPlatform(SqlPlatform $platform): bool
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
     * the given platforms — e.g.
     * `$this->abortIfUnsupportedPlatform(SqlPlatform::MYSQL, SqlPlatform::MARIADB, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);`.
     * MariaDB is a platform of its own, so a migration that supports it has to list it.
     */
    final protected function abortIfUnsupportedPlatform(SqlPlatform ...$supportedPlatforms): void
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
                implode(', ', array_map(
                    static fn (SqlPlatform $platform): string => $platform->value,
                    $supportedPlatforms
                ))
            )
        );
    }

    private function writesSql(): bool
    {
        return $this->migrationRunContext->isDryRun() || $this->migrationRunContext->writesSql();
    }

    /**
     * @param mixed[] $params
     * @param mixed[] $types
     */
    private function withLiteralParameters(
        string $sql,
        array $params,
        array $types
    ): ?string {
        $literals = [];
        foreach ($params as $key => $value) {
            $literal = $this->toLiteral($value, $types[$key] ?? ParameterType::STRING);
            if ($literal === null) {
                return null;
            }

            $literals[$key] = $literal;
        }

        $visitor = new class($literals) implements Visitor {
            private int $position = 0;

            /** @var list<string> */
            private array $parts = [];

            private bool $complete = true;

            /**
             * @param array<int|string, string> $literals
             */
            public function __construct(private readonly array $literals) {}

            public function acceptPositionalParameter(string $sql): void
            {
                $this->addLiteral($this->position++);
            }

            public function acceptNamedParameter(string $sql): void
            {
                $this->addLiteral(substr($sql, 1));
            }

            public function acceptOther(string $sql): void
            {
                $this->parts[] = $sql;
            }

            public function getSql(): ?string
            {
                return $this->complete ? implode('', $this->parts) : null;
            }

            private function addLiteral(int | string $key): void
            {
                if (!isset($this->literals[$key])) {
                    $this->complete = false;

                    return;
                }

                $this->parts[] = $this->literals[$key];
            }
        };
        $this->platform->createSQLParser()->parse($sql, $visitor);

        return $visitor->getSql();
    }

    private function toLiteral(
        mixed $value,
        mixed $type
    ): ?string {
        if (is_string($type)) {
            $type = Type::getType($type);
        }

        if ($type instanceof Type) {
            $value = $type->convertToDatabaseValue($value, $this->platform);
            $type = $type->getBindingType();
        }

        if ($value === null) {
            return 'NULL';
        }

        if ($type === ParameterType::INTEGER && is_int($value)) {
            return (string)$value;
        }

        if ($type === ParameterType::BOOLEAN && is_bool($value)) {
            $literal = $this->platform->convertBooleans($value);

            return is_int($literal) || is_string($literal) ? (string)$literal : null;
        }

        if (($type === ParameterType::STRING || $type === ParameterType::ASCII) && (is_string($value) || is_int($value) || is_float($value))) {
            $quoted = $this->connection->quote((string)$value);

            return is_string($quoted) ? $quoted : null;
        }

        return null;
    }

    private function resolvePlatform(): ?SqlPlatform
    {
        return $this->resolvedPlatform ??= self::resolveSqlPlatform($this->connection->getDatabasePlatform());
    }

    /**
     * {@see DatabasePlatformResolver} reports MariaDB as MySQL, so MariaDB is recognized here first.
     */
    private static function resolveSqlPlatform(AbstractPlatform $platform): ?SqlPlatform
    {
        if ($platform instanceof MariaDBPlatform) {
            return SqlPlatform::MARIADB;
        }

        return match (DatabasePlatformResolver::resolveName($platform)) {
            DatabasePlatformName::MySQL => SqlPlatform::MYSQL,
            DatabasePlatformName::PostgreSQL => SqlPlatform::POSTGRESQL,
            DatabasePlatformName::SQLite => SqlPlatform::SQLITE,
            null => null,
        };
    }
}
