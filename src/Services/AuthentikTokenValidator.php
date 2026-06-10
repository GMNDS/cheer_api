<?php

namespace Cheer\Services;

use Cheer\Core\Auth;
use stdClass;

final class AuthentikTokenValidator
{
    public function validateIdToken(string $token, ?string $nonce = null): stdClass
    {
        return Auth::validateIdToken($token, $nonce);
    }
}
