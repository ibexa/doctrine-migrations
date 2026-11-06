<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\AbstractSqlMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;

/**
 * Builds the {@see Configuration} used by {@see IbexaOnlyDependencyFactory}:
 * a clone of the application's own Configuration (so migrations directories and transactional/check-database-platform
 * settings all start identical), with its custom migration template overridden to Ibexa's own -- so scaffolded
 * classes already extend {@see AbstractSqlMigration} and implement {@see IbexaMigrationInterface} -- and its executed
 * versions kept in a table of its own, never in the application's "doctrine_migration_versions" (or whatever the
 * application named it), without ever mutating (and therefore leaking any of that into) the application's own
 * Configuration instance.
 *
 * The clone is shallow, so the application's metadata storage configuration is replaced, never modified; Ibexa's
 * table keeps Doctrine's default columns, whatever the application set for its own.
 */
final class IbexaMigrationConfigurationFactory
{
    private const METADATA_TABLE_NAME = 'ibexa_doctrine_migration_versions';

    public static function createFromApplicationConfiguration(DependencyFactory $applicationDependencyFactory): Configuration
    {
        $configuration = clone $applicationDependencyFactory->getConfiguration();
        $configuration->setCustomTemplate(__DIR__ . '/../Resources/migration-template.php.dist');

        $metadataStorageConfiguration = new TableMetadataStorageConfiguration();
        $metadataStorageConfiguration->setTableName(self::METADATA_TABLE_NAME);
        $configuration->setMetadataStorageConfiguration($metadataStorageConfiguration);

        return $configuration;
    }
}
