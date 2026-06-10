<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Config;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Core\Session;
use Cheer\Repositories\LogRepository;
use Cheer\Repositories\MobileAuthCodeRepository;
use Cheer\Repositories\ProfileRepository;
use Cheer\Services\AuthentikOAuthClient;
use Cheer\Services\AuthentikTokenValidator;
use OpenApi\Attributes as OA;
use RuntimeException;
use Throwable;

final class AuthController
{
    public function __construct(
        private readonly ?object $oauthClient = null,
        private readonly ?object $tokenValidator = null,
        private readonly ?object $profileRepository = null,
        private readonly ?object $mobileAuthCodes = null,
    ) {
    }

    #[OA\Get(
        path: '/api/auth/config',
        summary: 'Config publica de autenticacao',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Config de autenticacao', content: new OA\JsonContent(ref: '#/components/schemas/AuthConfigResponse')),
        ]
    )]
    public function config(): Response
    {
        return Response::json([
            'status' => 'success',
            'data' => [
                'mode' => 'session-bff',
                'authenticated' => Auth::check(),
                'login_url' => rtrim((string) Config::get('app.url'), '/') . '/api/auth/login',
                'logout_url' => rtrim((string) Config::get('app.url'), '/') . '/api/auth/logout',
                'mobile_login_url' => rtrim((string) Config::get('app.url'), '/') . '/api/auth/mobile/login',
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/auth/login',
        summary: 'Iniciar login no Authentik',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 302, description: 'Redirect para o Authentik'),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login(): Response
    {
        return $this->startOAuthLogin('web', (string) Config::get('authentik.redirect_uri'));
    }

    public function mobileLogin(Request $request): Response
    {
        $appRedirectUri = $this->allowedMobileRedirectUri((string) $request->input('redirect_uri', ''));

        if ($appRedirectUri === null) {
            return Response::json([
                'status' => 'error',
                'message' => 'Mobile redirect URI is not allowed.',
            ], 422);
        }

        Session::put('mobile_app_redirect_uri', $appRedirectUri);

        $clientState = (string) $request->input('state', '');
        if ($clientState !== '') {
            Session::put('mobile_client_state', $clientState);
        } else {
            Session::forget('mobile_client_state');
        }

        return $this->startOAuthLogin('mobile', (string) Config::get('authentik.mobile_callback_uri'));
    }

    private function startOAuthLogin(string $flow, string $redirectUri): Response
    {
        $authorizationUrl = (string) Config::get('authentik.authorization_url');
        $clientId = (string) Config::get('authentik.client_id');

        if ($authorizationUrl === '' || $clientId === '' || $redirectUri === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'Authentik OAuth is not configured.',
            ], 500);
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $codeVerifier = self::base64UrlEncode(random_bytes(64));
        $codeChallenge = self::base64UrlEncode(hash('sha256', $codeVerifier, true));

        Session::put('oauth_state', $state);
        Session::put('oauth_nonce', $nonce);
        Session::put('oauth_code_verifier', $codeVerifier);
        Session::put('oauth_flow', $flow);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', Config::get('authentik.scopes', ['openid', 'profile', 'email'])),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return Response::redirect("{$authorizationUrl}?{$query}");
    }

    #[OA\Get(
        path: '/api/auth/callback',
        summary: 'Callback OAuth do Authentik',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Sessao criada e redirect para o frontend'),
            new OA\Response(response: 400, description: 'Callback invalido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function callback(Request $request): Response
    {
        return $this->handleCallback($request, 'web', (string) Config::get('authentik.redirect_uri'));
    }

    public function mobileCallback(Request $request): Response
    {
        return $this->handleCallback($request, 'mobile', (string) Config::get('authentik.mobile_callback_uri'));
    }

    private function handleCallback(Request $request, string $expectedFlow, string $redirectUri): Response
    {
        if ($request->input('error') !== null) {
            return $this->callbackError($expectedFlow, [
                'status' => 'error',
                'message' => (string) $request->input('error_description', $request->input('error')),
            ], 400);
        }

        $state = (string) $request->input('state', '');
        $expectedState = (string) Session::get('oauth_state', '');

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            return $this->callbackError($expectedFlow, [
                'status' => 'error',
                'message' => 'Invalid OAuth state.',
            ], 400);
        }

        if ((string) Session::get('oauth_flow', 'web') !== $expectedFlow) {
            return $this->callbackError($expectedFlow, [
                'status' => 'error',
                'message' => 'Invalid OAuth flow.',
            ], 400);
        }

        try {
            $code = (string) $request->input('code', '');
            $codeVerifier = (string) Session::get('oauth_code_verifier', '');

            if ($code === '' || $codeVerifier === '') {
                throw new RuntimeException('Missing authorization code.');
            }

            $tokens = $this->oauthClient()->exchangeCode($code, $codeVerifier, $redirectUri);
            $idToken = $tokens['id_token'] ?? null;

            if (!is_string($idToken) || $idToken === '') {
                throw new RuntimeException('Authentik did not return an ID token.');
            }

            $claims = $this->tokenValidator()->validateIdToken($idToken, (string) Session::get('oauth_nonce'));
            $profile = $this->profileRepository()->findByAuthentikUser((string) ($claims->sub ?? ''));

            if ($profile === null) {
                throw new RuntimeException('Local profile not found for authenticated user.');
            }

            $sessionTokens = $this->sessionTokens($tokens);

            if ($expectedFlow === 'mobile') {
                return $this->finishMobileCallback($profile, $sessionTokens);
            }

            Session::regenerate();
            Session::put('profile', $profile);
            Session::put('authentik_tokens', $sessionTokens);
            $this->clearOAuthSession();
        } catch (Throwable $exception) {
            return $this->callbackError($expectedFlow, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return Response::redirect((string) Config::get('authentik.post_login_redirect_uri'));
    }

    public function mobileExchange(Request $request): Response
    {
        $code = trim((string) $request->input('code', ''));

        if ($code === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'Mobile authorization code is required.',
            ], 422);
        }

        $payload = $this->mobileAuthCodes()->consume($code);

        if (!is_array($payload)) {
            return Response::json([
                'status' => 'error',
                'message' => 'Mobile authorization code is invalid or expired.',
            ], 400);
        }

        Session::regenerate();
        Session::put('profile', $payload['profile']);
        Session::put('authentik_tokens', $payload['tokens']);

        return Response::json([
            'status' => 'success',
            'data' => $this->presentProfile($payload['profile']),
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Encerrar sessao local',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 302, description: 'Sessao encerrada e redirect para o frontend'),
        ]
    )]
    public function logout(): Response
    {
        Session::destroy();

        return Response::redirect((string) Config::get('authentik.post_logout_redirect_uri'));
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Perfil do usuario autenticado',
        security: [['cookieAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Perfil local', content: new OA\JsonContent(ref: '#/components/schemas/ProfileResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function me(Request $request): Response
    {
        try {
            $profile = Auth::profile();

            return Response::json([
                'status' => 'success',
                'data' => $this->presentProfile($profile),
            ]);
        } catch (Throwable $exception) {
            $this->logAuthFailure($request, $exception->getMessage());

            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 401);
        }
    }

    /** @param array<string, mixed> $profile */
    private function presentProfile(array $profile): array
    {
        if ($profile['tipo'] === 'instituicao') {
            return [
                'tipo' => 'instituicao',
                'id' => $profile['id'],
                'nome' => $profile['nome'],
                'email' => $profile['email'],
                'telefone' => $profile['telefone'],
                'categoria' => $profile['categoria'],
                'cidade' => $profile['cidade'],
                'uf' => $profile['uf'],
                'endereco' => $this->presentAddress($profile),
            ];
        }

        return [
            'tipo' => 'voluntario',
            'id' => $profile['id'],
            'nome' => $profile['nome'],
            'email' => $profile['email'],
            'telefone' => $profile['telefone'],
            'cidade' => $profile['cidade'],
            'uf' => $profile['uf'],
        ];
    }

    /** @param array<string, mixed> $profile */
    private function presentAddress(array $profile): array
    {
        return [
            'rua' => $profile['rua'] ?? '',
            'numero' => $profile['numero'] ?? '',
            'complemento' => $profile['complemento'] ?? '',
            'bairro' => $profile['bairro'] ?? '',
            'cidade' => $profile['cidade'] ?? '',
            'uf' => $profile['uf'] ?? '',
            'codigo_postal' => $profile['codigo_postal'] ?? '',
        ];
    }

    private function logAuthFailure(Request $request, string $message): void
    {
        try {
            (new LogRepository())->create('TOKEN_INVALIDO', $message, 'warning', $request);
        } catch (Throwable) {
        }
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $tokens */
    private function finishMobileCallback(array $profile, array $tokens): Response
    {
        $code = $this->mobileAuthCodes()->create(
            $profile,
            $tokens,
            (int) Config::get('authentik.mobile_code_ttl', 300)
        );
        $appRedirectUri = (string) Session::get('mobile_app_redirect_uri', '');
        $params = ['code' => $code];
        $clientState = (string) Session::get('mobile_client_state', '');

        if ($clientState !== '') {
            $params['state'] = $clientState;
        }

        $this->clearOAuthSession();
        Session::forget('mobile_app_redirect_uri');
        Session::forget('mobile_client_state');

        return Response::redirect($this->appendQuery($appRedirectUri, $params));
    }

    /** @param array<string, string> $body */
    private function callbackError(string $flow, array $body, int $status): Response
    {
        if ($flow !== 'mobile') {
            return Response::json($body, $status);
        }

        $appRedirectUri = (string) Session::get('mobile_app_redirect_uri', '');
        if ($appRedirectUri === '') {
            return Response::json($body, $status);
        }

        $this->clearOAuthSession();
        Session::forget('mobile_app_redirect_uri');
        Session::forget('mobile_client_state');

        return Response::redirect($this->appendQuery($appRedirectUri, [
            'error' => 'oauth_failed',
            'error_description' => $body['message'] ?? 'OAuth failed.',
        ]));
    }

    private function allowedMobileRedirectUri(string $requestedUri): ?string
    {
        $allowed = Config::get('authentik.mobile_app_redirect_uris', []);
        $allowed = is_array($allowed) ? array_values($allowed) : [];

        if ($allowed === []) {
            return null;
        }

        if ($requestedUri === '') {
            return (string) $allowed[0];
        }

        return in_array($requestedUri, $allowed, true) ? $requestedUri : null;
    }

    /** @param array<string, mixed> $tokens @return array<string, mixed> */
    private function sessionTokens(array $tokens): array
    {
        return [
            'access_token' => $tokens['access_token'] ?? null,
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => time() + (int) ($tokens['expires_in'] ?? 0),
        ];
    }

    private function clearOAuthSession(): void
    {
        Session::forget('oauth_state');
        Session::forget('oauth_nonce');
        Session::forget('oauth_code_verifier');
        Session::forget('oauth_flow');
    }

    /** @param array<string, string> $params */
    private function appendQuery(string $uri, array $params): string
    {
        $fragment = '';
        $fragmentPosition = strpos($uri, '#');

        if ($fragmentPosition !== false) {
            $fragment = substr($uri, $fragmentPosition);
            $uri = substr($uri, 0, $fragmentPosition);
        }

        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . $fragment;
    }

    private function oauthClient(): object
    {
        return $this->oauthClient ?? new AuthentikOAuthClient();
    }

    private function tokenValidator(): object
    {
        return $this->tokenValidator ?? new AuthentikTokenValidator();
    }

    private function profileRepository(): object
    {
        return $this->profileRepository ?? new ProfileRepository();
    }

    private function mobileAuthCodes(): object
    {
        return $this->mobileAuthCodes ?? new MobileAuthCodeRepository();
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
