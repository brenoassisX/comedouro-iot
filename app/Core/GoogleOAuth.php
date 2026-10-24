<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Fluxo "Authorization Code" do Google, sem bibliotecas (usa cURL).
 * 1) urlAutorizacao() -> usuário vai ao Google
 * 2) obterPerfil()    -> troca o code por access_token e lê e-mail/nome (userinfo)
 */
final class GoogleOAuth
{
    private const URL_AUTH     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const URL_TOKEN    = 'https://oauth2.googleapis.com/token';
    private const URL_USERINFO = 'https://openidconnect.googleapis.com/v1/userinfo';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $cfg = require dirname(__DIR__) . '/Config/config.php';

        $this->clientId     = (string) ($cfg['google']['client_id'] ?? '');
        $this->clientSecret = (string) ($cfg['google']['client_secret'] ?? '');
        // Deve ser IDÊNTICO ao "URI de redirecionamento autorizado" no Google Cloud
        $this->redirectUri  = rtrim((string) $cfg['app_url'], '/') . '/auth/google/callback';
    }

    public function configurado(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function urlAutorizacao(string $state): string
    {
        return self::URL_AUTH . '?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
    }

    /** @return array{email:string, email_verificado:bool, nome:string} */
    public function obterPerfil(string $code): array
    {
        $token = $this->requisicao('POST', self::URL_TOKEN, [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($token['access_token'])) {
            throw new RuntimeException('Google não devolveu access_token.');
        }

        $perfil = $this->requisicao('GET', self::URL_USERINFO, [], (string) $token['access_token']);

        if (empty($perfil['email'])) {
            throw new RuntimeException('Google não devolveu o e-mail.');
        }

        return [
            'email'            => (string) $perfil['email'],
            'email_verificado' => filter_var($perfil['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'nome'             => (string) ($perfil['name'] ?? ''),
        ];
    }

    private function requisicao(string $metodo, string $url, array $corpo = [], ?string $bearer = null): array
    {
        $headers = ['Accept: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($corpo));
        }

        $resposta = curl_exec($ch);
        $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);

        if ($resposta === false) {
            throw new RuntimeException('Falha de rede ao falar com o Google: ' . $erroCurl);
        }

        $dados = json_decode($resposta, true);
        if ($http >= 400 || !is_array($dados)) {
            throw new RuntimeException("Resposta inválida do Google (HTTP {$http}).");
        }

        return $dados;
    }
}
