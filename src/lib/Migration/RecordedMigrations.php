<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineMigrations\Migration;

use Doctrine\Common\EventSubscriber;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Events;
use Doctrine\Migrations\Version\ExecutionResult;

/**
 * The migrations a real run recorded in the metadata storage, for {@see RecordedMigrationsQueryWriter}. A skipped
 * migration isn't recorded; a dry run records nothing, and its file has the version rows already.
 */
final class RecordedMigrations implements EventSubscriber
{
    /** @var array<string, ExecutionResult> */
    private array $results = [];

    public function getSubscribedEvents(): array
    {
        return [Events::onMigrationsMigrating, Events::onMigrationsVersionExecuted];
    }

    public function onMigrationsMigrating(): void
    {
        $this->results = [];
    }

    public function onMigrationsVersionExecuted(MigrationsVersionEventArgs $args): void
    {
        $result = $args->getPlan()->getResult();
        if ($args->getMigratorConfiguration()->isDryRun() || $result === null) {
            return;
        }

        $this->results[(string)$args->getPlan()->getVersion()] = $result;
    }

    public function get(string $version): ?ExecutionResult
    {
        return $this->results[$version] ?? null;
    }

    public function hasAny(): bool
    {
        return $this->results !== [];
    }
}
