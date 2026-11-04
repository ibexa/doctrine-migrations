<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\FileQueryWriter;
use Doctrine\Migrations\Generator\ConcatenationFileBuilder;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\QueryWriter;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;

/**
 * Doctrine writes a statement into the --write-sql file as it is, so one with bound parameters ends up there with its
 * "?" placeholders, and the database client stops at it. This writer puts the parameters into the statements it
 * writes, quoted by the connection; the migrations still execute with them bound. A statement with a parameter that
 * has no literal form here (arrays, binary and large objects) is written as it is.
 */
final class LiteralParametersQueryWriter implements QueryWriter
{
    private QueryWriter $innerWriter;

    private Connection $connection;

    public function __construct(
        QueryWriter $innerWriter,
        Connection $connection
    ) {
        $this->innerWriter = $innerWriter;
        $this->connection = $connection;
    }

    /**
     * The Ibexa-only DependencyFactory's query writer: Doctrine's own, wrapped in this one.
     */
    public static function create(DependencyFactory $dependencyFactory): self
    {
        return new self(
            new FileQueryWriter(new ConcatenationFileBuilder(), $dependencyFactory->getLogger()),
            $dependencyFactory->getConnection()
        );
    }

    /**
     * @param array<string, Query[]> $queriesByVersion
     */
    public function write(
        string $path,
        string $direction,
        array $queriesByVersion
    ): bool {
        $platform = $this->connection->getDatabasePlatform();
        // MariaDB is resolved as MySQL, and both take backslash escapes in strings
        $backslashEscapes = DatabasePlatformResolver::resolveName($platform) === DatabasePlatformName::MYSQL;

        foreach ($queriesByVersion as $version => $queries) {
            foreach ($queries as $index => $query) {
                if ($query->getParameters() === []) {
                    continue;
                }

                $literalSql = $this->withLiteralParameters($query, $platform, $backslashEscapes);
                if ($literalSql !== null) {
                    $queriesByVersion[$version][$index] = new Query($literalSql);
                }
            }
        }

        return $this->innerWriter->write($path, $direction, $queriesByVersion);
    }

    private function withLiteralParameters(
        Query $query,
        AbstractPlatform $platform,
        bool $backslashEscapes
    ): ?string {
        $params = $query->getParameters();
        $types = $query->getTypes();
        $literals = [];
        if (is_int(key($params))) {
            // Like DBAL: positional parameters bind in array order, whatever their keys, and their types go by that
            // position too, counted from 0 or from 1.
            $typeOffset = array_key_exists(0, $types) ? 0 : 1;
            foreach (array_values($params) as $position => $value) {
                $literal = $this->toLiteral($value, $types[$position + $typeOffset] ?? ParameterType::STRING, $platform);
                if ($literal === null) {
                    return null;
                }

                $literals[$position] = $literal;
            }
        } else {
            foreach ($params as $name => $value) {
                $literal = $this->toLiteral($value, $types[$name] ?? ParameterType::STRING, $platform);
                if ($literal === null) {
                    return null;
                }

                $literals[ltrim((string)$name, ':')] = $literal;
            }
        }

        $position = 0;
        $result = '';
        foreach (self::splitAtPlaceholders($query->getStatement(), $backslashEscapes) as $part) {
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
        $type,
        AbstractPlatform $platform
    ): ?string {
        if (is_string($type)) {
            $type = Type::getType($type);
        }

        if ($type instanceof Type) {
            $value = $type->convertToDatabaseValue($value, $platform);
            $type = $type->getBindingType();
        }

        if ($value === null) {
            return 'NULL';
        }

        if ($type === ParameterType::INTEGER && is_int($value)) {
            return (string)$value;
        }

        // DBAL's boolean type converts to 0 or 1, which PostgreSQL doesn't take for a boolean column as a literal
        if ($type === ParameterType::BOOLEAN && (is_bool($value) || $value === 0 || $value === 1)) {
            $literal = $platform->convertBooleans((bool)$value);

            return is_int($literal) || is_string($literal) ? (string)$literal : null;
        }

        if (($type === ParameterType::STRING || $type === ParameterType::ASCII) && (is_string($value) || is_int($value) || is_float($value))) {
            $quoted = $this->connection->quote((string)$value);

            return is_string($quoted) ? $quoted : null;
        }

        return null;
    }
}
