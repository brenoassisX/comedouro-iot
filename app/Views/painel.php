<?php
declare(strict_types=1);
/**
 * View: app/Views/painel.php  (Início)
 * Variáveis do PainelController: $nomeUsuario, $primeiroNome
 */
$titulo    = 'Início';
$menuAtivo = 'inicio';
require __DIR__ . '/partials/app_head.php';
?>
        <div class="usuario">
            <span class="avatar"><svg aria-hidden="true"><use href="#i-user"/></svg></span>
            <?= $e($primeiroNome) ?>
        </div>

        <header class="saudacao">
            <h1>Olá, <?= $e($primeiroNome) ?>!</h1>
            <p>Seja bem-vindo ao seu comedouro inteligente.</p>
        </header>

        <!-- Fileira grande: dados do pet / nível de ração + segundo card (a definir) -->
        <div class="linha-grande">
            <section class="card card-grande" aria-label="Dados do pet e nível de ração">
                <div class="wire wire-bloco"></div>
                <div class="wire wire-barra"></div>
            </section>
            <section class="card card-grande" aria-label="Resumo (a definir)"></section>
        </div>

        <!-- Fileira pequena: atalhos -->
        <div class="linha-pequena">
            <a class="card card-pequeno" href="/alimentacao">
                <svg aria-hidden="true"><use href="#i-relogio"/></svg>
                Agendar Refeição
            </a>
            <a class="card card-pequeno" href="/dispositivos">
                <svg aria-hidden="true"><use href="#i-dispositivo"/></svg>
                Dispositivos
            </a>
            <div class="card card-pequeno card-vazio" aria-hidden="true"></div>
        </div>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
