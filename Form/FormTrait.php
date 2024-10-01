<?php

/*
 * This file copied and modified from kimai using the MIT license
 * kimai/src/Form/FormTrait.php
 *
 */

namespace KimaiPlugin\ApprovalBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Form\Type\ActivityType;
use App\Form\Type\CustomerType;
use App\Form\Type\DescriptionType;
use App\Form\Type\ProjectType;
use App\Form\Type\TagsType;
use App\Repository\ProjectRepository;
use App\Repository\Query\ProjectFormTypeQuery;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Helper functions to manage dependent customer-project-activity fields.
 *
 * If you always want to show the list of all available projects/activities, use the form types directly.
 */
trait FormTrait
{
    protected function addProject(string $id, FormBuilderInterface $builder, bool $isNew, ?Project $project = null, ?Customer $customer = null, array $options = [])
    {
        $options = array_merge([
            'placeholder' => '',
            'activity_enabled' => true,
            'query_builder_for_user' => true,
            'join_customer' => true
        ], $options);

        $builder->add($id, ProjectType::class, array_merge($options, [
            'projects' => $project,
            'customers' => $customer,
        ]));
    }

    protected function addActivity(string $id, string $projectId, FormBuilderInterface $builder, ?Activity $activity = null, ?Project $project = null, array $options = [])
    {
        $options = array_merge(['placeholder' => '', 'query_builder_for_user' => true], $options);

        $options['projects'] = $project;
        $options['activities'] = $activity;

        $builder->add($id, ActivityType::class, $options);

        // replaces the activity select after submission, to make sure only activities for the selected project are displayed
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($options, $projectId, $id) {
                $data = $event->getData();
                if (!isset($data[$projectId]) || empty($data[$projectId])) {
                    return;
                }

                $options['projects'] = $data[$projectId];

                $event->getForm()->add($id, ActivityType::class, $options);
            }
        );
    }

    /**
     * @deprecated since 1.13
     */
    protected function addDescription(FormBuilderInterface $builder)
    {
        @trigger_error('FormTrait::addDescription() is deprecated and will be removed with 2.0, use DescriptionType instead', E_USER_DEPRECATED);

        $builder->add('description', DescriptionType::class, [
            'required' => false,
            'attr' => [
                'autofocus' => 'autofocus'
            ]
        ]);
    }

    /**
     * @deprecated since 1.14
     */
    protected function addTags(FormBuilderInterface $builder)
    {
        @trigger_error('FormTrait::addTags() is deprecated and will be removed with 2.0, use TagsType instead', E_USER_DEPRECATED);

        $builder->add('tags', TagsType::class, [
            'required' => false,
        ]);
    }
}
