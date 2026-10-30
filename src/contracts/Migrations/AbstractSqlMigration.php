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
use Doctrine\DBAL\Schema\Schema;
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
        array $types = []
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

            $literals[is_string($key) ? ltrim($key, ':') : $key] = $literal;
        }

        $position = 0;
        $result = '';
        foreach (self::splitAtPlaceholders($sql, $this->isMySQL() || $this->isMariaDB()) as $part) {
            if ($part[0] === 'sql') {
                $result .= $part[1];
                continue;
            }

            $key = $part[0] === '?' ? $position++ : $part[1];
            if (!isset($literals[$key])) {
                return null;
            }

            $result .= $literals[$key];
        }

        return $result;
    }

    /**
     * Splits a statement into its SQL and its placeholders, skipping quoted strings, quoted identifiers and comments,
     * the way DBAL finds them (DBAL 2.13, which this branch supports, has no SQL parser of its own to use here).
     *
     * @return list<array{'sql', string}|array{'?', string}|array{':', string}> SQL text, a positional placeholder, or
     *                                                                         a named one (its name)
     */
    private static function splitAtPlaceholders(
        string $sql,
        bool $backslashEscapes
    ): array {
        $parts = [];
        $text = '';
        $length = strlen($sql);
        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $end = $i + 1;
                while ($end < $length && $sql[$end] !== $char) {
                    $end += $backslashEscapes && $sql[$end] === '\\' ? 2 : 1;
                }
                // '' inside a string is a quote, and the string goes on
                while ($end + 1 < $length && $sql[$end + 1] === $char) {
                    $end += 2;
                    while ($end < $length && $sql[$end] !== $char) {
                        $end += $backslashEscapes && $sql[$end] === '\\' ? 2 : 1;
                    }
                }
                $text .= substr($sql, $i, $end - $i + 1);
                $i = $end;
            } elseif ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i);
                $end = $end === false ? $length - 1 : $end;
                $text .= substr($sql, $i, $end - $i + 1);
                $i = $end;
            } elseif ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $length - 1 : $end + 1;
                $text .= substr($sql, $i, $end - $i + 1);
                $i = $end;
            } elseif ($char === '?') {
                $parts[] = ['sql', $text];
                $parts[] = ['?', '?'];
                $text = '';
            } elseif ($char === ':' && ($sql[$i + 1] ?? ':') !== ':' && ($i === 0 || $sql[$i - 1] !== ':')
                && preg_match('/\\G[a-zA-Z_][a-zA-Z0-9_]*/', $sql, $match, 0, $i + 1) === 1) {
                $parts[] = ['sql', $text];
                $parts[] = [':', $match[0]];
                $text = '';
                $i += strlen($match[0]);
            } else {
                $text .= $char;
            }
        }
        $parts[] = ['sql', $text];

        return $parts;
    }

    /**
     * @param mixed $value
     * @param mixed $type
     */
    private function toLiteral(
        $value,
        $type
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
