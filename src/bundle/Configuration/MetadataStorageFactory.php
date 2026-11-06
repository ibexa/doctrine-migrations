<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Ibexa\DoctrineMigrations\Metadata\IbexaMetadataStorage;

/**
 * Builds the Ibexa-only DependencyFactory's metadata storage (registered with its setDefinition()): Doctrine's own
 * table storage, as the DependencyFactory would build it, wrapped in {@see IbexaMetadataStorage} so that it takes over
 * the Ibexa versions earlier releases recorded in the application's table.
 */
final class MetadataStorageFactory
{
    private DependencyFactory $applicationDependencyFactory;

    public function __construct(DependencyFactory $applicationDependencyFactory)
    {
        $this->applicationDependencyFactory = $applicationDependencyFactory;
    }

    public function create(DependencyFactory $dependencyFactory): MetadataStorage
    {
        $storage = new TableMetadataStorage(
            $dependencyFactory->getConnection(),
            $dependencyFactory->getVersionComparator(),
            $dependencyFactory->getConfiguration()->getMetadataStorageConfiguration(),
            $dependencyFactory->getMigrationRepository()
        );

        $applicationConfiguration = $this->applicationDependencyFactory->getConfiguration()->getMetadataStorageConfiguration()
            ?? new TableMetadataStorageConfiguration();
        // an application keeping its versions in a storage of its own never got Ibexa's in a table
        if (!$applicationConfiguration instanceof TableMetadataStorageConfiguration) {
            return $storage;
        }

        return new IbexaMetadataStorage(
            $storage,
            $dependencyFactory->getMigrationRepository(),
            $dependencyFactory->getVersionComparator(),
            $this->applicationDependencyFactory->getConnection(),
            $applicationConfiguration
        );
    }
}
