<?php

namespace Tests\Feature;

use Cheer\Controllers\AuthController;
use Cheer\Core\Request;
use stdClass;
use Tests\TestCase;

final class MobileAuthFlowTest extends TestCase
{
    public function testMobileCallbackCreatesOneTimeCodeWithoutCreatingBrowserSession(): void
    {
        $_SESSION = [
            'oauth_state' => 'oauth-state',
            'oauth_nonce' => 'oauth-nonce',
            'oauth_code_verifier' => 'code-verifier',
            'oauth_flow' => 'mobile',
            'mobile_app_redirect_uri' => 'cheer://auth/callback',
            'mobile_client_state' => 'client-state',
        ];

        $codes = new FakeMobileAuthCodeStore();
        $result = $this->render($this->controller(mobileAuthCodes: $codes)->mobileCallback(
            new Request('GET', '/api/auth/mobile/callback', [], [
                'code' => 'auth-code',
                'state' => 'oauth-state',
            ], [])
        ));

        self::assertSame(302, $result['status']);
        self::assertSame('auth-code', FakeMobileOAuthClient::$lastCode);
        self::assertSame('code-verifier', FakeMobileOAuthClient::$lastVerifier);
        self::assertSame('http://localhost:8000/api/auth/mobile/callback', FakeMobileOAuthClient::$lastRedirectUri);
        self::assertSame('Voluntario Mobile', $codes->createdProfile['nome']);
        self::assertSame('access-token', $codes->createdTokens['access_token']);
        self::assertArrayNotHasKey('profile', $_SESSION);
        self::assertArrayNotHasKey('oauth_state', $_SESSION);
    }

    public function testMobileExchangeConsumesOneTimeCodeAndCreatesApiSession(): void
    {
        $codes = new FakeMobileAuthCodeStore([
            'profile' => $this->profile(),
            'tokens' => ['access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_at' => 123],
        ]);

        $result = $this->render($this->controller(mobileAuthCodes: $codes)->mobileExchange(
            new Request('POST', '/api/auth/mobile/exchange', [], [], ['code' => 'one-time-code'])
        ));

        self::assertSame(200, $result['status']);
        self::assertSame('one-time-code', $codes->consumedCode);
        self::assertSame('Voluntario Mobile', $_SESSION['profile']['nome']);
        self::assertSame('access-token', $_SESSION['authentik_tokens']['access_token']);

        $body = json_decode($result['body'], true);
        self::assertSame('success', $body['status']);
        self::assertSame('voluntario', $body['data']['tipo']);
    }

    public function testMobileExchangeRejectsInvalidCode(): void
    {
        $result = $this->render($this->controller(mobileAuthCodes: new FakeMobileAuthCodeStore())->mobileExchange(
            new Request('POST', '/api/auth/mobile/exchange', [], [], ['code' => 'expired-code'])
        ));

        self::assertSame(400, $result['status']);
        self::assertArrayNotHasKey('profile', $_SESSION);
    }

    private function controller(?FakeMobileAuthCodeStore $mobileAuthCodes = null): AuthController
    {
        return new AuthController(
            new FakeMobileOAuthClient(),
            new FakeMobileTokenValidator(),
            new FakeMobileProfileRepository(),
            $mobileAuthCodes ?? new FakeMobileAuthCodeStore()
        );
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        return [
            'tipo' => 'voluntario',
            'id' => 99,
            'nome' => 'Voluntario Mobile',
            'email' => 'mobile@teste.local',
            'telefone' => '11999999999',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
        ];
    }
}

final class FakeMobileOAuthClient
{
    public static ?string $lastCode = null;
    public static ?string $lastVerifier = null;
    public static ?string $lastRedirectUri = null;

    /** @return array<string, mixed> */
    public function exchangeCode(string $code, string $codeVerifier, ?string $redirectUri = null): array
    {
        self::$lastCode = $code;
        self::$lastVerifier = $codeVerifier;
        self::$lastRedirectUri = $redirectUri;

        return [
            'id_token' => 'id-token',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ];
    }
}

final class FakeMobileTokenValidator
{
    public function validateIdToken(string $token, ?string $nonce = null): stdClass
    {
        return (object) ['sub' => 'authentik-user'];
    }
}

final class FakeMobileProfileRepository
{
    /** @return array<string, mixed>|null */
    public function findByAuthentikUser(string $authentikUser): ?array
    {
        return [
            'tipo' => 'voluntario',
            'id' => 99,
            'nome' => 'Voluntario Mobile',
            'email' => 'mobile@teste.local',
            'telefone' => '11999999999',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
        ];
    }
}

final class FakeMobileAuthCodeStore
{
    /** @var array<string, mixed> */
    public array $createdProfile = [];

    /** @var array<string, mixed> */
    public array $createdTokens = [];

    public ?string $consumedCode = null;

    /** @param array{profile: array<string, mixed>, tokens: array<string, mixed>}|null $consumeResult */
    public function __construct(private readonly ?array $consumeResult = null)
    {
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $tokens */
    public function create(array $profile, array $tokens, int $ttlSeconds): string
    {
        $this->createdProfile = $profile;
        $this->createdTokens = $tokens;

        return 'one-time-code';
    }

    /** @return array{profile: array<string, mixed>, tokens: array<string, mixed>}|null */
    public function consume(string $code): ?array
    {
        $this->consumedCode = $code;

        return $this->consumeResult;
    }
}
