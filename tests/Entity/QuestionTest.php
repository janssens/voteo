<?php

namespace App\Tests\Entity;

use App\Entity\Question;
use PHPUnit\Framework\TestCase;

final class QuestionTest extends TestCase
{
    /** Le seul destinataire a supprimé ses données : l'index des questions ne doit pas planter. */
    public function testResponseRateOfASentQuestionWithoutRecipientsIsZero(): void
    {
        $question = (new Question())->setSent(true);

        self::assertSame(0.0, $question->getResponseRate());
    }

    public function testAuthorNameIsNullOnceTheAccountIsDeleted(): void
    {
        self::assertNull((new Question())->setOwner(null)->getAuthorName());
    }
}
