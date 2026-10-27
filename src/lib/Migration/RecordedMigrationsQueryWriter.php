<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\QueryWriter;

/**
 * Doctrine writes the version rows into the --write-sql file of a dry run only; a real run records them in the
 * database alone. This adds them to a real run's file too, for every migration the run recorded, so the file brings
 * another copy of the database to the same state on its own, version rows included.
 */
final class RecordedMigrationsQueryWriter implements QueryWriter
{
    public function __construct(
        private readonly QueryWriter $innerWriter,
        private readonly MetadataStorage $metadataStorage,
        private readonly RecordedMigrations $recordedMigrations
    ) {}

    /**
     * @param array<string, Query[]> $queriesByVersion
     */
    public function write(
        string $path,
        string $direction,
        array $queriesByVersion
    ): bool {
        if (method_exists($this->metadataStorage, 'getSql')) {
            foreach ($queriesByVersion as $version => $queries) {
                $result = $this->recordedMigrations->get($version);
                if ($result === null) {
                    continue;
                }

                foreach ($this->metadataStorage->getSql($result) as $query) {
                    $queriesByVersion[$version][] = $query;
                }
            }
        }

        return $this->innerWriter->write($path, $direction, $queriesByVersion);
    }
}
