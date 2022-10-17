<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\DependencyInjection\Compiler;

use KimaiPlugin\ApprovalBundle\Settings\DefaultSettings;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApprovalSettingsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->findDefinition('approval.settings');
        if (!class_exists('\KimaiPlugin\MetaFieldsBundle\Repository\MetaFieldRuleRepository')) {
            $definition->setClass(DefaultSettings::class);
        }
    }
}
