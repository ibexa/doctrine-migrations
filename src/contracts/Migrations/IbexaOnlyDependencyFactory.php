<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigrationsRepository;

/**
 * Holds the service ID of a {@see DependencyFactory} that behaves like the application's own
 * "doctrine.migrations.dependency_factory" — a clone of its {@see Configuration} (carrying an
 * Ibexa-specific migration template, so "ibexa:doctrine:migrations:generate"/"...:diff" scaffold
 * classes shaped like real Ibexa migrations, never the application's own default stub) and the
 * same logger — except it always runs against the "ibexa.persistence.connection"
 * {@see Connection}, and its {@see MigrationsRepository} is always the one identified by
 * {@see IbexaOnlyMigrationsRepository::SERVICE_ID}. Every other dependency it lazily builds
 * (metadata storage, migrator, plan calculator, version comparator, ...) is a fresh instance,
 * built from those, and shared by nothing else.
 *
 * Being an entirely separate {@see DependencyFactory} instance, replacing its MigrationsRepository
 * (or any other dependency) never affects the application's own, and vice versa; and being
 * unfrozen, any of its dependencies can still be replaced via {@see DependencyFactory::setService()}
 * / {@see DependencyFactory::setDefinition()} before first use.
 *
 * This is a plain container service — inject it wherever a dedicated, Ibexa-only
 * {@see DependencyFactory} is needed, e.g. when installing Ibexa DXP itself.
 */
final class IbexaOnlyDependencyFactory
{
    public const SERVICE_ID = 'ibexa.doctrine_migrations.dependency_factory.ibexa_migrations_only';
}
