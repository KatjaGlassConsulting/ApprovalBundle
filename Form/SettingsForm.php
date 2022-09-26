<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Form;

use App\Form\Type\CustomerType;
use App\Repository\CustomerRepository;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Enumeration\FormEnum;
use KimaiPlugin\ApprovalBundle\Toolbox\FormTool;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

class SettingsForm extends AbstractType
{
    /**
     * @var FormTool
     */
    private $formTool;
    /**
     * @var SettingsTool
     */
    private $settingsTool;
    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    public function __construct(
        FormTool $formTool,
        SettingsTool $settingsTool,
        CustomerRepository $customerRepository
    ) {
        $this->formTool = $formTool;
        $this->settingsTool = $settingsTool;
        $this->customerRepository = $customerRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->formTool->createMetaDataChoice(
            FormEnum::MONDAY,
            'label.meta_field_expected_working_time_on_monday',
            ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_MONDAY,
            $builder
        );
        $this->formTool->createMetaDataChoice(
            FormEnum::TUESDAY,
            'label.meta_field_expected_working_time_on_tuesday',
            ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_TUESDAY,
            $builder
        );
        $this->formTool->createMetaDataChoice(
            FormEnum::WEDNESDAY,
            'label.meta_field_expected_working_time_on_wednesday',
            ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_WEDNESDAY,
            $builder
        );
        $this->formTool->createMetaDataChoice(
            FormEnum::THURSDAY,
            'label.meta_field_expected_working_time_on_thursday',
            ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_THURSDAY,
            $builder
        );
        $this->formTool->createMetaDataChoice(
            FormEnum::FRIDAY,
            'label.meta_field_expected_working_time_on_friday',
            ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_FRIDAY,
            $builder
        );
        $this->formTool->createMetaDataChoice(
            FormEnum::SATURDAY,
            'label.meta_field_expected_working_time_on_saturday',
            ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SATURDAY,
            $builder
        );
        $this->formTool->createMetaDataChoice(
            FormEnum::SUNDAY,
            'label.meta_field_expected_working_time_on_sunday',
            ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SUNDAY,
            $builder
        );

        $customer = $this->customerRepository->find($this->settingsTool->getConfiguration(ConfigEnum::CUSTOMER_FOR_FREE_DAYS));
        $builder->add(FormEnum::CUSTOMER_FOR_FREE_DAYS, CustomerType::class, [
            'label' => 'label.customer_for_free_days',
            'data' => $customer ?? null,
            'required' => false
        ]);

        $data = $this->settingsTool->getConfiguration(ConfigEnum::META_FIELD_EMAIL_LINK_URL);
        $builder->add(FormEnum::EMAIL_LINK_URL, UrlType::class, [
            'label' => 'label.email_link_url',
            'data' => $data,
            'required' => false
        ]);

        $workflowDate = $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_WORKFLOW_START);
        if ($workflowDate == '') {
            $workflowDate = '2000-01-01';
        }
        $builder->add(FormEnum::WORKFLOW_START, TextType::class, [
            'label' => 'label.workflow_start',
            'data' => $workflowDate,
            'required' => false
        ]);

        $builder->add(FormEnum::SUBMIT, SubmitType::class, [
            'label' => 'action.save',
            'attr' => ['class' => 'btn btn-primary']
        ]);
    }
}
