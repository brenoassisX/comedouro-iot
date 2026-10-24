<?php
declare(strict_types=1);

/**
 * View: app/Views/login.php
 *
 * Variáveis opcionais que o Controller pode enviar:
 *   $aba   => 'entrar' | 'cadastrar'   (aba inicial)
 *   $erro  => string                   (mensagem de erro/aviso)
 *   $csrf  => string                   (token CSRF, se você usar)
 *   $old   => array                    (valores antigos: ['nome' => '', 'email' => ''])
 */

$e    = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$aba  = ($aba ?? 'entrar') === 'cadastrar' ? 'cadastrar' : 'entrar';
$old  = $old ?? [];
$sucesso = $sucesso ?? null;
$reenviar = $reenviar ?? false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso | Comedouro Pet</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Itim&family=Jockey+One&family=Lilita+One&family=Luckiest+Guy&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poetsen+One&display=swap" rel="stylesheet">

    <style>
        :root {
            --card:   #F4EADE;
            --laranja:#E27D60;
            --verde:  #486B5D;
            --escuro: #1E1E24;

            --fundo:      #FFFBF8;                 /* base clara atrás do card */
            --trilho:     #E6DCCB;                 /* trilho das abas (card escurecido) */
            --campo:      #FFFFFF;
            --texto-suave:#8A8A93;

            --f-corpo:   'Plus Jakarta Sans', system-ui, sans-serif;
            --f-titulo:  'Poetsen One', 'Lilita One', system-ui, sans-serif;
            --f-botao:   'Lilita One', 'Poetsen One', system-ui, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body { height: 100%; margin: 0; }

        body {
            font-family: var(--f-corpo);
            color: var(--escuro);
            background-color: var(--fundo);
            /* Patas e ossos desenhados em SVG, repetidos como padrão */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='260' height='260' viewBox='0 0 260 260' fill='%23F7E3CB'%3E%3Cg transform='translate(55 60) rotate(-35)'%3E%3Crect x='-30' y='-6' width='60' height='12' rx='6'/%3E%3Ccircle cx='-30' cy='-8' r='9'/%3E%3Ccircle cx='-30' cy='8' r='9'/%3E%3Ccircle cx='30' cy='-8' r='9'/%3E%3Ccircle cx='30' cy='8' r='9'/%3E%3C/g%3E%3Cg transform='translate(195 70) rotate(15)'%3E%3Cellipse cx='0' cy='8' rx='15' ry='12'/%3E%3Cellipse cx='-18' cy='-9' rx='6' ry='8'/%3E%3Cellipse cx='-7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='18' cy='-9' rx='6' ry='8'/%3E%3C/g%3E%3Cg transform='translate(70 195) rotate(-18)'%3E%3Cellipse cx='0' cy='8' rx='15' ry='12'/%3E%3Cellipse cx='-18' cy='-9' rx='6' ry='8'/%3E%3Cellipse cx='-7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='7' cy='-19' rx='6' ry='8'/%3E%3Cellipse cx='18' cy='-9' rx='6' ry='8'/%3E%3C/g%3E%3Cg transform='translate(200 200) rotate(42)'%3E%3Crect x='-30' y='-6' width='60' height='12' rx='6'/%3E%3Ccircle cx='-30' cy='-8' r='9'/%3E%3Ccircle cx='-30' cy='8' r='9'/%3E%3Ccircle cx='30' cy='-8' r='9'/%3E%3Ccircle cx='30' cy='8' r='9'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 260px 260px;
            display: grid;
            place-items: center;
            padding: 24px 16px;
            min-height: 100%;
        }

        .sr-only {
            position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0;
            overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
        }

        /* ---------- Card ---------- */
        .card {
            width: 100%;
            max-width: 500px;
            background: var(--card);
            border-radius: 20px;
            padding: clamp(24px, 6vw, 44px);
            box-shadow: 0 18px 40px -22px rgba(30, 30, 36, .28);
        }

        /* ---------- Abas ---------- */
        .abas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            padding: 4px;
            background: var(--trilho);
            border-radius: 14px;
            margin-bottom: 28px;
        }

        .aba {
            font-family: var(--f-titulo);
            font-size: 1rem;
            letter-spacing: .01em;
            color: var(--escuro);
            background: transparent;
            border: 0;
            border-radius: 10px;
            padding: 11px 8px;
            cursor: pointer;
            transition: background-color .25s ease, color .25s ease;
        }

        .aba[aria-selected="true"] { background: var(--escuro); color: #fff; }
        .aba:not([aria-selected="true"]):hover { background: rgba(30, 30, 36, .08); }

        /* ---------- Painéis (alternância suave) ---------- */
        .paineis { display: grid; }

        .painel {
            grid-area: 1 / 1;                 /* os dois ocupam a mesma célula: sem "pulo" de layout */
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateY(8px);
            transition: opacity .3s ease, transform .3s ease, visibility 0s linear .3s;
        }

        .painel.ativo {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: none;
            transition: opacity .3s ease, transform .3s ease, visibility 0s;
        }

        /* ---------- Formulário ---------- */
        .grupo { margin-bottom: 16px; }

        .rotulo-linha { display: flex; justify-content: space-between; align-items: baseline; }

        label, .rotulo {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .02em;
            margin-bottom: 7px;
        }

        .esqueceu {
            font-size: .75rem;
            font-weight: 600;
            color: var(--laranja);
            text-decoration: none;
        }
        .esqueceu:hover { text-decoration: underline; }

        .campo { position: relative; }

        .campo input {
            width: 100%;
            height: 46px;
            font: 500 .9rem var(--f-corpo);
            color: var(--escuro);
            background: var(--campo);
            border: 2px solid transparent;
            border-radius: 10px;
            padding: 0 44px 0 42px;
            transition: border-color .2s ease;
        }

        .campo input::placeholder { color: #A9A9B1; }
        .campo input:focus { outline: none; border-color: var(--escuro); }

        .icone {
            position: absolute; left: 14px; top: 50%;
            width: 18px; height: 18px; margin-top: -9px;
            color: var(--texto-suave);
            pointer-events: none;
        }

        .olho {
            position: absolute; right: 6px; top: 50%;
            width: 36px; height: 36px; margin-top: -18px;
            display: grid; place-items: center;
            background: transparent; border: 0; border-radius: 8px;
            color: var(--texto-suave); cursor: pointer;
        }
        .olho:hover { color: var(--escuro); }
        .olho svg { width: 18px; height: 18px; }

        .lembrar {
            display: flex; align-items: center; gap: 9px;
            font-size: .8rem; font-weight: 500;
            margin: 2px 0 20px; cursor: pointer;
        }
        .lembrar input { width: 17px; height: 17px; accent-color: var(--escuro); margin: 0; cursor: pointer; }

        /* ---------- Botões ---------- */
        .btn {
            width: 100%;
            height: 50px;
            font-family: var(--f-botao);
            font-size: 1.05rem;
            letter-spacing: .02em;
            color: #fff;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            transition: transform .15s ease, filter .2s ease;
        }
        .btn:hover  { filter: brightness(.94); }
        .btn:active { transform: scale(.985); }
        .btn svg { width: 18px; height: 18px; }

        .btn-entrar { background: var(--laranja); margin-top: 6px; }
        .btn-criar  { background: var(--verde);   margin-top: 6px; }

        .ou {
            display: flex; align-items: center; gap: 12px;
            margin: 22px 0 16px;
            font-size: .7rem; font-weight: 600; color: var(--texto-suave);
        }
        .ou::before, .ou::after { content: ""; flex: 1; height: 1px; background: rgba(30, 30, 36, .15); }

        .btn-google {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 60%; min-width: 150px; height: 44px; margin: 0 auto;
            font: 600 .85rem var(--f-corpo);
            color: var(--escuro); text-decoration: none;
            background: #fff; border-radius: 10px;
            transition: box-shadow .2s ease;
        }
        .btn-google:hover { box-shadow: 0 4px 14px -6px rgba(30, 30, 36, .35); }
        .btn-google svg { width: 18px; height: 18px; }

        .alerta {
            font-size: .82rem; font-weight: 600;
            color: #8C2F1B; background: #FBE3DC;
            border-radius: 10px; padding: 11px 14px; margin-bottom: 18px;
        }

        .alerta.ok { color: #1F3D31; background: #DCEBE3; }
        .reenviar { margin: -8px 0 18px; font-size: .82rem; font-weight: 600; }
        .reenviar a { color: var(--laranja); }

        /* ---------- Foco visível ---------- */
        .aba:focus-visible, .btn:focus-visible, .btn-google:focus-visible,
        .olho:focus-visible, .esqueceu:focus-visible, .lembrar input:focus-visible {
            outline: 3px solid var(--escuro); outline-offset: 2px;
        }

        @media (max-width: 420px) {
            .btn-google { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; }
        }
    </style>
</head>
<body>

<!-- Ícones reutilizáveis -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19c-6.4 0-10-7-10-7a17 17 0 0 1 4.1-4.9M9.9 5.2A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-2.2 3.2M1 1l22 22M9.9 9.9a3 3 0 0 0 4.2 4.2"/></symbol>
    <symbol id="i-seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
    <symbol id="i-add-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/></symbol>
</svg>

<main class="card">
    <h1 class="sr-only">Acesso ao Comedouro Pet</h1>

    <!-- Abas -->
    <div class="abas" role="tablist" aria-label="Escolha entre entrar ou criar conta">
        <button type="button" class="aba" role="tab" id="tab-entrar"
                aria-controls="painel-entrar" aria-selected="<?= $aba === 'entrar' ? 'true' : 'false' ?>">Entrar</button>
        <button type="button" class="aba" role="tab" id="tab-cadastrar"
                aria-controls="painel-cadastrar" aria-selected="<?= $aba === 'cadastrar' ? 'true' : 'false' ?>">Criar Conta</button>
    </div>

    <?php if (!empty($sucesso)): ?>
        <div class="alerta ok" role="status"><?= $e($sucesso) ?></div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
        <div class="alerta" role="alert"><?= $e($erro) ?></div>
    <?php endif; ?>

    <?php if (!empty($reenviar)): ?>
        <p class="reenviar"><a href="/reenviar-confirmacao">Reenviar e-mail de confirmação</a></p>
    <?php endif; ?>

    <div class="paineis">

        <!-- ================= LOGIN ================= -->
        <section class="painel <?= $aba === 'entrar' ? 'ativo' : '' ?>" id="painel-entrar"
                 role="tabpanel" aria-labelledby="tab-entrar">
            <form action="/login" method="POST" novalidate>
                <?php if (!empty($csrf)): ?><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><?php endif; ?>

                <div class="grupo">
                    <label for="login-email">E-mail</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-mail"/></svg>
                        <input type="email" id="login-email" name="email" placeholder="seu@email.com"
                               autocomplete="email" required value="<?= $e($old['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="grupo">
                    <div class="rotulo-linha">
                        <label for="login-senha">Senha</label>
                        <a class="esqueceu" href="/esqueci-senha">Esqueceu a senha?</a>
                    </div>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-lock"/></svg>
                        <input type="password" id="login-senha" name="senha" placeholder="••••••••"
                               autocomplete="current-password" required>
                        <button type="button" class="olho" data-alvo="login-senha" aria-label="Mostrar senha">
                            <svg aria-hidden="true"><use href="#i-eye"/></svg>
                        </button>
                    </div>
                </div>

                <label class="lembrar">
                    <input type="checkbox" name="lembrar" value="1">
                    Lembrar deste dispositivo
                </label>

                <button type="submit" class="btn btn-entrar">
                    Entrar no Sistema <svg aria-hidden="true"><use href="#i-seta"/></svg>
                </button>
            </form>
        </section>

        <!-- ================= CADASTRO ================= -->
        <section class="painel <?= $aba === 'cadastrar' ? 'ativo' : '' ?>" id="painel-cadastrar"
                 role="tabpanel" aria-labelledby="tab-cadastrar">
            <form action="/cadastrar" method="POST" novalidate>
                <?php if (!empty($csrf)): ?><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><?php endif; ?>

                <div class="grupo">
                    <label for="cad-nome">Nome completo</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-user"/></svg>
                        <input type="text" id="cad-nome" name="nome" placeholder="Seu nome"
                               autocomplete="name" required value="<?= $e($old['nome'] ?? '') ?>">
                    </div>
                </div>

                <div class="grupo">
                    <label for="cad-email">E-mail</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-mail"/></svg>
                        <input type="email" id="cad-email" name="email" placeholder="seu@email.com"
                               autocomplete="email" required value="<?= $e($old['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="grupo">
                    <label for="cad-senha">Criar senha</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-lock"/></svg>
                        <input type="password" id="cad-senha" name="senha" placeholder="Pelo menos 8 caracteres"
                               autocomplete="new-password" minlength="8" required>
                        <button type="button" class="olho" data-alvo="cad-senha" aria-label="Mostrar senha">
                            <svg aria-hidden="true"><use href="#i-eye"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-criar">
                    Criar minha conta <svg aria-hidden="true"><use href="#i-add-user"/></svg>
                </button>
            </form>
        </section>
    </div>

    <!-- Acesso alternativo (comum às duas abas) -->
    <div class="ou">ou acesse com</div>
    <a class="btn-google" href="/auth/google">
        <svg viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Google
    </a>
</main>

<script>
(function () {
    'use strict';

    var abas    = { entrar: document.getElementById('tab-entrar'),    cadastrar: document.getElementById('tab-cadastrar') };
    var paineis = { entrar: document.getElementById('painel-entrar'), cadastrar: document.getElementById('painel-cadastrar') };

    function mostrar(nome, focar) {
        Object.keys(abas).forEach(function (k) {
            var ativo = k === nome;
            abas[k].setAttribute('aria-selected', ativo ? 'true' : 'false');
            abas[k].tabIndex = ativo ? 0 : -1;
            paineis[k].classList.toggle('ativo', ativo);
        });
        if (focar) {
            var primeiro = paineis[nome].querySelector('input');
            if (primeiro) setTimeout(function () { primeiro.focus({ preventScroll: true }); }, 320);
        }
    }

    Object.keys(abas).forEach(function (k) {
        abas[k].addEventListener('click', function () { mostrar(k, true); });
        abas[k].addEventListener('keydown', function (ev) {      // setas esquerda/direita entre abas
            if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') {
                var outro = k === 'entrar' ? 'cadastrar' : 'entrar';
                mostrar(outro, false);
                abas[outro].focus();
            }
        });
    });

    // Aba inicial: #cadastrar na URL tem prioridade sobre o valor vindo do PHP
    var inicial = location.hash === '#cadastrar' ? 'cadastrar'
                : location.hash === '#entrar'    ? 'entrar'
                : '<?= $aba ?>';
    mostrar(inicial, false);

    // Mostrar/ocultar senha
    document.querySelectorAll('.olho').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input   = document.getElementById(btn.dataset.alvo);
            var visivel = input.type === 'text';
            input.type  = visivel ? 'password' : 'text';
            btn.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
            btn.querySelector('use').setAttribute('href', visivel ? '#i-eye' : '#i-eye-off');
        });
    });
})();
</script>
</body>
</html>
