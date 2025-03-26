<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Event\SendQuestionEvent;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class QuestionCrudController extends AbstractCrudController
{
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }
    
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
            BooleanField::new('sent')->hideOnForm(),
            TextField::new('subject','Sujet'),
            TextEditorField::new('intro','Texte avant question'),
            CollectionField::new('choices','Choix')->useEntryCrudForm(ChoiceCrudController::class),
            BooleanField::new('is_multichoices','Plusieurs réponses possibles'),
            TextEditorField::new('outro','Texte après question'),
            AssociationField::new('lists',label: 'Liste(s) concernée(s)')
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $sendQuestion = Action::new('sendQuestion', 'Envoyer', 'fa fa-send')
        ->displayIf(static function (Question $entity) {
            return !$entity->isSent();
        })
        ->displayAsButton()
        ->linkToCrudAction('sendQuestion');
        
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $sendQuestion);
    }
    
    public function sendQuestion(AdminContext $context)
    {
        $question = $context->getEntity()->getInstance();
        $event = new SendQuestionEvent($question);
        $this->eventDispatcher->dispatch($event, SendQuestionEvent::NAME);

        return $this->redirect($context->getReferrer());
    }


}
