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
 * Builds the Ibexa-only DependencyFactory's {@see IbexaMetadataStorage}.
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

        $projectStorageConfiguration = $this->applicationDependencyFactory->getConfiguration()->getMetadataStorageConfiguration()
            ?? new TableMetadataStorageConfiguration();
        if (!$projectStorageConfiguration instanceof TableMetadataStorageConfiguration) {
            return $storage;
        }

        return new IbexaMetadataStorage(
            $storage,
            $dependencyFactory->getMigrationRepository(),
            $dependencyFactory->getVersionComparator(),
            $dependencyFactory->getConnection(),
            $projectStorageConfiguration
        );
    }
}
