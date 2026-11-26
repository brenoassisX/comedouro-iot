<?php
declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\PainelController;
use App\Controllers\PetController;
use App\Controllers\DispositivoController;
use App\Controllers\AlimentacaoController;
use App\Controllers\RelatorioController;
use App\Controllers\ConfiguracaoController;
use App\Controllers\NotificacaoController;
use App\Controllers\UsuarioController;

/**
 * Front Controller + tabela de rotas.
 * Todas as requisicoes passam por aqui (ver public/.htaccess).
 */

define('BASE_PATH', dirname(__DIR__));

// Servidor embutido (php -S ... public/index.php): entrega arquivos estaticos (CSS e fotos enviadas)
// sem passar pelas rotas. No Apache/Nginx isto nao e necessario (o .htaccess ja faz).
if (PHP_SAPI === 'cli-server') {
    $caminhoUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $estatico = str_starts_with($caminhoUrl, '/uploads/') || str_starts_with($caminhoUrl, '/assets/');
    if ($estatico && !str_contains($caminhoUrl, '..') && is_file(__DIR__ . $caminhoUrl)) {
        return false;
    }
}

// Autoload simples: App\Controllers\ApiController -> app/Controllers/ApiController.php
spl_autoload_register(static function (string $classe): void {
    $prefixo = 'App\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) {
        return;
    }
    $arquivo = BASE_PATH . '/app/' . str_replace('\\', '/', substr($classe, strlen($prefixo))) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

$config = require BASE_PATH . '/app/Config/config.php';
date_default_timezone_set($config['timezone']);

// ---- Resolve o caminho (suporta app em subpasta, ex.: /comedouro/public) ----
$metodo  = $_SERVER['REQUEST_METHOD'];
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$base    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base !== '' && str_starts_with($caminho, $base)) {
    $caminho = substr($caminho, strlen($base)) ?: '/';
}
$caminho = '/' . trim($caminho, '/');

// ---- Sessao (NOVO) ----
// Iniciada antes de qualquer saida e de qualquer controller.
// A API do ESP32 (/api/*) nao usa cookies (o ESP32 se identifica pelo MAC), por isso fica de fora:
// evita criar um ficheiro de sessao a cada consulta do dispositivo.
if (!str_starts_with($caminho, '/api/')) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,                                                    // expira ao fechar o navegador
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,                                                 // JS nao le o cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Tabela de rotas: [METODO][caminho] => [Controller, metodo] ----
$rotas = [
    'GET' => [
        '/'             => [UsuarioController::class, 'mostrar'],   // tela de login
        '/login'        => [UsuarioController::class, 'mostrar'],
        '/painel'       => [PainelController::class, 'index'],      // protegida por sessao
        '/logout'       => [UsuarioController::class, 'logout'],
        '/pets'         => [PetController::class, 'index'],         // Meus Pets (lista)
        '/pets/novo'    => [PetController::class, 'novo'],          // formulario de cadastro
        '/pets/perfil'  => [PetController::class, 'perfil'],        // perfil alimentar (?id=)
        '/pets/editar'  => [PetController::class, 'editar'],        // formulario de edicao (?id=)
        // Dispositivos (comedouros)
        '/dispositivos'         => [DispositivoController::class, 'index'],
        '/dispositivos/novo'    => [DispositivoController::class, 'novo'],
        '/dispositivos/editar'  => [DispositivoController::class, 'editar'],
        // Alimentacao (horarios e "liberar agora")
        '/alimentacao'          => [AlimentacaoController::class, 'index'],
        '/alimentacao/novo'     => [AlimentacaoController::class, 'novo'],
        '/alimentacao/editar'   => [AlimentacaoController::class, 'editar'],
        // Relatorios
        '/relatorios'           => [RelatorioController::class, 'index'],
        '/relatorios/pdf'       => [RelatorioController::class, 'pdf'],
        // Notificacoes (sininho do Inicio)
        '/notificacoes'         => [NotificacaoController::class, 'index'],
        // Configuracao
        '/configuracao'         => [ConfiguracaoController::class, 'index'],
        '/configuracao/perfil'  => [ConfiguracaoController::class, 'perfil'],
    ],
    'POST' => [
        '/login'        => [UsuarioController::class, 'login'],
        '/cadastrar'    => [UsuarioController::class, 'cadastrar'],
        '/pets'         => [PetController::class, 'salvar'],        // grava o pet (com foto)
        '/pets/atualizar' => [PetController::class, 'atualizar'],   // grava a edicao
        '/pets/excluir'   => [PetController::class, 'excluir'],     // apaga o pet
        '/dispositivos'           => [DispositivoController::class, 'salvar'],
        '/dispositivos/atualizar' => [DispositivoController::class, 'atualizar'],
        '/dispositivos/excluir'   => [DispositivoController::class, 'excluir'],
        '/alimentacao'            => [AlimentacaoController::class, 'salvar'],
        '/alimentacao/atualizar'  => [AlimentacaoController::class, 'atualizar'],
        '/alimentacao/excluir'    => [AlimentacaoController::class, 'excluir'],
        '/alimentacao/liberar'    => [AlimentacaoController::class, 'liberar'],
        '/alimentacao/porcoes'    => [AlimentacaoController::class, 'salvarPorcoes'],
        '/configuracao/perfil'    => [ConfiguracaoController::class, 'salvarPerfil'],
        '/configuracao/preferencias' => [ConfiguracaoController::class, 'salvarPreferencias'],
        '/notificacoes/limpar'    => [NotificacaoController::class, 'limpar'],
        '/api/sincronizar' => [ApiController::class, 'sincronizar'], // ESP32 (JSON)
    ],
];

// ---- Despacho ----
header_remove('X-Powered-By');

if (isset($rotas[$metodo][$caminho])) {
    [$controller, $acao] = $rotas[$metodo][$caminho];
    (new $controller())->$acao();
    exit;
}

// Caminho existe com outro metodo? -> 405
foreach ($rotas as $m => $lista) {
    if (isset($lista[$caminho])) {
        http_response_code(405);
        header('Allow: ' . $m);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'erro' => 'Metodo nao permitido.']);
        exit;
    }
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['sucesso' => false, 'erro' => 'Rota nao encontrada.']);
