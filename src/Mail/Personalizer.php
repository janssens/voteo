<?php

namespace App\Mail;

use App\Entity\Contact;

/**
 * Replaces the recipient variables written in the question texts:
 *   {{ prenom }} / {{ firstname }}  -> first name
 *   {{ nom }}    / {{ lastname }}   -> last name
 * Case and inner spaces (including &nbsp; from the rich text editor) are ignored.
 */
final class Personalizer
{
    private const SPACE = '(?:\s|&nbsp;|\x{00A0})*';

    public const VARIABLES_HELP = 'Variables disponibles : {{ prenom }} (ou {{ firstname }}) et {{ nom }} (ou {{ lastname }}), remplacées par le prénom et le nom de chaque destinataire.';

    /**
     * @param Contact|array{firstname?: ?string, lastname?: ?string}|null $contact
     * @param bool $html escape the names for an HTML context (intro / outro)
     */
    public static function personalize(?string $text, Contact|array|null $contact, bool $html = true): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        if ($contact instanceof Contact) {
            $firstname = $contact->getFirstname();
            $lastname = $contact->getLastname();
        } else {
            $firstname = $contact['firstname'] ?? null;
            $lastname = $contact['lastname'] ?? null;
        }

        $values = [
            'firstname' => (string) $firstname,
            'lastname' => (string) $lastname,
        ];
        if ($html) {
            $values = array_map(static fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_HTML5), $values);
        }

        $s = self::SPACE;

        return preg_replace_callback(
            "/\\{\\{{$s}(pr(?:é|e|&eacute;)nom|firstname|nom|lastname){$s}\\}\\}/iu",
            static function (array $m) use ($values): string {
                $name = mb_strtolower($m[1]);

                return in_array($name, ['nom', 'lastname'], true) ? $values['lastname'] : $values['firstname'];
            },
            $text,
        ) ?? $text;
    }
}
