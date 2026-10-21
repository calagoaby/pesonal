<?php

namespace App\Actions;

enum InviteResult
{
    case Invited;
    case AlreadyInvited;
    case NotAnEmail;
}
