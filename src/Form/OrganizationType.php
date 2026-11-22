<?php

namespace App\Form;

use App\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Création et modification d'une organisation (les contraintes sont sur l'entité).
 */
class OrganizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'organisation',
                'help' => 'Il apparaît comme expéditeur des emails : « Asso Jardins partagés via Voteo ».',
                'attr' => ['autocomplete' => 'organization'],
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'Adresse email de contact',
                'help' => 'Les réponses des membres aux emails de l\'organisation arrivent à cette adresse.',
                'attr' => ['autocomplete' => 'email'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organization::class,
        ]);
    }
}
