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
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;

/**
 * Builds the {@see Configuration} used by {@see IbexaOnlyDependencyFactory} from the application's own.
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
