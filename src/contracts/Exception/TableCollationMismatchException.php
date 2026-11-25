<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Exception;

use RuntimeException;

/**
 * Thrown before a migrate when Ibexa tables use another collation than the tables the migrations create would get.
 */
final class TableCollationMismatchException extends RuntimeException
{
    private const LISTED_TABLES = 5;

    /**
     * @param non-empty-array<string, string> $mismatchedCollations table name => collation
     */
    public function __construct(
        string $expectedCollation,
        array $mismatchedCollations
    ) {
        $listed = [];
        foreach (array_slice($mismatchedCollations, 0, self::LISTED_TABLES, true) as $table => $collation) {
            $listed[] = sprintf('%s (%s)', $table, $collation);
        }

        $count = count($mismatchedCollations);

        parent::__construct(sprintf(
            'Ibexa tables use another collation than the %s new tables get: %s. Configure their collation in '
            . '"ibexa_doctrine_schema.tables.options", or convert them first (back the database up: converting '
            . 'rewrites and locks each table). A table can be skipped with '
            . '"ibexa_doctrine_migrations.table_collation_check.ignored_tables".',
            $expectedCollation,
            implode(', ', $listed)
                . ($count > self::LISTED_TABLES ? sprintf(' and %d more', $count - self::LISTED_TABLES) : '')
        ));
    }
}
