<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Config;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Core\Session;
use Cheer\Repositories\LogRepository;
use Cheer\Repositories\ProfileRepository;
use Cheer\Services\AuthentikOAuthClient;
use OpenApi\Attributes as OA;
use RuntimeException;
use Throwable;

final class AuthController
{
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
        $authorizationUrl = (string) Config::get('authentik.authorization_url');
        $clientId = (string) Config::get('authentik.client_id');
        $redirectUri = (string) Config::get('authentik.redirect_uri');

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
        if ($request->input('error') !== null) {
            return Response::json([
                'status' => 'error',
                'message' => (string) $request->input('error_description', $request->input('error')),
            ], 400);
        }

        $state = (string) $request->input('state', '');
        $expectedState = (string) Session::get('oauth_state', '');

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            return Response::json([
                'status' => 'error',
                'message' => 'Invalid OAuth state.',
            ], 400);
        }

        try {
            $code = (string) $request->input('code', '');
            $codeVerifier = (string) Session::get('oauth_code_verifier', '');

            if ($code === '' || $codeVerifier === '') {
                throw new RuntimeException('Missing authorization code.');
            }

            $tokens = (new AuthentikOAuthClient())->exchangeCode($code, $codeVerifier);
            $idToken = $tokens['id_token'] ?? null;

            if (!is_string($idToken) || $idToken === '') {
                throw new RuntimeException('Authentik did not return an ID token.');
            }

            $claims = Auth::validateIdToken($idToken, (string) Session::get('oauth_nonce'));
            $profile = (new ProfileRepository())->findByAuthentikUser((string) ($claims->sub ?? ''));

            if ($profile === null) {
                throw new RuntimeException('Local profile not found for authenticated user.');
            }

            Session::regenerate();
            Session::put('profile', $profile);
            Session::put('authentik_tokens', [
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => time() + (int) ($tokens['expires_in'] ?? 0),
            ]);
            Session::forget('oauth_state');
            Session::forget('oauth_nonce');
            Session::forget('oauth_code_verifier');
        } catch (Throwable $exception) {
            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return Response::redirect((string) Config::get('authentik.post_login_redirect_uri'));
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

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
