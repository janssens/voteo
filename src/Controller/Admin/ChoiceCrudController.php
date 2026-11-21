<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Concern\OwnedByUserTrait;
use App\Entity\Choice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ChoiceCrudController extends AbstractCrudController
{
    use OwnedByUserTrait;

    public static function getEntityFqcn(): string
    {
        return Choice::class;
    }

    protected static function ownerPath(): array
    {
        return ['owned_question.owner', ['owned_question' => 'entity.question']];
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('js/admin-choice-theme.js');
    }

    public function configureFields(string $pageName): iterable
    {
        $themeChoices = [];
        $themeColors = [];
        foreach (Choice::THEMES as $key => [$label, $bg, $text, $border]) {
            $themeChoices[$label] = $key;
            $themeColors[$key] = ['color' => $bg, 'textColor' => $text, 'borderColor' => $border];
        }

        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('label','Option'),
            // Unmapped: only pre-fills the three color fields below (see public/js/admin-choice-theme.js)
            ChoiceField::new('theme', 'Thème')
                ->onlyOnForms()
                ->setChoices($themeChoices)
                ->renderAsNativeWidget()
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('required', false)
                ->setFormTypeOption('placeholder', 'Personnalisé')
                ->setFormTypeOption('attr', [
                    'data-choice-theme' => json_encode($themeColors, JSON_THROW_ON_ERROR),
                ])
                ->setHelp('Choisir un thème remplit les couleurs, qui restent modifiables.'),
            ColorField::new('color','Fond'),
            ColorField::new('textColor','Texte'),
            ColorField::new('borderColor','Bordure'),
        ];
    }
}
