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
    /**
     * @var ConfigurationRepository
     */
    private $configurationRepository;
    private $cache = [];

    /**
     * @param ConfigurationRepository $configurationRepository
     */
    public function __construct(ConfigurationRepository $configurationRepository)
    {
        $this->configurationRepository = $configurationRepository;
    }

    /**
     * @param $key
     * @return mixed|string
     */
    public function isInConfiguration($key)
    {
        if ($this->configurationRepository->findOneBy(['name' => $key]) == null){
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $key
     * @return mixed|string
     */
    public function getConfiguration($key)
    {
        if (!array_key_exists($key, $this->cache)) {
            $config = $this->configurationRepository->findOneBy(['name' => $key]);
            if ($config === null) {
                return '';
            }
            $this->cache[$key] = $config->getValue() ?? '';
        }

        return $this->cache[$key];
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function setConfiguration($key, $value)
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

    public function isAllSettingsUpdated()
    {
        if ($this->getConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_MONDAY) === '') {
            return false;
        }
        if ($this->getConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_TUESDAY) === '') {
            return false;
        }
        if ($this->getConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_WEDNESDAY) === '') {
            return false;
        }
        if ($this->getConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_THURSDAY) === '') {
            return false;
        }
        if ($this->getConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_FRIDAY) === '') {
            return false;
        }
        if ($this->getConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SATURDAY) === '') {
            return false;
        }
        if ($this->getConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SUNDAY) === '') {
            return false;
        }

        return true;
    }

    public function resetCache()
    {
        $this->cache = [];
    }
}
