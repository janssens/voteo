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
use EasyCorp\Bundle\EasyAdminBundle\Field\PercentField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class QuestionCrudController extends AbstractCrudController
{
    private $eventDispatcher;
    private $adminUrlGenerator;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager,EventDispatcherInterface $eventDispatcher,AdminUrlGenerator $adminUrlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->adminUrlGenerator = $adminUrlGenerator;
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
            AssociationField::new('lists',label: 'Liste(s) concernée(s)'),
            PercentField::new('reponseRate',label: 'Réponses'),
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

        $resendQuestion = Action::new('resendQuestion', 'Relancer les sans réponse', 'fa fa-send')
        ->displayIf(static function (Question $entity) {
            return $entity->isSent() && $entity->getReponseRate() < 1;
        })
        ->displayAsButton()
        ->linkToCrudAction('resendQuestion');

        $duplicate = Action::new('duplicateQuestion', 'Duplicate', 'fa fa-copy')
        ->displayAsButton()
        ->linkToCrudAction('duplicateQuestion');
        
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $duplicate)
            ->add(Crud::PAGE_INDEX, $sendQuestion)
            ->add(Crud::PAGE_INDEX, $resendQuestion);
    }
    
    public function sendQuestion(AdminContext $context)
    {
        $question = $context->getEntity()->getInstance();
        $event = new SendQuestionEvent($question);
        $this->eventDispatcher->dispatch($event, SendQuestionEvent::NAME);

        $url = $this->adminUrlGenerator
            ->setController(QuestionCrudController::class)
            ->setAction(Action::INDEX)
            // ->setEntityId($question->getId())
            ->generateUrl();
    
        return $this->redirect($url);
    }

    public function resendQuestion(AdminContext $context)
    {
        $question = $context->getEntity()->getInstance();
        $event = new SendQuestionEvent($question);
        $this->eventDispatcher->dispatch($event, SendQuestionEvent::NAME_SEND_AGAIN);

        $url = $this->adminUrlGenerator
            ->setController(QuestionCrudController::class)
            ->setAction(Action::INDEX)
            // ->setEntityId($question->getId())
            ->generateUrl();
    
        return $this->redirect($url);
    }

    public function duplicateQuestion(AdminContext $context)
    {
        /** @var Question $question */
        $question = $context->getEntity()->getInstance();
    
        $clone = clone $question;

        // Custom logic for the clone, for example:
        $clone->setSubject('[clone] '.$question->getSubject());
        $clone->setSent(false);

        foreach ($question->getChoices() as $choice)
        {
            $cloned_choice = clone $choice;
            $clone->addChoice($cloned_choice);
        }
    
        // Persist the cloned entity
        $this->entityManager->persist($clone);
        $this->entityManager->flush();
        
        $url = $this->adminUrlGenerator
            ->setController(QuestionCrudController::class)
            ->setAction(Action::INDEX)
            // ->setEntityId($clone->getId())
            ->generateUrl();
    
        return $this->redirect($url);
    }


}
