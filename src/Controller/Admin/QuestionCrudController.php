<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Controller\Admin\Concern\OwnedByUserTrait;
use App\Entity\Answer;
use App\Event\SendQuestionEvent;
use App\Mail\Personalizer;
use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\PercentField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class QuestionCrudController extends AbstractCrudController
{
    use OwnedByUserTrait;

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

    protected static function scopeRoot(): array
    {
        return ['entity', []];
    }

    /**
     * Question gérée par l'action personnalisée en cours, après contrôle du propriétaire.
     * (EasyAdmin n'applique pas l'entity permission aux actions personnalisées.)
     */
    private function getOwnedQuestion(AdminContext $context): Question
    {
        // EasyAdmin vide l'instance quand l'entity permission est refusée : null = pas le droit
        $question = $context->getEntity()->getInstance();
        if (!$question instanceof Question) {
            throw $this->createAccessDeniedException();
        }
        $this->denyAccessUnlessGranted(OwnerVoter::OWNER, $question);

        return $question;
    }


    public function createEntity(string $entityFqcn): Question
    {
        $question = new Question();
        $question->setOwner($this->getUser());
        $question->setOrganization($this->currentContext->getOrganization());

        return $question;
    }
    public function configureCrud(Crud $crud): Crud
    {
        // Redéfinit celui du trait : sans cet appel, détail / édition / suppression ne seraient pas protégés.
        return $this->restrictToOwner($crud)
            // Form + live email preview side by side
            ->overrideTemplate('crud/edit', 'admin/question/edit.html.twig')
            ->overrideTemplate('crud/new', 'admin/question/new.html.twig')
            ->renderContentMaximized();
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            // Theme picker of the embedded choice forms
            ->addJsFile('js/admin-choice-theme.js')
            ->addJsFile('js/admin-email-preview.js')
            ->addJsFile('js/admin-question-form.js');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            BooleanField::new('sent')->hideOnForm(),
            TextField::new('subject','Sujet')->setHelp(Personalizer::VARIABLES_HELP),
            ...$this->authorColumn(),
            TextEditorField::new('intro','Texte avant question')->setHelp(Personalizer::VARIABLES_HELP),
            CollectionField::new('choices','Choix')->useEntryCrudForm(ChoiceCrudController::class),
            BooleanField::new('is_multichoices','Plusieurs réponses possibles'),
            BooleanField::new('anonymous', 'Réponses anonymes')
                ->onlyOnForms()
                ->setHelp('Les participants voient les résultats sans les prénoms des votants (dans les pages de résultats et le mail de résultats). Vous, vous les voyez toujours.'),
            BooleanField::new('resultsVisibleBeforeVote', 'Résultats visibles avant participation')
                ->setHelp('Le mail contiendra un lien pour consulter les résultats actuels avant de voter.'),
            DateTimeField::new('closesAt', 'Date de clôture')
                // setTimezone() ne concerne que l'affichage : le formulaire a besoin de ses propres fuseaux
                ->setTimezone('Europe/Paris')
                ->setRequired(false)
                ->setFormTypeOptions([
                    'input' => 'datetime_immutable',
                    'model_timezone' => 'UTC',
                    'view_timezone' => 'Europe/Paris',
                ])
                ->setHelp('Facultatif. Heure de Paris. Au-delà, on ne peut plus répondre ni modifier sa réponse.'),
            BooleanField::new('sendResultsOnClose', 'Envoyer les résultats à la clôture')
                ->onlyOnForms()
                // Affiché uniquement si une date de clôture est saisie (public/js/admin-email-preview.js)
                ->addCssClass('js-send-results-on-close')
                ->setHelp('Une fois la date de clôture passée, tous les destinataires reçoivent les résultats par email.'),
            TextEditorField::new('outro','Texte après question')->setHelp(Personalizer::VARIABLES_HELP),
            AssociationField::new('lists',label: 'Liste(s) concernée(s)')
                // Uniquement les listes du même contexte que la question : son organisation, ou les listes personnelles de son auteur.
                // Le formulaire refuse aussi toute liste hors de cette requête (choix soumis invalides).
                ->setQueryBuilder(function (QueryBuilder $qb) {
                    $question = $this->getContext()?->getEntity()?->getInstance();

                    return $question instanceof Question
                        ? self::applyScope($qb, 'entity', $question->getOrganization(), $question->getOwner())
                        : self::applyScope($qb, 'entity', $this->currentContext->getOrganization(), $this->getUser());
                }),
            PercentField::new('responseRate',label: 'Réponses')->hideOnForm(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $sendQuestion = Action::new('sendQuestion', 'Envoyer', 'fa fa-send')
        ->displayIf(static function (Question $entity) {
            return !$entity->isSent() && !$entity->isClosed();
        })
        ->displayAsButton()
        ->linkToCrudAction('sendQuestion');

        $resendQuestion = Action::new('resendQuestion', 'Relancer les sans réponse', 'fa fa-send')
        ->displayIf(static function (Question $entity) {
            return $entity->isSent() && !$entity->isClosed() && $entity->getResponseRate() < 1;
        })
        ->displayAsButton()
        ->linkToCrudAction('resendQuestion');

        $duplicate = Action::new('duplicateQuestion', 'Duplicate', 'fa fa-copy')
        ->displayAsButton()
        ->linkToCrudAction('duplicateQuestion');
        
        $showResults = Action::new('showResults', 'Résultats', 'fa fa-chart-bar')
            ->displayIf(static fn (Question $entity) => $entity->isSent())
            ->linkToCrudAction('showResults');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $showResults)
            ->add(Crud::PAGE_DETAIL, $showResults)
            ->add(Crud::PAGE_INDEX, $duplicate)
            ->add(Crud::PAGE_INDEX, $sendQuestion)
            ->add(Crud::PAGE_INDEX, $resendQuestion);
    }
    
    public function sendQuestion(AdminContext $context)
    {
        $question = $this->getOwnedQuestion($context);
        $event = new SendQuestionEvent($question);
        $this->eventDispatcher->dispatch($event, SendQuestionEvent::NAME);

        $url = $this->adminUrlGenerator
            ->setController(QuestionCrudController::class)
            ->setAction(Action::INDEX)
            // ->setEntityId($question->getId())
            ->generateUrl();
    
        return $this->redirect($url);
    }

    #[AdminRoute(path: '/{entityId}/resultats', name: 'results')]
    public function showResults(AdminContext $context): Response
    {
        $question = $this->getOwnedQuestion($context);

        $total = $question->getNbOfResponses();
        $results = [];
        foreach ($question->getChoices() as $choice) {
            $voters = $choice->getAnswers()
                ->filter(static fn (Answer $a) => $a->getQuestion() === $question)
                ->map(static fn (Answer $a) => $a->getContact())
                ->toArray();
            usort($voters, static fn ($a, $b) => strcasecmp((string) $a->getFullName(), (string) $b->getFullName()));
            $results[] = [
                'choice' => $choice,
                'count' => \count($voters),
                'percent' => $total > 0 ? round(\count($voters) / $total * 100) : 0,
                'voters' => $voters,
            ];
        }

        $pending = $question->getAnswers()
            ->filter(static fn (Answer $a) => $a->getChoices()->isEmpty())
            ->map(static fn (Answer $a) => $a->getContact())
            ->toArray();
        usort($pending, static fn ($a, $b) => strcasecmp((string) $a->getFullName(), (string) $b->getFullName()));

        return $this->render('admin/question_results.html.twig', [
            'question' => $question,
            'results' => $results,
            'total' => $total,
            'recipients' => \count($question->getAnswers()),
            'pending' => $pending,
            'back_url' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->unset('entityId')
                ->generateUrl(),
            'edit_url' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($question->getId())
                ->generateUrl(),
        ]);
    }

    public function resendQuestion(AdminContext $context)
    {
        $question = $this->getOwnedQuestion($context);
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
        $question = $this->getOwnedQuestion($context);

        $clone = clone $question;

        // Custom logic for the clone, for example:
        $clone->setSubject('[clone] '.$question->getSubject());
        // Le clone reste dans le contexte de l'original (mêmes listes). Dans une organisation, son auteur est celui qui duplique.
        if (null !== $question->getOrganization()) {
            $clone->setOwner($this->getUser());
        }
        $clone->setSent(false);
        $clone->setResultsSentAt(null);

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
