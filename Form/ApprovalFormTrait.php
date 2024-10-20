<?php

/*
 * This file copied and modified from kimai using the MIT license
 * kimai/src/Form/ApprovalFormTrait.php
 *
 */

namespace KimaiPlugin\ApprovalBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Form\FormTrait;
use App\Form\Type\ActivityType;
use App\Form\Type\ProjectType;
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
trait ApprovalFormTrait
{
    use FormTrait {
        addProject as protected addProjectParent;
        addActivity as protected addActivityParent;
    }

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
            'data' => $project,
            'customers' => $customer,
        ]));

        // replaces the project select after submission, to make sure only projects for the selected customer are displayed
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($id, $builder, $project, $customer, $isNew, $options) {
                $data = $event->getData();
                $customer = isset($data['customer']) && !empty($data['customer']) ? $data['customer'] : null;
                $project = isset($data[$id]) && !empty($data[$id]) ? $data[$id] : $project;

                $event->getForm()->add($id, ProjectType::class, array_merge($options, [
                    'group_by' => null,
                    'query_builder' => function (ProjectRepository $repo) use ($builder, $project, $customer, $isNew) {
                        // is there a better wa to prevent starting a record with a hidden project ?
                        if ($isNew && !empty($project) && (\is_int($project) || \is_string($project))) {
                            /** @var Project $project */
                            $project = $repo->find($project);
                            if (null !== $project) {
                                if (!$project->getCustomer()->isVisible()) {
                                    $customer = null;
                                    $project = null;
                                } elseif (!$project->isVisible()) {
                                    $project = null;
                                }
                            }
                        }
                        $query = new ProjectFormTypeQuery($project, $customer);
                        $query->setUser($builder->getOption('user'));
                        $query->setWithCustomer(true);

                        return $repo->getQueryBuilderForFormType($query);
                    },
                ]));
            }
        );
    }

    protected function addActivity(string $id, string $projectId, FormBuilderInterface $builder, ?Activity $activity = null, ?Project $project = null, array $options = [])
    {
        $options = array_merge(['placeholder' => '', 'query_builder_for_user' => true], $options);

        $options['projects'] = $project;
        $options['activities'] = $activity;
        $options['data'] = $activity;

        $builder->add($id, ActivityType::class, $options);

        // replaces the activity select after submission, to make sure only activities for the selected project are displayed
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($id, $projectId, $options) {
                $data = $event->getData();
                if (!isset($data[$projectId]) || empty($data[$projectId])) {
                    return;
                }

                $options['projects'] = $data[$projectId];

                $event->getForm()->add($id, ActivityType::class, $options);
            }
        );
    }
}
