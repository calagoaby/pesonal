<?php

namespace App\Exceptions;

use RuntimeException;

// Google says it hasn't verified the address. Linking hands over an existing account by
// email, so an unproven one must not reach it.
class ProviderEmailUnverified extends RuntimeException {}
