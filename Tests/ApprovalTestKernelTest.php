<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests;

use KimaiPlugin\ApprovalBundle\ApprovalBundle;
use KimaiPlugin\ApprovalBundle\Settings\ApprovalSettingsInterface;
use KimaiPlugin\ApprovalBundle\Toolbox\Formatting;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke test proving that the plugin is actually registered and its services are
 * wired - Kimai's own Kernel skips plugins in the "test" environment.
 *
 * @group integration
 */
class ApprovalTestKernelTest extends KernelTestCase
{
    public function testBundleIsRegistered(): void
    {
        self::bootKernel();

        self::assertArrayHasKey('ApprovalBundle', self::$kernel->getBundles());
        self::assertInstanceOf(ApprovalBundle::class, self::$kernel->getBundle('ApprovalBundle'));
    }

    public function testServicesAreWired(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(Formatting::class, $container->get(Formatting::class));
        self::assertInstanceOf(ApprovalSettingsInterface::class, $container->get('approval.settings'));
    }
}
