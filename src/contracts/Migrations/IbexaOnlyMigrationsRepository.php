<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Doctrine\Migrations\MigrationsRepository;

/**
 * Holds the service ID of the {@see MigrationsRepository} that exposes
 * ONLY migrations tagged with {@see IbexaMigrationTag::TAG}, without decorating any inner
 * (e.g. project-level) repository — unlike the default repository, which combines both.
 *
 * Useful under circumstances where only Ibexa-internal migrations should be considered,
 * regardless of what other migrations a project declares (e.g. installing Ibexa DXP itself).
 *
 * This is a plain container service — wire it in wherever a {@see MigrationsRepository}
 * is needed, e.g. by registering it as Doctrine Migrations' repository definition:
 *
 * ```php
 * $dependencyFactory->setDefinition(
 *     MigrationsRepository::class,
 *     new ServiceClosureArgument(new Reference(IbexaOnlyMigrationsRepository::SERVICE_ID)),
 * );
 * ```
 */
final class IbexaOnlyMigrationsRepository
{
    public const SERVICE_ID = 'ibexa.doctrine_migrations.service_migration_repository.ibexa_migrations_only_repository';
}
