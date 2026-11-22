<?php

namespace App\Import;

use App\Entity\Contact;
use App\Entity\ContactList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Importe des contacts dans une liste depuis un fichier CSV.
 *
 * Tolérant aux exports courants (Excel, LibreOffice, Google Sheets, outils de gestion d'adhérents) :
 *  - séparateur détecté automatiquement : point-virgule, virgule ou tabulation ;
 *  - encodage UTF-8 (avec ou sans BOM) ou Windows-1252 / ISO-8859-1, converti en UTF-8 ;
 *  - ligne d'en-tête reconnue par ses libellés (prénom, nom, email…), dans n'importe quel ordre.
 *    Sans en-tête, les colonnes sont lues dans l'ordre prénom, nom, email
 *    (ou email seul si le fichier n'a qu'une colonne).
 *
 * Les adresses invalides sont ignorées et signalées. Les adresses déjà présentes dans la liste
 * (ou en double dans le fichier) ne créent pas de doublon : elles sont ignorées, ou leurs prénom
 * et nom sont mis à jour si l'option est cochée.
 */
class ContactCsvImporter
{
    public const MAX_ROWS = 5000;

    private const COLUMN_ALIASES = [
        'firstname' => ['prenom', 'firstname', 'first name', 'first_name', 'given name', 'givenname'],
        'lastname' => ['nom', 'lastname', 'last name', 'last_name', 'nom de famille', 'family name', 'surname'],
        'email' => ['email', 'e-mail', 'e mail', 'mail', 'courriel', 'adresse email', 'adresse e-mail', 'adresse mail', 'email address', 'e-mail address'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
    ) {
    }

    public function import(ContactList $list, string $content, bool $updateExisting = false): ContactImportReport
    {
        $report = new ContactImportReport();

        $content = $this->toUtf8($content);
        // Clé = numéro de ligne dans le fichier (à partir de 1) ; lignes vides ou sans aucune valeur ignorées
        $delimiter = null;
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $index => $line) {
            if ('' === trim($line)) {
                continue;
            }
            $delimiter ??= $this->detectDelimiter($line);
            $cells = array_map('trim', str_getcsv($line, $delimiter, '"', ''));
            if ('' !== implode('', $cells)) {
                $rows[$index + 1] = $cells;
            }
        }
        if (!$rows) {
            $report->addError(0, 'Le fichier est vide.');

            return $report;
        }

        // Ligne d'en-tête : reconnue si elle contient au moins une colonne email ou prénom/nom connue
        $columns = $this->mapHeader(reset($rows));
        if (null !== $columns) {
            unset($rows[array_key_first($rows)]);
            if (!isset($columns['email'])) {
                $report->addError(0, 'Aucune colonne « email » trouvée dans l\'en-tête.');

                return $report;
            }
        } else {
            $columns = 1 === count(reset($rows)) ? ['email' => 0] : ['firstname' => 0, 'lastname' => 1, 'email' => 2];
        }

        if (count($rows) > self::MAX_ROWS) {
            $report->addError(0, sprintf('Le fichier contient %d lignes : %d au maximum par import.', count($rows), self::MAX_ROWS));

            return $report;
        }

        $existing = [];
        foreach ($list->getContacts() as $contact) {
            $existing[mb_strtolower((string) $contact->getEmail())] = $contact;
        }

        foreach ($rows as $lineNumber => $row) {
            $email = mb_strtolower($row[$columns['email']] ?? '');
            $firstname = isset($columns['firstname']) ? ($row[$columns['firstname']] ?? '') : '';
            $lastname = isset($columns['lastname']) ? ($row[$columns['lastname']] ?? '') : '';

            if ('' === $email) {
                $report->addError($lineNumber, 'adresse email manquante');
                continue;
            }
            if (count($this->validator->validate($email, new Email(mode: Email::VALIDATION_MODE_STRICT))) > 0) {
                $report->addError($lineNumber, sprintf('adresse email invalide « %s »', $email));
                continue;
            }

            if (isset($existing[$email])) {
                $contact = $existing[$email];
                $changed = ('' !== $firstname || '' !== $lastname)
                    && ($contact->getFirstname() !== $firstname || $contact->getLastname() !== $lastname);
                // Un contact sans id vient de ce même import : doublon dans le fichier, on garde la première ligne
                if ($updateExisting && $changed && null !== $contact->getId()) {
                    $contact->setFirstname(mb_substr($firstname, 0, 255))->setLastname(mb_substr($lastname, 0, 255));
                    $report->updated++;
                } else {
                    $report->skipped++;
                }
                continue;
            }

            $contact = (new Contact())
                ->setFirstname(mb_substr($firstname, 0, 255))
                ->setLastname(mb_substr($lastname, 0, 255))
                ->setEmail($email);
            $list->addContact($contact);
            $this->em->persist($contact);
            $existing[$email] = $contact;
            $report->added++;
        }

        $this->em->flush();

        return $report;
    }

    private function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ';';
    }

    /**
     * @return array<string, int>|null colonne => index, ou null si la ligne n'est pas un en-tête
     */
    private function mapHeader(array $row): ?array
    {
        $columns = [];
        foreach ($row as $index => $cell) {
            $label = $this->normalize($cell);
            foreach (self::COLUMN_ALIASES as $column => $aliases) {
                if (!isset($columns[$column]) && in_array($label, $aliases, true)) {
                    $columns[$column] = $index;
                    break;
                }
            }
        }

        return $columns ?: null;
    }

    private function normalize(string $label): string
    {
        $label = mb_strtolower(trim($label, " \t\"'"));
        $label = strtr($label, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'ç' => 'c', 'ï' => 'i', 'î' => 'i', 'ô' => 'o']);

        return preg_replace('/\s+/', ' ', $label);
    }
}
