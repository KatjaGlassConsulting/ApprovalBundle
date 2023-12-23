<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;

class SettingsTool
{
    private array $cache = [];

    public function __construct(private ConfigurationRepository $configurationRepository)
    {
    }

    /**
     * @param $key
     * @return mixed|string
     */
    public function isInConfiguration($key): bool
    {
        if ($this->configurationRepository->findOneBy(['name' => $key]) === null) {
            return false;
        }

        return true;
    }

    public function isOvertimeCheckActive(): bool
    {
        return $this->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY, null) !== null;
    }

    /**
     * @param $key
     * @return mixed|string
     */
    public function getConfiguration($key, $default = '')
    {
        if (!\array_key_exists($key, $this->cache)) {
            $config = $this->configurationRepository->findOneBy(['name' => $key]);
            if ($config === null) {
                return $default;
            }
            $this->cache[$key] = $config->getValue() ?? $default;
        }

        return $this->cache[$key];
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function setConfiguration($key, $value): bool
    {
        $this->cache = [];

        $configuration = $this->configurationRepository->findOneBy(['name' => $key]);

        if ($configuration === null) {
            $configuration = new Configuration();
            $configuration->setName($key);
        }

        $configuration->setValue($value);

        $this->configurationRepository->saveConfiguration($configuration);

        return true;
    }
}
