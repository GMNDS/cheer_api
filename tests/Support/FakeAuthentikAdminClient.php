<?php

namespace Tests\Support;

use Cheer\Services\AuthentikAdminClientInterface;

final class FakeAuthentikAdminClient implements AuthentikAdminClientInterface
{
    private int $nextPk = 1000;

    public function createUser(string $name, string $email, string $password, string $tipo): array
    {
        $this->nextPk++;

        return [
            'pk' => $this->nextPk,
            'uid' => "fake-{$tipo}-{$this->nextPk}",
            'username' => strtolower(trim($email)),
            'email' => strtolower(trim($email)),
            'name' => $name,
        ];
    }

    public function localIdentifier(array $user): string
    {
        return (string) ($user['uid'] ?? $user['username'] ?? $user['email'] ?? $user['pk'] ?? '');
    }

    public function deleteUserIfCreated(array $user): void
    {
    }
}