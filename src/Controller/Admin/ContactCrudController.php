<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Concern\OwnedByUserTrait;
use App\Entity\Contact;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ContactCrudController extends AbstractCrudController
{
    use OwnedByUserTrait;

    public static function getEntityFqcn(): string
    {
        return Contact::class;
    }

    protected static function scopeRoot(): array
    {
        return ['owned_list', ['owned_list' => 'entity.list']];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('firstname','Prénom'),
            TextField::new('lastname','Nom'),
            EmailField::new('email','Courriel'),
        ];
    }

}
