<?php

namespace App\Import;

/**
 * Bilan d'un import de contacts : ajoutés, mis à jour, ignorés (déjà présents) et lignes en erreur.
 */
class ContactImportReport
{
    public int $added = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var list<array{line: int, message: string}> ligne 0 = erreur sur le fichier entier */
    public array $errors = [];

    public function addError(int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }

}
