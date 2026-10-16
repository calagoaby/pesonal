<?php

namespace App\Exceptions;

use RuntimeException;

// A first-time provider sign-in would open a new account, and sign-up is invite-only.
class ProviderEmailNotInvited extends RuntimeException {}
