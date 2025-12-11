<?php
namespace KimaiPlugin\ApprovalBundle\Form\Toolbar;

use App\Form\Toolbar\ToolbarFormTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\Query\ApprovalQuery;

final class ApprovalToolbarForm extends AbstractType
{
    use ToolbarFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addSearchTermInputField($builder);

        $this->addDateRange($builder, ['timezone' => $options['timezone']]);

        $this->addUsersChoice($builder);

        $builder->add('status', ChoiceType::class, [
            'required' => false,
            'placeholder' => 'All',
            'choices' => [
                ApprovalStatus::SUBMITTED => ApprovalStatus::SUBMITTED,
                ApprovalStatus::DENIED => ApprovalStatus::DENIED,
                ApprovalStatus::APPROVED => ApprovalStatus::APPROVED,
                ApprovalStatus::NOT_SUBMITTED => ApprovalStatus::NOT_SUBMITTED,
            ],
            'multiple' => true,
        ]);

        $this->addHiddenPagination($builder);

        $query = $options['data'];

        if ($query instanceof ApprovalQuery) {
            $this->addOrder($builder);
            $this->addOrderBy($builder, $query->getAllowedOrderColumns());
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApprovalQuery::class,
            'csrf_protection' => false,
            'timezone' => date_default_timezone_get(),
        ]);
    }
}