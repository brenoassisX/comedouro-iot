<?php
declare(strict_types=1);
/**
 * View: app/Views/dispositivo_form.php  (cadastrar e editar comedouro)
 * Variáveis do DispositivoController: $modo ('novo'|'editar'), $erro, $old, $id (int|null), $pets (array)
 */
$titulo    = $modo === 'editar' ? 'Configurações do comedouro' : 'Novo comedouro';
$menuAtivo = 'dispositivos';
require __DIR__ . '/partials/app_head.php';

$old     = $old ?? [];
$v       = static fn (string $k): string => $e($old[$k] ?? '');
$voltar  = $modo === 'editar' ? '/dispositivos?id=' . (int) $id : '/dispositivos';
?>
        <header class="cab">
            <a class="seta" href="<?= $e($voltar) ?>" aria-label="Voltar"><svg aria-hidden="true"><use href="#i-seta"/></svg></a>
            <h1><?= $e($titulo) ?></h1>
            <?php if ($modo === 'editar'): ?>
                <div class="cab-acoes">
                    <!-- Excluir é POST; o JavaScript do rodapé pede confirmação (data-confirmar) -->
                    <form action="/dispositivos/excluir" method="POST"
                          data-confirmar="Excluir este comedouro? Os horários e o histórico de alimentação dele também serão apagados. O pet não será apagado.">
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                        <button type="submit" class="btn btn-perigo btn-peq">Excluir</button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <form class="caixa" action="<?= $modo === 'editar' ? '/dispositivos/atualizar' : '/dispositivos' ?>" method="POST" novalidate>
            <?php if ($modo === 'editar'): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

            <div class="campos">
                <div class="c6">
                    <label for="nome">Nome do comedouro</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-dispositivo"/></svg>
                        <input type="text" id="nome" name="nome" placeholder="Ex: Comedouro 01" maxlength="80" required value="<?= $v('nome') ?>">
                    </div>
                </div>

                <div class="c6">
                    <label for="codigo_mac_esp">Código MAC do ESP32</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-chip"/></svg>
                        <input type="text" id="codigo_mac_esp" name="codigo_mac_esp" placeholder="AA:BB:CC:DD:EE:FF" maxlength="17" required value="<?= $v('codigo_mac_esp') ?>">
                    </div>
                    <p class="ajuda">O ESP32 mostra o MAC no Monitor Serial ao ligar.</p>
                </div>

                <div class="c12">
                    <label for="pet_id">Pet deste comedouro</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-pets"/></svg>
                        <select id="pet_id" name="pet_id">
                            <option value="0">Sem pet por enquanto</option>
                            <?php foreach ($pets as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"<?= (string) ($old['pet_id'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>><?= $e($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="ajuda">Cada comedouro atende um único pet, e cada pet só pode estar em um comedouro.</p>
                </div>
            </div>

            <div class="acoes-form" style="margin-top: 20px">
                <button type="submit" class="btn">Salvar</button>
                <a class="btn btn-sec" href="<?= $e($voltar) ?>">Cancelar</a>
            </div>
        </form>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
