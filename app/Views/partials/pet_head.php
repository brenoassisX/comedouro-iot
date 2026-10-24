<?php
/**
 * Topo comum das telas de pets (lista, cadastro, perfil).
 * Variáveis: $titulo (aba do navegador), $tituloPagina (h1), $voltar (link da seta),
 *            $botaoHref / $botaoTexto (opcionais: botão à direita do título).
 * Fechar com partials/pet_foot.php.
 */
$e            = $e ?? static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$titulo       = $titulo ?? 'Pets';
$tituloPagina = $tituloPagina ?? $titulo;
$voltar       = $voltar ?? '/painel';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($titulo) ?> | Comedouro Pet</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lilita+One&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poetsen+One&display=swap" rel="stylesheet">

    <style>
        :root {
            --card: #F4EADE; --interno: #E6DCCB; --laranja: #E27D60; --escuro: #1E1E24;
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
            padding: clamp(14px, 4vw, 64px) clamp(12px, 3vw, 40px);
        }

        .cartao { max-width: 960px; margin: 0 auto; background: var(--card); border-radius: 22px; padding: clamp(20px, 5vw, 56px); }

        /* Cabeçalho: seta + título (+ botão opcional) */
        .cab { display: flex; align-items: center; gap: 14px; margin-bottom: clamp(20px, 3vw, 34px); }
        .cab h1 { margin: 0; font: 400 clamp(1.5rem, 4vw, 2.2rem)/1.1 var(--f-titulo); }
        .seta { display: grid; place-items: center; width: 34px; height: 34px; color: var(--escuro); border-radius: 8px; }
        .seta:hover { background: rgba(30, 30, 36, .08); }
        .seta svg { width: 24px; height: 24px; }
        .cab .btn-topo { margin-left: auto; }

        /* Botões */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-width: 140px; height: 46px; padding: 0 22px;
            font-family: var(--f-botao); font-size: 1.05rem; letter-spacing: .02em;
            color: #fff; text-decoration: none; background: var(--laranja);
            border: 0; border-radius: 10px; cursor: pointer;
            transition: filter .2s ease, transform .15s ease;
        }
        .btn:hover { filter: brightness(.94); }
        .btn:active { transform: scale(.985); }
        .btn svg { width: 18px; height: 18px; }
        .btn:focus-visible, .seta:focus-visible, .card-pet:focus-visible, .btn-foto:focus-within { outline: 3px solid var(--escuro); outline-offset: 2px; }

        .alerta { font-size: .88rem; font-weight: 600; color: #8C2F1B; background: #FBE3DC; border-radius: 10px; padding: 11px 14px; margin-bottom: 20px; }

        /* Foto (placeholder quando não há imagem) */
        .foto-box {
            width: 112px; height: 112px; flex: none; overflow: hidden;
            display: grid; place-items: center;
            background: var(--interno); color: #CBBDA8;
            border: 1px solid rgba(30, 30, 36, .3); border-radius: 14px;
        }
        .foto-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .foto-box svg { width: 46px; height: 46px; }

        /* ---------- Formulário de cadastro ---------- */
        .cad { display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: clamp(18px, 3vw, 34px); align-items: start; }
        .foto-col { display: flex; flex-direction: column; gap: 12px; }
        .btn-foto {
            display: grid; place-items: center; height: 40px; cursor: pointer;
            font-size: .8rem; font-weight: 700; color: #B5573C;
            background: #F6DCCB; border: 1.5px solid var(--laranja); border-radius: 10px;
        }
        .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); border: 0; padding: 0; }
        .foto-msg { margin: 0; font-size: .72rem; font-weight: 600; color: #8C2F1B; min-height: 1em; }

        .campos { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 12px 14px; }
        .c3 { grid-column: span 3; } .c4 { grid-column: span 4; } .c5 { grid-column: span 5; } .c12 { grid-column: span 12; }
        label { display: block; margin-bottom: 6px; font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }

        .campo { position: relative; }
        .campo input, .campo select, .campo textarea {
            width: 100%; height: 44px; padding: 0 12px 0 40px;
            font: 500 .88rem var(--f-corpo); color: var(--escuro);
            background: #fff; border: 2px solid transparent; border-radius: 10px;
            transition: border-color .2s ease;
        }
        .campo textarea { height: 76px; padding-top: 12px; resize: vertical; }
        .campo select {
            appearance: none; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8' fill='none' stroke='%231E1E24' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m1 1 5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center;
        }
        .campo input::placeholder, .campo textarea::placeholder { color: #A9A9B1; }
        .campo input:focus, .campo select:focus, .campo textarea:focus { outline: none; border-color: var(--escuro); }
        .campo .icone { position: absolute; left: 13px; top: 13px; width: 18px; height: 18px; color: var(--suave); pointer-events: none; }
        .campo .unidade { position: absolute; right: 6px; top: 6px; width: 84px; height: 32px; padding: 0 22px 0 10px; background-color: var(--card); background-position: right 8px center; border-radius: 8px; }
        .campo.com-unidade input { padding-right: 96px; }

        .info h2 { margin: clamp(24px, 4vw, 40px) 0 6px; font: 400 clamp(1.15rem, 2.6vw, 1.5rem) var(--f-titulo); }
        .info p { margin: 0 0 22px; font-size: .82rem; }

        @media (max-width: 680px) {
            .cad { grid-template-columns: 1fr; }
            .foto-col { flex-direction: row; align-items: center; }
            .btn-foto { flex: 1; }
            .c3, .c4, .c5 { grid-column: span 12; }
        }

        /* ---------- Lista (Meus Pets) ---------- */
        .grade-pets { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
        .card-pet {
            display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 18px 14px;
            text-align: center; color: var(--escuro); text-decoration: none;
            background: #fff; border-radius: 16px; transition: box-shadow .2s ease, transform .15s ease;
        }
        .card-pet:hover { box-shadow: 0 10px 22px -14px rgba(30, 30, 36, .5); transform: translateY(-2px); }
        .card-pet .foto-box { margin-bottom: 10px; }
        .card-pet strong { font: 400 1.2rem var(--f-titulo); }
        .card-pet span { font-size: .78rem; color: #5B5B63; }
        .vazio { padding: 36px 16px; text-align: center; background: var(--interno); border-radius: 16px; }
        .vazio p { margin: 0 0 18px; font-weight: 600; }

        /* ---------- Perfil alimentar ---------- */
        .painel-pet { display: flex; align-items: center; gap: clamp(14px, 3vw, 24px); padding: 14px; background: var(--interno); border: 1px solid var(--escuro); border-radius: 18px; }
        .painel-pet .foto-box { width: clamp(88px, 14vw, 128px); height: clamp(88px, 14vw, 128px); }
        .painel-pet h2 { margin: 0; font: 400 clamp(1.9rem, 5vw, 2.9rem)/1.05 var(--f-titulo); }
        .raca { margin: 2px 0 8px; font-size: clamp(.95rem, 2vw, 1.15rem); color: #6B6B73; }
        .meta { display: flex; flex-wrap: wrap; gap: 6px 0; margin: 0; padding: 0; list-style: none; font-size: .78rem; color: #4A4A52; }
        .meta li { display: flex; align-items: center; gap: 5px; padding: 0 10px; border-left: 1px solid rgba(30, 30, 36, .3); }
        .meta li:first-child { padding-left: 0; border-left: 0; }
        .meta svg { width: 14px; height: 14px; }
        .obs { margin: 14px 4px 0; font-size: .85rem; }

        .nutri h2 { margin: clamp(20px, 3vw, 30px) 0 12px; font: 400 clamp(1.3rem, 3vw, 1.7rem) var(--f-titulo); }
        .nutri-grade { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .nutri-card { padding: 16px 18px; background: var(--interno); border: 1px solid var(--escuro); border-radius: 16px; }
        .nutri-card small.rotulo { display: block; font-size: .85rem; font-weight: 500; margin-bottom: 4px; }
        .nutri-card .valor { font-size: clamp(1.5rem, 3.4vw, 2.1rem); font-weight: 800; letter-spacing: -.01em; }
        .nutri-card .obs-valor { margin-left: 6px; font-size: .68rem; font-weight: 500; color: #6B6B73; }
        .nota { margin: 16px 2px 0; font-size: .74rem; color: #5B5B63; line-height: 1.5; }

        @media (max-width: 760px) { .nutri-grade { grid-template-columns: 1fr; } }
        @media (max-width: 520px) { .painel-pet { align-items: flex-start; } }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
        <symbol id="i-seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol>
        <symbol id="i-mais" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="i-pata" viewBox="0 0 24 24" fill="currentColor"><ellipse cx="12" cy="16" rx="5" ry="4"/><ellipse cx="5" cy="11" rx="2" ry="2.7"/><ellipse cx="9.5" cy="6.5" rx="2" ry="2.7"/><ellipse cx="14.5" cy="6.5" rx="2" ry="2.7"/><ellipse cx="19" cy="11" rx="2" ry="2.7"/></symbol>
        <symbol id="i-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.3"/></symbol>
        <symbol id="i-regua" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="8" width="20" height="8" rx="1.5"/><path d="M6 8v3M10 8v4M14 8v3M18 8v4"/></symbol>
        <symbol id="i-calendario" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></symbol>
        <symbol id="i-balanca" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10a4 4 0 0 1 8 0M12 10l2-2.5"/></symbol>
        <symbol id="i-lapis" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></symbol>
    </defs>
</svg>

<main class="cartao">
    <header class="cab">
        <a class="seta" href="<?= $e($voltar) ?>" aria-label="Voltar"><svg aria-hidden="true"><use href="#i-seta"/></svg></a>
        <h1><?= $e($tituloPagina) ?></h1>
        <?php if (!empty($botaoHref)): ?>
            <a class="btn btn-topo" href="<?= $e($botaoHref) ?>"><svg aria-hidden="true"><use href="#i-mais"/></svg><?= $e($botaoTexto ?? '') ?></a>
        <?php endif; ?>
    </header>
