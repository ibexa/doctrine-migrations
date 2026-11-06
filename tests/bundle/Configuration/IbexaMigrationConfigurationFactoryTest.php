<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Ibexa\Bundle\DoctrineMigrations\Configuration\IbexaMigrationConfigurationFactory;
use PHPUnit\Framework\TestCase;

final class IbexaMigrationConfigurationFactoryTest extends TestCase
{
    public function testCreatesAnIndependentCloneWithTheIbexaTemplate(): void
    {
        $applicationConfiguration = new Configuration();
        $applicationConfiguration->addMigrationsDirectory('DoctrineMigrations', '/tmp/migrations');

        $ibexaConfiguration = $this->createFromApplicationConfiguration($applicationConfiguration);

        self::assertNotSame($applicationConfiguration, $ibexaConfiguration);
        self::assertNull($applicationConfiguration->getCustomTemplate());

        $customTemplate = $ibexaConfiguration->getCustomTemplate();
        self::assertNotNull($customTemplate);
        self::assertSame(
            realpath(dirname(__DIR__, 3) . '/src/bundle/Resources/migration-template.php.dist'),
            realpath($customTemplate)
        );
        // The clone still starts with the application's own settings (e.g. migration directories).
        self::assertSame($applicationConfiguration->getMigrationDirectories(), $ibexaConfiguration->getMigrationDirectories());
    }

    /**
     * @return iterable<string, array{Configuration, string|null}>
     */
    public static function provideApplicationConfigurations(): iterable
    {
        yield 'application keeps Doctrine\'s default table' => [new Configuration(), null];

        $metadataStorageConfiguration = new TableMetadataStorageConfiguration();
        $metadataStorageConfiguration->setTableName('app_migration_versions');
        $metadataStorageConfiguration->setVersionColumnName('app_version');
        $configuration = new Configuration();
        $configuration->setMetadataStorageConfiguration($metadataStorageConfiguration);
        yield 'application names its own table and columns' => [$configuration, 'app_migration_versions'];
    }

    /**
     * @dataProvider provideApplicationConfigurations
     */
    public function testRecordsVersionsInATableOfItsOwn(
        Configuration $applicationConfiguration,
        ?string $applicationTableName
    ): void {
        $applicationMetadataStorageConfiguration = $applicationConfiguration->getMetadataStorageConfiguration();

        $ibexaConfiguration = $this->createFromApplicationConfiguration($applicationConfiguration);

        $ibexaMetadataStorageConfiguration = $ibexaConfiguration->getMetadataStorageConfiguration();
        self::assertInstanceOf(TableMetadataStorageConfiguration::class, $ibexaMetadataStorageConfiguration);
        self::assertSame('ibexa_doctrine_migration_versions', $ibexaMetadataStorageConfiguration->getTableName());
        self::assertSame('version', $ibexaMetadataStorageConfiguration->getVersionColumnName());

        // The application's own metadata storage configuration is neither replaced nor modified.
        self::assertSame($applicationMetadataStorageConfiguration, $applicationConfiguration->getMetadataStorageConfiguration());
        self::assertSame(
            $applicationTableName,
            $applicationMetadataStorageConfiguration instanceof TableMetadataStorageConfiguration
                ? $applicationMetadataStorageConfiguration->getTableName()
                : null
        );
    }

    private function createFromApplicationConfiguration(Configuration $applicationConfiguration): Configuration
    {
        $applicationDependencyFactory = $this->createStub(DependencyFactory::class);
        $applicationDependencyFactory
            ->method('getConfiguration')
            ->willReturn($applicationConfiguration);

        return IbexaMigrationConfigurationFactory::createFromApplicationConfiguration($applicationDependencyFactory);
    }
}
