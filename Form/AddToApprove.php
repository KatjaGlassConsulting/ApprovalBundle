<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Form;

use Exception;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddToApprove extends AbstractType
{
    public function __construct(private ApprovalRepository $approvalRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', ChoiceType::class, [
            'label' => 'label.user',
            'required' => true,
            'multiple' => false,
            'choices' => $options['users'],
            'choice_value' => 'id',
            'choice_label' => 'username'
        ]);

        $builder->add('week', ChoiceType::class, [
            'label' => 'label.week',
            'required' => true,
            'multiple' => true,
            'choices' => $this->approvalRepository->getWeeks($options['user']),
            'choice_value' => 'value',
            'choice_label' => 'label'
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'action.ok',
            'attr' => [
                'class' => 'btn btn-primary'
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'users' => [],
        ]);
    }
}
