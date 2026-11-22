<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Rectification des informations du compte (art. 16 du RGPD).
 */
class AccountFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new NotBlank(message: 'Merci d\'indiquer votre prénom.')],
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Merci d\'indiquer votre nom.')],
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'help' => 'Elle sert d\'adresse de réponse aux emails envoyés à vos membres.',
                'constraints' => [
                    new NotBlank(message: 'Merci d\'indiquer votre adresse email.'),
                    new Email(message: 'Cette adresse email ne semble pas valide.'),
                ],
                'attr' => ['autocomplete' => 'email'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
