<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Concern\OwnedByUserTrait;
use App\Entity\ContactList;
use App\Import\ContactCsvImporter;
use App\Security\Voter\OwnerVoter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ContactListCrudController extends AbstractCrudController
{
    use OwnedByUserTrait;

    private const MAX_FILE_SIZE = 2 * 1024 * 1024;

    public function __construct(
        private ContactCsvImporter $importer,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContactList::class;
    }

    protected static function ownerPath(): array
    {
        return ['entity.owner', []];
    }

    public function createEntity(string $entityFqcn): ContactList
    {
        return (new ContactList())->setOwner($this->getUser());
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name','Nom'),
            CollectionField::new('contacts','Contacts')
            ->setEntryIsComplex(true)
            ->useEntryCrudForm(ContactCrudController::class),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $import = Action::new('importCsv', 'Importer un CSV', 'fa fa-file-import')
            ->linkToCrudAction('importCsv');

        return $actions
            ->add(Crud::PAGE_INDEX, $import)
            ->add(Crud::PAGE_DETAIL, $import)
            ->add(Crud::PAGE_EDIT, $import);
    }

    /**
     * Ajoute à la liste les contacts d'un fichier CSV (voir ContactCsvImporter pour les formats acceptés).
     */
    #[AdminRoute(path: '/{entityId}/import', name: 'import', options: ['methods' => ['GET', 'POST']])]
    public function importCsv(AdminContext $context): Response
    {
        // EasyAdmin vide l'instance quand l'entity permission est refusée : null = pas le droit
        $list = $context->getEntity()->getInstance();
        if (!$list instanceof ContactList) {
            throw $this->createAccessDeniedException();
        }
        $this->denyAccessUnlessGranted(OwnerVoter::OWNER, $list);

        $request = $context->getRequest();
        $report = null;
        $error = null;

        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $file */
            $file = $request->files->get('csv_file');

            if (!$this->isCsrfTokenValid('import_csv_'.$list->getId(), (string) $request->request->get('_token'))) {
                $error = 'Votre session a expiré, merci de réessayer.';
            } elseif (!$request->request->getBoolean('consent')) {
                $error = 'Merci de confirmer que ces personnes s\'attendent à être consultées.';
            } elseif (!$file || !$file->isValid()) {
                $error = 'Merci de choisir un fichier CSV.';
            } elseif ($file->getSize() > self::MAX_FILE_SIZE) {
                $error = 'Le fichier est trop volumineux (2 Mo maximum).';
            } elseif (!in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt', 'tsv'], true)) {
                $error = 'Le fichier doit être au format CSV (.csv). Depuis Excel ou LibreOffice : « Enregistrer sous » > CSV.';
            } else {
                $report = $this->importer->import($list, (string) file_get_contents($file->getPathname()), $request->request->getBoolean('update_existing'));
            }
        }

        $listUrl = fn (string $action) => $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($list->getId())
            ->generateUrl();

        return $this->render('admin/contact_list_import.html.twig', [
            'list' => $list,
            'report' => $report,
            'error' => $error,
            'max_rows' => ContactCsvImporter::MAX_ROWS,
            'edit_url' => $listUrl(Action::EDIT),
            'back_url' => $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->unset('entityId')->generateUrl(),
        ], new Response(status: $error ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
