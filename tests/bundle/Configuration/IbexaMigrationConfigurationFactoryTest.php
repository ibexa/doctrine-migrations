<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\Configuration;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Ibexa\Bundle\DoctrineMigrations\Configuration\IbexaMigrationConfigurationFactory;
use PHPUnit\Framework\TestCase;

final class IbexaMigrationConfigurationFactoryTest extends TestCase
{
    public function testCreatesAnIndependentCloneWithTheIbexaTemplate(): void
    {
        $applicationConfiguration = new Configuration();
        $applicationConfiguration->addMigrationsDirectory('DoctrineMigrations', '/tmp/migrations');

        $applicationDependencyFactory = $this->createMock(DependencyFactory::class);
        $applicationDependencyFactory
            ->method('getConfiguration')
            ->willReturn($applicationConfiguration);

        $ibexaConfiguration = IbexaMigrationConfigurationFactory::createFromApplicationConfiguration($applicationDependencyFactory);

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
}
