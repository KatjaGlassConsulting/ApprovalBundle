<?php

namespace KimaiPlugin\ApprovalBundle\Form\Toolbar;

use App\Form\Toolbar\ToolbarFormTrait;
use KimaiPlugin\ApprovalBundle\Repository\Query\WorkingTimeActQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WorkingTimeActToolbarForm extends AbstractType
{
    use ToolbarFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addSearchTermInputField($builder);

        $this->addDateRange($builder, ['timezone' => $options['timezone']]);

        $this->addUsersChoice($builder);

        $this->addHiddenPagination($builder);

        $query = $options['data'];

        if ($query instanceof WorkingTimeActQuery) {
            $this->addOrder($builder);
            $this->addOrderBy($builder, $query->getAllowedOrderColumns());
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkingTimeActQuery::class,
            'csrf_protection' => false,
            'timezone' => date_default_timezone_get(),
        ]);
    }
}
