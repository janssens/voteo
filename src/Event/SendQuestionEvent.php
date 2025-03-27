<?php

namespace App\Event;

use App\Entity\Question;
use Symfony\Contracts\EventDispatcher\Event;

class SendQuestionEvent extends Event
{
    public const NAME = 'question.sendmail';
    public const NAME_SEND_AGAIN = 'question.sendmailagain';

    protected Question $question;
    public function __construct(Question $question)
    {
        $this->question = $question;
    }
    public function getQuestion(): Question
    {
        return $this->question;
    }

}