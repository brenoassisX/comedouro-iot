<?php
/**
 * CASCA DO SISTEMA LOGADO: <head>, logo, menu lateral e abertura da área principal.
 * Todas as telas internas usam este topo e fecham com app_foot.php; por isso o menu
 * lateral continua visível ao navegar.
 *
 * Variáveis que a View define ANTES de incluir este arquivo:
 *   $titulo     (aba do navegador)
 *   $menuAtivo  ('inicio'|'pets'|'dispositivos'|'alimentacao'|'relatorios'|'config')
 *   $extraHead  (opcional) HTML extra para o <head> (ex.: recarregar a página sozinha)
 */
$e         = $e ?? static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$titulo    = $titulo ?? 'PetFeeder';
$menuAtivo = $menuAtivo ?? '';
$extraHead = $extraHead ?? '';

// Primeiro nome do usuário (usado na tela Início)
$primeiroNome = $primeiroNome ?? (explode(' ', trim((string) ($_SESSION['usuario_nome'] ?? '')))[0] ?: 'Usuário');

// Itens do menu: chave => [link, texto, ícone]
$menu = [
    'inicio'       => ['/painel',       'Início',        '#i-inicio'],
    'pets'         => ['/pets',         'Meus Pets',     '#i-pets'],
    'dispositivos' => ['/dispositivos', 'Dispositivos',  '#i-dispositivo'],
    'alimentacao'  => ['/alimentacao',  'Alimentação',   '#i-talher'],
    'relatorios'   => ['/relatorios',   'Relatórios',    '#i-doc'],
    'config'       => ['/configuracao', 'Configuração',  '#i-config'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($titulo) ?> | PetFeeder</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
    <?= $extraHead ?>
</head>
<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
        <symbol id="i-inicio" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 4l9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></symbol>
        <symbol id="i-pets" viewBox="0 0 24 24" fill="currentColor"><ellipse cx="12" cy="16" rx="5" ry="4"/><ellipse cx="5" cy="11" rx="2" ry="2.7"/><ellipse cx="9.5" cy="6.5" rx="2" ry="2.7"/><ellipse cx="14.5" cy="6.5" rx="2" ry="2.7"/><ellipse cx="19" cy="11" rx="2" ry="2.7"/></symbol>
        <symbol id="i-dispositivo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18h2"/></symbol>
        <symbol id="i-talher" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3v4.5a2.5 2.5 0 0 0 5 0V3M7 10v11M17 21V3c-2.5 1.5-3.5 4-3.5 7v3H17"/></symbol>
        <symbol id="i-doc" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></symbol>
        <symbol id="i-config" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></symbol>
        <symbol id="i-sair" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></symbol>
        <symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
        <symbol id="i-sino" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0"/></symbol>
        <symbol id="i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 0 1 2-2h2l1.5-2h7L17 6h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.5"/></symbol>
        <symbol id="i-lixeira" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4h6v3"/></symbol>
        <symbol id="i-lapis" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></symbol>
        <symbol id="i-relogio" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="i-seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol>
        <symbol id="i-mais" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="i-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.3"/></symbol>
        <symbol id="i-regua" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="8" width="20" height="8" rx="1.5"/><path d="M6 8v3M10 8v4M14 8v3M18 8v4"/></symbol>
        <symbol id="i-calendario" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></symbol>
        <symbol id="i-balanca" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10a4 4 0 0 1 8 0M12 10l2-2.5"/></symbol>
        <symbol id="i-chip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></symbol>
    </defs>
</svg>

<div class="casca">

    <!-- ============ Menu lateral (igual em todas as telas) ============ -->
    <aside class="sidebar">
        <a class="logo" href="/painel" aria-label="PetFeeder - Início">
            <img src="/assets/logo.svg" alt="">
            <span>PetFeeder</span>
        </a>

        <nav class="menu" aria-label="Menu principal">
            <?php foreach ($menu as $chave => [$link, $texto, $icone]): ?>
                <a class="item" href="<?= $e($link) ?>"<?= $chave === $menuAtivo ? ' aria-current="page"' : '' ?>>
                    <svg aria-hidden="true"><use href="<?= $e($icone) ?>"/></svg><span><?= $e($texto) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <a class="btn-sair" href="/logout"><svg aria-hidden="true"><use href="#i-sair"/></svg>Sair</a>
    </aside>

    <!-- ============ Área principal: o conteúdo de cada tela entra aqui ============ -->
    <div class="area">
    <main class="principal">
