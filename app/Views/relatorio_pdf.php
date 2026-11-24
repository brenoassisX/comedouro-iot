<?php
declare(strict_types=1);
/**
 * View: app/Views/relatorio_pdf.php  (versão para impressão do relatório)
 * Abre a janela de impressão do navegador: escolha "Salvar como PDF" como destino.
 * Mesmas variáveis da tela de relatórios.
 */
$e     = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtPeso = static fn (float $g): string => $g >= 1000 ? rtrim(rtrim(number_format($g / 1000, 2, ',', ''), '0'), ',') . ' kg' : (int) round($g) . ' g';
$nomes = ['semanal' => 'Semanal', 'mensal' => 'Mensal', 'personalizado' => 'Personalizado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de alimentação<?= $petAtual ? ' - ' . $e($petAtual['nome']) : '' ?> | PetFeeder</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #1E1E24; }
        header { display: flex; align-items: center; gap: 14px; padding-bottom: 14px; border-bottom: 2px solid #C4694F; }
        header img { width: 56px; }
        h1 { margin: 0; font-size: 1.6rem; } header p { margin: 2px 0 0; color: #55555C; font-size: .9rem; }
        .resumo { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 22px 0; }
        .resumo div { padding: 14px; text-align: center; background: #F4EADE; border-radius: 12px; }
        .resumo small { display: block; margin-bottom: 6px; font-size: .78rem; font-weight: 700; }
        .resumo strong { font-size: 1.4rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #E4D6C4; }
        th { background: #F4EADE; }
        td.n, th.n { text-align: right; }
        .barra { display: inline-block; height: 10px; background: #C4694F; border-radius: 5px; vertical-align: middle; }
        .rodape { margin-top: 24px; font-size: .75rem; color: #7A7A82; }
        .acoes { margin-bottom: 18px; }
        .acoes button { padding: 10px 18px; font: 700 .9rem inherit; color: #fff; background: #C4694F; border: 0; border-radius: 8px; cursor: pointer; }
        @media print { .acoes { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="acoes"><button type="button" onclick="window.print()">Imprimir / Salvar como PDF</button></div>

    <header>
        <img src="/assets/logo.svg" alt="">
        <div>
            <h1>Relatório de alimentação</h1>
            <p>
                <?= $petAtual ? 'Pet: <strong>' . $e($petAtual['nome']) . '</strong> · ' : '' ?>
                <?= $e($nomes[$periodo] ?? '') ?> · <?= $e($rotuloPeriodo) ?>
            </p>
        </div>
    </header>

    <?php if (!empty($erro)): ?>
        <p><strong><?= $e($erro) ?></strong></p>
    <?php elseif ($petAtual === null): ?>
        <p>Nenhum pet cadastrado.</p>
    <?php else: ?>
        <section class="resumo">
            <div><small>Total de refeições</small><strong><?= (int) $total ?></strong></div>
            <div><small>Ração liberada</small><strong><?= $e($fmtPeso($liberado)) ?></strong></div>
            <div><small>Ração consumida</small><strong><?= $temConsumo ? $e($fmtPeso($consumido)) : '—' ?></strong></div>
            <div><small>Média liberada por refeição</small><strong><?= (int) $media ?> g</strong></div>
        </section>

        <table>
            <thead><tr><th>Data</th><th class="n">Refeições</th><th class="n">Liberada (g)</th><th class="n">Consumida (g)</th><th>Frequência</th></tr></thead>
            <tbody>
                <?php foreach ($dias as $d): ?>
                    <tr>
                        <td><?= $e($d['data']) ?></td>
                        <td class="n"><?= (int) $d['refeicoes'] ?></td>
                        <td class="n"><?= (int) round($d['liberado']) ?></td>
                        <td class="n"><?= $d['medidas'] > 0 ? (int) round($d['consumido']) : '—' ?></td>
                        <td><span class="barra" style="width: <?= round($d['refeicoes'] / $topo * 160) ?>px"></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="rodape">Gerado em <?= date('d/m/Y H:i') ?> · PetFeeder</p>

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
