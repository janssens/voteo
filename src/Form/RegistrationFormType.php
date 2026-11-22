<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new NotBlank(message: 'Merci d\'indiquer votre prénom.')],
                'attr' => ['autocomplete' => 'given-name', 'placeholder' => 'Camille'],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Merci d\'indiquer votre nom.')],
                'attr' => ['autocomplete' => 'family-name', 'placeholder' => 'Martin'],
            ])
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                'constraints' => [new NotBlank(message: 'Merci de choisir un nom d\'utilisateur.')],
                'help' => 'C\'est avec lui que vous vous connecterez.',
                'attr' => ['autocomplete' => 'username', 'placeholder' => 'camille.martin'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(message: 'Merci d\'indiquer votre adresse email.'),
                    new Email(message: 'Cette adresse email ne semble pas valide.'),
                ],
                'attr' => ['autocomplete' => 'email', 'placeholder' => 'camille@association.fr'],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'label' => 'Mot de passe',
                'help' => '6 caractères minimum.',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Merci de choisir un mot de passe.'),
                    new Length(
                        min: 6,
                        // max length allowed by Symfony for security reasons
                        max: 4096,
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                // libellé avec liens défini dans templates/registration/register.html.twig
                'label' => 'J\'ai lu et j\'accepte la politique de confidentialité',
                'constraints' => [
                    new IsTrue(message: 'Merci d\'accepter la politique de confidentialité.'),
                ],
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
