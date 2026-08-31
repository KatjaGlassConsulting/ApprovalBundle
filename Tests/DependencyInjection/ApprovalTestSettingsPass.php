<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\DependencyInjection;

use KimaiPlugin\ApprovalBundle\Settings\DefaultSettings;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * ApprovalSettingsCompilerPass keeps the MetaFieldSettings implementation as soon as the
 * MetaFieldsBundle classes are autoloadable - which they are whenever that plugin sits in
 * var/plugins, even if it is not booted. Its extension however returns early in the "test"
 * environment and registers no services at all, so "approval.settings" could not be autowired.
 *
 * Falling back to DefaultSettings mirrors a Kimai installation that runs the ApprovalBundle
 * without the MetaFieldsBundle, which is a supported setup.
 */
final class ApprovalTestSettingsPass implements CompilerPassInterface
{
    private const META_FIELDS_REPOSITORY = 'KimaiPlugin\\MetaFieldsBundle\\Repository\\MetaFieldRuleRepository';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('approval.settings') && !$container->hasAlias('approval.settings')) {
            return;
        }

        if ($container->has(self::META_FIELDS_REPOSITORY)) {
            return;
        }

        $container->findDefinition('approval.settings')->setClass(DefaultSettings::class);
    }
}
