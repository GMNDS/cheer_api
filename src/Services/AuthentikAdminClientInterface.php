<?php

namespace Cheer\Services;

interface AuthentikAdminClientInterface
{
    /** @return array<string, mixed> */
    public function createUser(string $name, string $email, string $password, string $tipo): array;

    /** @param array<string, mixed> $user */
    public function localIdentifier(array $user): string;

    /** @param array<string, mixed> $user */
    public function deleteUserIfCreated(array $user): void;
}