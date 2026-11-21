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
            // Rich text (Trix HTML) => plain text for the text part of emails
            new TwigFilter('html_to_text', [self::class, 'htmlToText']),
            // '#'|repeat(5) => '#####' (text emails)
            new TwigFilter('repeat', static fn (string $s, int|float $times): string => str_repeat($s, max(0, (int) $times))),
        ];
    }

    public static function htmlToText(?string $html): string
    {
        if (null === $html || '' === trim($html)) {
            return '';
        }
        // Links: keep the target, "label (https://...)"
        $text = preg_replace_callback(
            '#<a\s[^>]*href=("|\')(.*?)\1[^>]*>(.*?)</a>#is',
            static fn (array $m) => trim(strip_tags($m[3])) === $m[2] ? $m[2] : trim(strip_tags($m[3])).' ('.$m[2].')',
            $html,
        );
        $text = preg_replace('#<li[^>]*>#i', '- ', $text);
        $text = preg_replace('#<br\s*/?>|</(div|p|li|h[1-6]|blockquote|pre|ul|ol)>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    public function getFunctions(): array
    {
        return [
            //new TwigFunction('config',[$this,'config'])
        ];
    }

}
