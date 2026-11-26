<?php
declare(strict_types=1);
/**
 * View: app/Views/pet_form.php  (serve para CADASTRAR e para EDITAR)
 * Variáveis do PetController:
 *   $modo       'novo' | 'editar'
 *   $erro       string
 *   $old        array com os valores dos campos (digitados ou vindos do banco)
 *   $petId      int|null   (só no modo editar)
 *   $fotoAtual  string|null (nome do arquivo da foto atual, só no modo editar)
 */
$titulo    = $modo === 'editar' ? 'Editar pet' : 'Cadastro do pet';
$menuAtivo = 'pets';
require __DIR__ . '/partials/app_head.php';

$old        = $old ?? [];
$fotoAtual  = $fotoAtual ?? null;
$voltar     = $modo === 'editar' ? '/pets/perfil?id=' . (int) $petId : '/pets';
$v          = static fn (string $k): string => $e($old[$k] ?? '');
$marcado    = static fn (string $k, string $valor, string $padrao = ''): string =>
    (($old[$k] ?? $padrao) === $valor) ? 'selected' : '';
?>
        <header class="cab">
            <a class="seta" href="<?= $e($voltar) ?>" aria-label="Voltar"><svg aria-hidden="true"><use href="#i-seta"/></svg></a>
            <h1><?= $e($titulo) ?></h1>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <!-- enctype="multipart/form-data" é OBRIGATÓRIO para enviar a foto -->
        <form action="<?= $modo === 'editar' ? '/pets/atualizar' : '/pets' ?>" method="POST" enctype="multipart/form-data" novalidate>
            <?php if ($modo === 'editar'): ?>
                <input type="hidden" name="id" value="<?= (int) $petId ?>">
            <?php endif; ?>

            <div class="cad">
                <div class="foto-col">
                    <div class="foto-box" id="fotoBox">
                        <img id="fotoPrev" alt="Foto do pet"
                             <?= $fotoAtual ? 'src="/uploads/pets/' . $e($fotoAtual) . '"' : 'hidden' ?>>
                        <svg id="fotoIcone" aria-hidden="true"<?= $fotoAtual ? ' style="display:none"' : '' ?>><use href="#i-pets"/></svg>
                    </div>
                    <label class="btn-foto" for="foto"><?= $fotoAtual ? 'Trocar foto' : 'Enviar foto' ?></label>
                    <input class="sr-only" type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                    <p class="foto-msg" id="fotoMsg" role="status"></p>
                </div>

                <div class="campos">
                    <div class="c5">
                        <label for="nome">Nome do pet</label>
                        <div class="campo">
                            <svg class="icone" aria-hidden="true"><use href="#i-pets"/></svg>
                            <input type="text" id="nome" name="nome" placeholder="Nome do seu pet" maxlength="80" required value="<?= $v('nome') ?>">
                        </div>
                    </div>

                    <div class="c3">
                        <label for="especie">Espécie</label>
                        <div class="campo">
                            <svg class="icone" aria-hidden="true"><use href="#i-tag"/></svg>
                            <select id="especie" name="especie" required>
                                <option value="cao"  <?= $marcado('especie', 'cao', 'cao') ?>>Cão</option>
                                <option value="gato" <?= $marcado('especie', 'gato') ?>>Gato</option>
                            </select>
                        </div>
                    </div>

                    <div class="c4">
                        <label for="raca">Raça</label>
                        <div class="campo">
                            <svg class="icone" aria-hidden="true"><use href="#i-tag"/></svg>
                            <input type="text" id="raca" name="raca" placeholder="Ex: Pinscher" maxlength="80" value="<?= $v('raca') ?>">
                        </div>
                    </div>

                    <div class="c4">
                        <label for="porte">Porte</label>
                        <div class="campo">
                            <svg class="icone" aria-hidden="true"><use href="#i-regua"/></svg>
                            <select id="porte" name="porte" required>
                                <option value="pequeno" <?= $marcado('porte', 'pequeno', 'pequeno') ?>>Pequeno</option>
                                <option value="medio"   <?= $marcado('porte', 'medio') ?>>Médio</option>
                                <option value="grande"  <?= $marcado('porte', 'grande') ?>>Grande</option>
                            </select>
                        </div>
                    </div>

                    <div class="c4">
                        <label for="idade">Idade</label>
                        <div class="campo com-unidade">
                            <svg class="icone" aria-hidden="true"><use href="#i-calendario"/></svg>
                            <input type="number" id="idade" name="idade" min="0" max="30" step="0.5" inputmode="decimal" placeholder="Ex: 2" required value="<?= $v('idade') ?>">
                            <select class="unidade" name="unidade_idade" aria-label="Unidade da idade">
                                <option value="anos"  <?= $marcado('unidade_idade', 'anos', 'anos') ?>>anos</option>
                                <option value="meses" <?= $marcado('unidade_idade', 'meses') ?>>meses</option>
                            </select>
                        </div>
                    </div>

                    <div class="c4">
                        <label for="peso">Peso (kg)</label>
                        <div class="campo">
                            <svg class="icone" aria-hidden="true"><use href="#i-balanca"/></svg>
                            <input type="number" id="peso" name="peso" min="0.1" max="120" step="0.01" inputmode="decimal" placeholder="Ex: 3,5" required value="<?= $v('peso') ?>">
                        </div>
                    </div>

                    <div class="c4">
                        <label for="preco_kg">Preço do kg da ração (R$)</label>
                        <div class="campo">
                            <svg class="icone" aria-hidden="true"><use href="#i-balanca"/></svg>
                            <input type="number" id="preco_kg" name="preco_kg" min="0" max="9999" step="0.01" inputmode="decimal" placeholder="Ex: 28,90" value="<?= $v('preco') ?>">
                        </div>
                        <p class="ajuda">Opcional. Usado para calcular o gasto com ração nos relatórios.</p>
                    </div>

                    <div class="c12">
                        <label for="observacoes">Observações</label>
                        <div class="campo">
                            <svg class="icone" aria-hidden="true"><use href="#i-lapis"/></svg>
                            <textarea id="observacoes" name="observacoes" maxlength="500" placeholder="Alergias, restrições, rotina..."><?= $v('observacoes') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info">
                <h2>Por que são necessárias essas informações?</h2>
                <p>Esses dados são necessários para calcular a necessidade energética do pet.</p>
            </div>

            <div class="acoes-form">
                <button type="submit" class="btn">Salvar</button>
                <?php if ($modo === 'editar'): ?>
                    <a class="btn btn-sec" href="<?= $e($voltar) ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>

        <script>
        (function () {
            var input = document.getElementById('foto');
            var img   = document.getElementById('fotoPrev');
            var icone = document.getElementById('fotoIcone');
            var msg   = document.getElementById('fotoMsg');
            var srcOriginal = img.getAttribute('src');   // foto atual (modo editar), se houver

            function restaurar() {
                if (srcOriginal) { img.src = srcOriginal; img.hidden = false; icone.style.display = 'none'; }
                else { img.hidden = true; icone.style.display = ''; }
            }

            input.addEventListener('change', function () {
                var arq = input.files[0];
                msg.textContent = '';
                if (!arq) { restaurar(); return; }

                // Mesmas regras do servidor (o servidor confere de novo): JPG/PNG/WebP até 2 MB
                if (['image/jpeg', 'image/png', 'image/webp'].indexOf(arq.type) === -1) {
                    msg.textContent = 'Use JPG, PNG ou WebP.'; input.value = ''; restaurar(); return;
                }
                if (arq.size > 2 * 1024 * 1024) {
                    msg.textContent = 'Máximo de 2 MB.'; input.value = ''; restaurar(); return;
                }

                img.src = URL.createObjectURL(arq);   // prévia local, sem enviar nada ainda
                img.hidden = false;
                icone.style.display = 'none';
            });
        })();
        </script>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
