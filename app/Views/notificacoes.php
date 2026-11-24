<?php
declare(strict_types=1);
/**
 * View: app/Views/notificacoes.php
 * Variáveis do NotificacaoController::index(): $notificacoes (array), $erro (string)
 */
$titulo    = 'Notificações';
$menuAtivo = '';   // não há item do menu para esta tela: ela abre pelo sininho
require __DIR__ . '/partials/app_head.php';

// tipo => [ícone, cor do círculo]
$visual = [
    'consumo'     => ['#i-alerta',      'vermelho'],
    'alimentacao' => ['#i-check',       'verde'],
    'relatorio'   => ['#i-doc',         'roxo'],
    'offline'     => ['#i-wifi-off',    'vermelho'],
    'nivel'       => ['#i-reservatorio', 'laranja'],
    'proxima'     => ['#i-relogio',     'terracota'],
];
?>
        <header class="cab">
            <svg class="cab-icone" aria-hidden="true"><use href="#i-sino"/></svg>
            <h1>Notificações</h1>
            <?php if (!empty($notificacoes)): ?>
                <div class="cab-acoes">
                    <form action="/notificacoes/limpar" method="POST" data-confirmar="Apagar todas as notificações?">
                        <button type="submit" class="btn btn-sec btn-peq">Limpar tudo</button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <?php if (empty($notificacoes) && empty($erro)): ?>
            <div class="vazio"><p>Você não tem notificações por enquanto.</p></div>
        <?php endif; ?>

        <div class="lista-notif">
            <?php foreach ($notificacoes as $n): ?>
                <?php
                    [$icone, $cor] = $visual[$n['tipo']] ?? ['#i-sino', 'terracota'];
                    $href = !empty($n['url']) ? (string) $n['url'] : '';
                    $nova = (int) $n['lida'] === 0;
                ?>
                <<?= $href !== '' ? 'a href="' . $e($href) . '"' : 'div' ?> class="notif<?= $nova ? ' nova' : '' ?>">
                    <span class="notif-icone <?= $e($cor) ?>"><svg aria-hidden="true"><use href="<?= $e($icone) ?>"/></svg></span>
                    <span class="notif-texto">
                        <strong><?= $e($n['titulo']) ?><?= $nova ? ' <span class="sr-only">(nova)</span>' : '' ?></strong>
                        <span><?= $e($n['mensagem']) ?></span>
                    </span>
                    <time datetime="<?= $e(date('c', strtotime((string) $n['criado_em']))) ?>"><?= $e(date('d/m/Y - H:i', strtotime((string) $n['criado_em']))) ?></time>
                </<?= $href !== '' ? 'a' : 'div' ?>>
            <?php endforeach; ?>
        </div>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
