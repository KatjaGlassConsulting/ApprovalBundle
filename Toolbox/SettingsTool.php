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
    public function getConfiguration($key)
    {
        $config = $this->configurationRepository->findOneBy(['name' => $key]);
        if ($config) {
            return $config->getValue() ?? '';
        }

        return '';
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function setConfiguration($key, $value)
    {
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
}
