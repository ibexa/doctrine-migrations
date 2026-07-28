<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Ibexa\Contracts\DoctrineMigrations\Migrations\AbstractSqlMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaOnlyDependencyFactory;

/**
 * Builds the {@see Configuration} used by {@see IbexaOnlyDependencyFactory}:
 * a clone of the application's own Configuration (so migrations directories, transactional/check-database-platform
 * settings, and metadata storage configuration all start identical), with its custom migration template
 * overridden to Ibexa's own -- so scaffolded classes already extend
 * {@see AbstractSqlMigration} and implement
 * {@see IbexaMigrationInterface}, without ever mutating (and
 * therefore leaking the template into) the application's own Configuration instance.
 */
final class IbexaMigrationConfigurationFactory
{
    public static function createFromApplicationConfiguration(DependencyFactory $applicationDependencyFactory): Configuration
    {
        $configuration = clone $applicationDependencyFactory->getConfiguration();
        $configuration->setCustomTemplate(__DIR__ . '/../Resources/migration-template.php.dist');

        return $configuration;
    }
}
