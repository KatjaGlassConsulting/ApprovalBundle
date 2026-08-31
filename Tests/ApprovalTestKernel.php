<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests;

use App\Kernel;
use KimaiPlugin\ApprovalBundle\ApprovalBundle;
use KimaiPlugin\ApprovalBundle\Tests\DependencyInjection\ApprovalTestSettingsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Kimai's Kernel skips plugin discovery in the "test" environment (see src/Kernel.php),
 * so a plain KernelTestCase would boot without the ApprovalBundle: no services, no routes,
 * no entities. This kernel adds the bundle back and is wired up via the KERNEL_CLASS
 * environment variable in phpunit.xml.dist.
 */
final class ApprovalTestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();

        yield new ApprovalBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // registered after ApprovalBundle::build(), therefore it also runs after
        // the bundle's own ApprovalSettingsCompilerPass
        $container->addCompilerPass(new ApprovalTestSettingsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    /**
     * Symfony derives the project directory from the location of the kernel class, which
     * would resolve to the plugin directory (it has its own composer.json). Everything the
     * kernel loads - config/, src/, var/ - lives in the Kimai installation instead.
     */
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 4);
    }

    /**
     * Own cache directory, otherwise this container would clash with the one
     * built by the Kimai core test-suite.
     */
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/approval-' . $this->environment;
    }
}
