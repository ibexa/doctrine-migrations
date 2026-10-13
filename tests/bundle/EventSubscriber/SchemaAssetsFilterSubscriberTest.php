<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\DoctrineMigrations\EventSubscriber;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Ibexa\Bundle\DoctrineMigrations\EventSubscriber\SchemaAssetsFilterSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class SchemaAssetsFilterSubscriberTest extends TestCase
{
    private Configuration $configuration;

    private SchemaAssetsFilterSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->configuration->setSchemaAssetsFilter(static fn (mixed $asset): bool => $asset === 'ibexa_mapped');

        $connection = $this->createMock(Connection::class);
        $connection->method('getConfiguration')->willReturn($this->configuration);

        $this->subscriber = new SchemaAssetsFilterSubscriber($connection);
    }

    public function testLiftsTheFilterForAMigrationsCommandUntilItTerminates(): void
    {
        $this->subscriber->onCommand($this->createEvent('ibexa:doctrine:migrations:migrate'));

        self::assertTrue($this->accepts('doctrine_migration_versions'));
        self::assertTrue($this->accepts('ibexa_content'));

        $this->subscriber->onTerminate();

        self::assertFalse($this->accepts('doctrine_migration_versions'));
        self::assertTrue($this->accepts('ibexa_mapped'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOtherCommands(): iterable
    {
        yield 'ORM schema sync' => ['doctrine:schema:update'];
        yield 'installer' => ['ibexa:install'];
        yield 'application migrations' => ['doctrine:migrations:migrate'];
    }

    #[DataProvider('provideOtherCommands')]
    public function testKeepsTheFilterForOtherCommands(string $name): void
    {
        $this->subscriber->onCommand($this->createEvent($name));

        self::assertFalse($this->accepts('doctrine_migration_versions'));

        $this->subscriber->onTerminate();

        self::assertFalse($this->accepts('doctrine_migration_versions'));
        self::assertTrue($this->accepts('ibexa_mapped'));
    }

    private function accepts(string $asset): bool
    {
        $accepted = ($this->configuration->getSchemaAssetsFilter())($asset);
        self::assertIsBool($accepted);

        return $accepted;
    }

    private function createEvent(string $name): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent(new Command($name), new ArrayInput([]), new NullOutput());
    }
}
