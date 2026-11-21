<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use App\Mail\Personalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{

    public function getFilters(): array
    {
        return [
            new TwigFilter('base64_encode', 'base64_encode'),
            new TwigFilter('base64_decode', 'base64_decode'),
            // {{ question.intro|personalize(contact)|raw }} / {{ question.subject|personalize(contact, false) }}
            new TwigFilter('personalize', [Personalizer::class, 'personalize']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            //new TwigFunction('config',[$this,'config'])
        ];
    }

}
