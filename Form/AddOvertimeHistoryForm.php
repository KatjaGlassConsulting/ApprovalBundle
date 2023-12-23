<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Form;

use App\Form\Type\DatePickerType;
use App\Form\Type\DurationType;
use App\Form\Type\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddOvertimeHistoryForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', UserType::class, [
          'multiple' => false,
          'required' => true,
        ]);

        $builder->add('duration', DurationType::class, [
          'label' => 'duration',
          'required' => true
        ]);

        $builder->add('applyDate', DatePickerType::class, [
          'label' => 'date',
          'required' => true
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'users' => [],
        ]);
    }
}
