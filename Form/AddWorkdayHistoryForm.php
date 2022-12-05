<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Form;

use Exception;
use App\Form\Type\DatePickerType;
use App\Form\Type\DurationType;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\UserType;

class AddWorkdayHistoryForm extends AbstractType
{
    /**
     * @var ApprovalRepository
     */
    private $approvalRepository;

    public function __construct(
        ApprovalRepository $approvalRepository
    ) {
        $this->approvalRepository = $approvalRepository;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $days = [
          'Monday' => 'monday',
          'Tuesday' => 'tuesday',
          'Wednesday' => 'wednesday',
          'Thursday' => 'thursday',
          'Friday' => 'friday',
          'Saturday' => 'saturday',
          'Sunday' => 'sunday'
        ];

        $builder->add('user', UserType::class, [
          'multiple' => false,
          'required' => true,
        ]);

        foreach ($days as $key => $value) {
            $builder->add($value, DurationType::class, [
              'label' => $key,
              'translation_domain' => 'system-configuration',
              'required' => true
            ]);
        }

        $builder->add('validTill', DatePickerType::class, [
          'label' => 'label.validTill',
          'required' => true
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'users' => [],
        ]);
    }
}
