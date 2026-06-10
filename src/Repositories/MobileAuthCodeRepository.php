<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use PDO;

final class MobileAuthCodeRepository
{
    /** @param array<string, mixed> $profile @param array<string, mixed> $tokens */
    public function create(array $profile, array $tokens, int $ttlSeconds): string
    {
        $code = self::base64UrlEncode(random_bytes(32));
        $now = time();

        $statement = Database::connection()->prepare(
            'INSERT INTO mobile_auth_codes (code_hash, profile_json, tokens_json, expires_at, created_at)
             VALUES (:code_hash, :profile_json, :tokens_json, :expires_at, :created_at)'
        );
        $statement->execute([
            'code_hash' => hash('sha256', $code),
            'profile_json' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tokens_json' => json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => gmdate('Y-m-d H:i:s', $now + max(60, $ttlSeconds)),
            'created_at' => gmdate('Y-m-d H:i:s', $now),
        ]);

        return $code;
    }

    /** @return array{profile: array<string, mixed>, tokens: array<string, mixed>}|null */
    public function consume(string $code): ?array
    {
        $connection = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                'SELECT id, profile_json, tokens_json, expires_at, consumed_at
                 FROM mobile_auth_codes
                 WHERE code_hash = :code_hash
                 LIMIT 1'
            );
            $statement->execute(['code_hash' => hash('sha256', $code)]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row) || $row['consumed_at'] !== null || (string) $row['expires_at'] <= $now) {
                $connection->rollBack();
                return null;
            }

            $update = $connection->prepare(
                'UPDATE mobile_auth_codes
                 SET consumed_at = :consumed_at
                 WHERE id = :id AND consumed_at IS NULL'
            );
            $update->execute([
                'consumed_at' => $now,
                'id' => $row['id'],
            ]);

            if ($update->rowCount() !== 1) {
                $connection->rollBack();
                return null;
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        $profile = json_decode((string) $row['profile_json'], true);
        $tokens = json_decode((string) $row['tokens_json'], true);

        if (!is_array($profile) || !is_array($tokens)) {
            return null;
        }

        return [
            'profile' => $profile,
            'tokens' => $tokens,
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
