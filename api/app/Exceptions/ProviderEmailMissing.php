<?php

namespace App\Exceptions;

use RuntimeException;

// Some Facebook accounts are phone-only: with no email there's nothing to link or verify.
class ProviderEmailMissing extends RuntimeException {}
