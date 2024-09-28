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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

class SettingsForm extends AbstractType
{
    public function __construct(
        private readonly FormTool $formTool,
        private readonly SettingsTool $settingsTool,
        private readonly CustomerRepository $customerRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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

        $builder->add(FormEnum::BREAKCHECKS_NY, CheckboxType::class, [
          'label' => 'label.approval_breakchecks_ny',
          'data' => $this->settingsTool->getBooleanConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY, true),
          'required' => false
        ]);

        if ($this->settingsTool->isInConfiguration(ConfigEnum::APPROVAL_INCLUDE_ADMIN_NY)) {
            $adminchecks = $this->formTool->isChecked(ConfigEnum::APPROVAL_INCLUDE_ADMIN_NY);
        } else {
            $adminchecks = false;
        }

        $builder->add(FormEnum::INCLUDE_ADMIN_NY, CheckboxType::class, [
            'label' => 'label.approval_include_admin_ny',
            'data' => $this->settingsTool->getBooleanConfiguration(ConfigEnum::APPROVAL_INCLUDE_ADMIN_NY, false),
            'required' => false
        ]);

        if ($this->settingsTool->isInConfiguration(ConfigEnum::APPROVAL_TEAMLEAD_SELF_APPROVE_NY)) {
            $leadselfchecks = $this->formTool->isChecked(ConfigEnum::APPROVAL_TEAMLEAD_SELF_APPROVE_NY);
        } else {
            $leadselfchecks = false;
        }

        $builder->add(FormEnum::TEAMLEAD_SELF_APPROVE_NY, CheckboxType::class, [
            'label' => 'label.approval_teamlead_selfapprove_ny',
            'data' => $this->settingsTool->getBooleanConfiguration(ConfigEnum::APPROVAL_TEAMLEAD_SELF_APPROVE_NY, false),
            'required' => false
        ]);

        $builder->add(FormEnum::MAIL_SUBMITTED_NY, CheckboxType::class, [
            'label' => 'label.approval_mail_submitted_ny',
            'data' => $this->settingsTool->getBooleanConfiguration(ConfigEnum::APPROVAL_MAIL_SUBMITTED_NY, true),
            'required' => false
        ]);
       
        $builder->add(FormEnum::MAIL_ACTION_NY, CheckboxType::class, [
            'label' => 'label.approval_mail_action_ny',
            'data' => $this->settingsTool->getBooleanConfiguration(ConfigEnum::APPROVAL_MAIL_ACTION_NY, true),
            'required' => false
        ]);

        $builder->add(FormEnum::SUBMIT, SubmitType::class, [
            'label' => 'action.save',
            'attr' => ['class' => 'btn btn-primary']
        ]);
    }
}
