<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Form;

use App\Form\Type\UserType;
use App\Reporting\WeekByUser\WeekByUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OvertimeByUserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', UserType::class, [
            'required' => true,
            'multiple' => false,
            'choices' => $options['users'],
            'width' => false
        ]);

        $builder->add('linkButton', ButtonType::class, [
            'label' => 'all',
            'attr' => [
                'class' => 'btn btn-primary',
                'onclick' => \sprintf("window.location.href='%s'", $options['routePath']),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WeekByUser::class,
            'csrf_protection' => false,
            'method' => 'GET',
            'users' => [],
            'routePath' => ''
        ]);
    }
}
