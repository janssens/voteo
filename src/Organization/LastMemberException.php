<?php

namespace App\Organization;

use App\Entity\Organization;

final class LastMemberException extends \DomainException
{
    public function __construct(public readonly ?Organization $organization)
    {
        parent::__construct('Vous êtes le dernier membre de cette organisation : supprimez-la, ou invitez quelqu\'un avant de partir.');
    }
}
