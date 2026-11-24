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

                <div class="c12">
                    <h3 style="font-size: .95rem; font-weight: 700">Calibração do sensor de nível (ultrassônico)</h3>
                    <p class="ajuda">Meça a distância do sensor até a ração. O sistema converte essa distância em porcentagem.</p>
                </div>

                <div class="c6">
                    <label for="cheio_cm">Distância com o reservatório cheio (cm)</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-regua"/></svg>
                        <input type="number" id="cheio_cm" name="cheio_cm" min="1" max="200" step="0.1" inputmode="decimal" required value="<?= $e($old['cheio_cm'] ?? '5') ?>">
                    </div>
                    <p class="ajuda">100% — ração quase encostando no sensor.</p>
                </div>

                <div class="c6">
                    <label for="vazio_cm">Distância com o reservatório vazio (cm)</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-regua"/></svg>
                        <input type="number" id="vazio_cm" name="vazio_cm" min="1" max="200" step="0.1" inputmode="decimal" required value="<?= $e($old['vazio_cm'] ?? '25') ?>">
                    </div>
                    <p class="ajuda">0% — fundo do reservatório.</p>
                </div>

                <div class="c12">
                    <h3 style="font-size: .95rem; font-weight: 700">Calibração da comporta (servo)</h3>
                    <p class="ajuda">O sistema converte a quantidade pedida em graus de abertura. Meça quanto cai em UMA abertura de 90° (pelo tempo informado abaixo) com o reservatório quase vazio e com ele cheio. Com mais ração em cima cai mais.</p>
                </div>

                <div class="c4">
                    <label for="g90_vazio">Gramas a 90° (reservatório quase vazio)</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-balanca"/></svg>
                        <input type="number" id="g90_vazio" name="g90_vazio" min="10" max="1000" step="1" inputmode="numeric" required value="<?= $e($old['g90_vazio'] ?? '100') ?>">
                    </div>
                </div>

                <div class="c4">
                    <label for="g90_cheio">Gramas a 90° (reservatório cheio)</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-balanca"/></svg>
                        <input type="number" id="g90_cheio" name="g90_cheio" min="10" max="1000" step="1" inputmode="numeric" required value="<?= $e($old['g90_cheio'] ?? '150') ?>">
                    </div>
                </div>

                <div class="c4">
                    <label for="abertura_ms">Tempo aberta (ms)</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-relogio"/></svg>
                        <input type="number" id="abertura_ms" name="abertura_ms" min="100" max="3000" step="50" inputmode="numeric" required value="<?= $e($old['abertura_ms'] ?? '600') ?>">
                    </div>
                    <p class="ajuda">Quanto a comporta fica aberta antes de fechar rápido.</p>
                </div>
            </div>

            <div class="acoes-form" style="margin-top: 20px">
                <button type="submit" class="btn">Salvar</button>
                <a class="btn btn-sec" href="<?= $e($voltar) ?>">Cancelar</a>
            </div>
        </form>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
