<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use Doctrine\ORM\EntityRepository;
use KimaiPlugin\ApprovalBundle\Settings\ApprovalSettingsInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class FormTool
{
    public function __construct(private SettingsTool $settingsTool, private ApprovalSettingsInterface $metaFieldRuleRepository)
    {
    }

    /**
     * @param $key
     * @param $label
     * @param $child
     * @param FormBuilderInterface $builder
     */
    public function createMetaDataChoice($child, $label, $key, FormBuilderInterface $builder)
    {
        $data = empty($this->settingsTool->getConfiguration($key)) ? null : $this->metaFieldRuleRepository->find($this->settingsTool->getConfiguration($key));

        $builder->add($child, ChoiceType::class, [
            'label' => $label,
            'data' => $data,
            'required' => false,
            'choices' => $this->metaFieldRuleRepository->getRules(),
            'choice_value' => 'label',
            'choice_label' => 'label'
        ]);
    }

    /**
     * @param $prefix
     * @return bool
     */
    public function isChecked($prefix)
    {
        return !empty($this->settingsTool->getConfiguration($prefix));
    }

    /**
     * @param $prefix
     * @param EntityRepository $repository
     * @param string $columnName
     * @return array
     */
    public function collectElementsToExclude($prefix, EntityRepository $repository, $columnName = 'id')
    {
        $elementsToExclude = [];

        if (empty($this->settingsTool->getConfiguration($prefix))) {
            return $elementsToExclude;
        }

        $ids = explode(',', (string) $this->settingsTool->getConfiguration($prefix));
        foreach ($ids as $id) {
            array_push($elementsToExclude, $repository->findOneBy([$columnName => $id]));
        }

        return $elementsToExclude;
    }
}
