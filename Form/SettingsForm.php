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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsForm extends AbstractType
{
    public function __construct(
        private FormTool $formTool,
        private SettingsTool $settingsTool,
        private CustomerRepository $customerRepository
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'with_time' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['with_time'] === true) {
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
        }

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

        $builder->add(FormEnum::OVERTIME_NY, CheckboxType::class, [
          'label' => 'label.approval_overtime_ny',
          'data' => $this->formTool->isChecked(ConfigEnum::APPROVAL_OVERTIME_NY),
          'required' => false
        ]);

        if ($this->settingsTool->isInConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY)) {
            $breakchecks = $this->formTool->isChecked(ConfigEnum::APPROVAL_BREAKCHECKS_NY);
        } else {
            $breakchecks = true;            
        }
        $builder->add(FormEnum::BREAKCHECKS_NY, CheckboxType::class, [
          'label' => 'label.approval_breakchecks_ny',
          'data' => $breakchecks,
          'required' => false
        ]);

        $builder->add(FormEnum::SUBMIT, SubmitType::class, [
            'label' => 'action.save',
            'attr' => ['class' => 'btn btn-primary']
        ]);
    }
}
