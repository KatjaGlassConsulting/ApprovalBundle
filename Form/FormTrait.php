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
use App\Form\Type\DescriptionType;
use App\Form\Type\ProjectType;
use App\Form\Type\TagsType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Helper functions to manage dependent customer-project-activity fields.
 *
 * If you always want to show the list of all available projects/activities, use the form types directly.
 */
trait FormTrait
{
    protected function addProject(string $id, FormBuilderInterface $builder, ?Project $project = null, array $options = [])
    {
        $options = array_merge([
            'class' => Project::class,
            'choice_label' => 'name',
            'mapped' => false,
            'activity_enabled' => true,
            'query_builder_for_user' => true,
            'join_customer' => true,
            'data' => $project
        ], $options);

        $builder->add($id, ProjectType::class, array_merge($options, [
            'projects' => $project,
        ]));
    }

    protected function addActivity(string $id, FormBuilderInterface $builder, ?Activity $activity = null, ?Project $project = null, array $options = [])
    {
        $builder->add($id, ChoiceType::class, [
            'choices' => [], // Empty choice when no project is selected
            'placeholder' => 'Select an activity',
            'mapped' => false,
        ]);

        // replaces the activity select after submission, to make sure only activities for the selected project are displayed
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($options, $project, $id) {
                $form = $event->getForm();

                if ($project) {
                    $form->add($id, EntityType::class, [
                        'class' => Activity::class,
                        'query_builder' => function (EntityRepository $repo) use ($project) {
                            return $repo->createQueryBuilder('a')
                                ->where('a.project = :project')
                                ->setParameter('project', $project);
                        },
                        'choice_label' => 'name',
                        'placeholder' => 'Select an activity',
                        'mapped' => false,
                    ]);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options, $project, $activity, $id) {
                $form = $event->getForm();

                if ($project !== null && $project->getId()) {
                    $form->add($id, EntityType::class, [
                        'class' => Activity::class,
                        'query_builder' => function (EntityRepository $er) use ($project, $activity) {
                            return $er->createQueryBuilder('a')
                                ->where('a.project = :project')
                                ->setParameter('project', $project->getId());
                        },
                        'choice_label' => 'name',
                        'placeholder' => 'Select an activity',
                        'data' => $activity,
                        'mapped' => false,
                    ]);
                }
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}
