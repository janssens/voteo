<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class QuestionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Question::class;
    }


    public function createEntity(string $entityFqcn): Question
    {
        $question = new Question();
        $question->setOwner($this->getUser());

        return $question;
    }
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            HiddenField::new('owner'),
            TextField::new('subject','Sujet'),
            TextEditorField::new('intro','Texte avant question'),
            CollectionField::new('choices','Choix')->useEntryCrudForm(ChoiceCrudController::class),
            BooleanField::new('is_multichoices','Plusieurs réponses possibles'),
            TextEditorField::new('outro','Texte après question'),
        ];
    }
    
}
