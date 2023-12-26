<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle;

use App\Plugin\PluginInterface;
use KimaiPlugin\ApprovalBundle\DependencyInjection\Compiler\ApprovalSettingsCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ApprovalBundle extends Bundle implements PluginInterface
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ApprovalSettingsCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
