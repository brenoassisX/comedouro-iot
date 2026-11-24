<?php
/**
 * Topo comum das telas de autenticação secundárias (esqueci/redefinir senha).
 * Abre <html>, <body> e <main class="card">. Fechar com partials/auth_foot.php.
 * Espera $titulo (opcional). Define $e() para escapar saídas.
 */
$e      = $e ?? static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$titulo = $titulo ?? 'Comedouro Pet';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($titulo) ?> | Comedouro Pet</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lilita+One&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poetsen+One&display=swap" rel="stylesheet">

    <style>
        :root {
            --card: #F4EADE; --laranja: #E27D60; --verde: #486B5D; --escuro: #1E1E24;
            --fundo: #FFFBF8; --suave: #8A8A93;
            --f-corpo: 'Plus Jakarta Sans', system-ui, sans-serif;
            --f-titulo: 'Poetsen One', 'Lilita One', system-ui, sans-serif;
            --f-botao: 'Lilita One', 'Poetsen One', system-ui, sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: var(--f-corpo); color: var(--escuro);
            background-color: var(--fundo);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='260' height='260' viewBox='0 0 260 260' fill='%23F7E3CB'%3E%3Cg transform='translate(55 60) rotate(-35)'%3E%3Crect x='-30' y='-6' width='60' height='12' rx='6'/%3E%3Ccircle cx='-30' cy='-8' r='9'/%3E%3Ccircle cx='-30' cy='8' r='9'/%3E%3Ccircle cx='30' cy='-8' r='9'/%3E%3Ccircle cx='30' cy='8' r='9'/%3E%3C/g%3E%3Cg transform='translate(195 70) rotate(15)'%3E%3Cellipse cx='0' cy='8' rx='15' ry='12'/%3E%3Cellipse cx='-18' cy='-9' rx='6' ry='8'/%3E%3Cellipse cx='-7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='18' cy='-9' rx='6' ry='8'/%3E%3C/g%3E%3Cg transform='translate(70 195) rotate(-18)'%3E%3Cellipse cx='0' cy='8' rx='15' ry='12'/%3E%3Cellipse cx='-18' cy='-9' rx='6' ry='8'/%3E%3Cellipse cx='-7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='18' cy='-9' rx='6' ry='8'/%3E%3C/g%3E%3Cg transform='translate(200 200) rotate(42)'%3E%3Crect x='-30' y='-6' width='60' height='12' rx='6'/%3E%3Ccircle cx='-30' cy='-8' r='9'/%3E%3Ccircle cx='-30' cy='8' r='9'/%3E%3Ccircle cx='30' cy='-8' r='9'/%3E%3Ccircle cx='30' cy='8' r='9'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 260px 260px;
            display: grid; place-items: center; padding: 24px 16px;
        }
        .card {
            width: 100%; max-width: 460px; background: var(--card);
            border-radius: 20px; padding: clamp(24px, 6vw, 44px);
            box-shadow: 0 18px 40px -22px rgba(30, 30, 36, .28);
        }
        h1 { margin: 0 0 8px; font: 400 clamp(1.5rem, 5vw, 1.9rem)/1.15 var(--f-titulo); }
        .sub { margin: 0 0 24px; font-size: .9rem; line-height: 1.5; }
        label { display: block; font-size: .75rem; font-weight: 700; letter-spacing: .02em; margin-bottom: 7px; }
        .grupo { margin-bottom: 16px; }
        .campo { position: relative; }
        .campo input {
            width: 100%; height: 46px; padding: 0 14px 0 42px;
            font: 500 .9rem var(--f-corpo); color: var(--escuro);
            background: #fff; border: 2px solid transparent; border-radius: 10px;
            transition: border-color .2s ease;
        }
        .campo input::placeholder { color: #A9A9B1; }
        .campo input:focus { outline: none; border-color: var(--escuro); }
        .icone { position: absolute; left: 14px; top: 50%; width: 18px; height: 18px; margin-top: -9px; color: var(--suave); pointer-events: none; }
        .btn {
            width: 100%; height: 50px; margin-top: 6px;
            font-family: var(--f-botao); font-size: 1.05rem; letter-spacing: .02em;
            color: #fff; border: 0; border-radius: 10px; cursor: pointer;
            transition: transform .15s ease, filter .2s ease;
        }
        .btn:hover { filter: brightness(.94); }
        .btn:active { transform: scale(.985); }
        .btn-laranja { background: var(--laranja); }
        .btn-verde { background: var(--verde); }
        .voltar { display: block; margin-top: 20px; text-align: center; font-size: .85rem; font-weight: 600; color: var(--escuro); }
        .alerta { font-size: .85rem; font-weight: 600; border-radius: 10px; padding: 11px 14px; margin-bottom: 18px; color: #8C2F1B; background: #FBE3DC; }
        .alerta.ok { color: #1F3D31; background: #DCEBE3; }
        .btn:focus-visible, .voltar:focus-visible { outline: 3px solid var(--escuro); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></symbol>
</svg>
<main class="card">
