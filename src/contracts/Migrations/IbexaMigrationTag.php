<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineMigrations\Migrations;

/**
 * Holds the Symfony service tag used to register Ibexa-internal Doctrine migrations.
 *
 * Tag migration services with {@see TAG} so they are picked up by the compiler pass
 * and wired into the migrations pipeline.
 *
 * Usage in YAML:
 *
 * ```yaml
 * App\Migrations\Version20240101:
 *     tags:
 *         - { name: !php/const Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag::TAG }
 * ```
 *
 * Or autoconfigure via _instanceof:
 *
 * ```yaml
 * services:
 *     _instanceof:
 *         Doctrine\Migrations\AbstractMigration:
 *             tags: [!php/const Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag::TAG]
 * ```
 */
final class IbexaMigrationTag
{
    public const TAG = 'ibexa.doctrine_migrations.migration';
}
