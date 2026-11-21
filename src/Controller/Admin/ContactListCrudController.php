<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Concern\OwnedByUserTrait;
use App\Entity\ContactList;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ContactListCrudController extends AbstractCrudController
{
    use OwnedByUserTrait;

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
    
}
