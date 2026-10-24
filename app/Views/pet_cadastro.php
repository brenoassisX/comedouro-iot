<?php
declare(strict_types=1);
/**
 * View: app/Views/pet_cadastro.php  (Cadastro do pet)
 * Variáveis do PetController: $erro (string), $old (array com o que foi digitado)
 */
$titulo       = 'Cadastro do pet';
$tituloPagina = 'Cadastro do pet';
$voltar       = '/pets';
require __DIR__ . '/partials/pet_head.php';

$old     = $old ?? [];
$v       = static fn (string $k): string => $e($old[$k] ?? '');
$marcado = static fn (string $k, string $valor, string $padrao = ''): string =>
    (($old[$k] ?? $padrao) === $valor) ? 'selected' : '';
?>
    <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

    <!-- enctype="multipart/form-data" é OBRIGATÓRIO para enviar a foto -->
    <form action="/pets" method="POST" enctype="multipart/form-data" novalidate>

        <div class="cad">
            <div class="foto-col">
                <div class="foto-box" id="fotoBox">
                    <img id="fotoPrev" alt="Prévia da foto" hidden>
                    <svg id="fotoIcone" aria-hidden="true"><use href="#i-pata"/></svg>
                </div>
                <label class="btn-foto" for="foto">Enviar foto</label>
                <input class="sr-only" type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                <p class="foto-msg" id="fotoMsg" role="status"></p>
            </div>

            <div class="campos">
                <div class="c5">
                    <label for="nome">Nome do pet</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-pata"/></svg>
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

        <button type="submit" class="btn">Salvar</button>
    </form>

    <script>
    (function () {
        var input = document.getElementById('foto');
        var img   = document.getElementById('fotoPrev');
        var icone = document.getElementById('fotoIcone');
        var msg   = document.getElementById('fotoMsg');

        input.addEventListener('change', function () {
            var arq = input.files[0];
            msg.textContent = '';

            if (!arq) { img.hidden = true; icone.style.display = ''; return; }

            // Mesmas regras do servidor (o servidor confere de novo): JPG/PNG/WebP até 2 MB
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(arq.type) === -1) {
                msg.textContent = 'Use JPG, PNG ou WebP.';
                input.value = ''; img.hidden = true; icone.style.display = ''; return;
            }
            if (arq.size > 2 * 1024 * 1024) {
                msg.textContent = 'Máximo de 2 MB.';
                input.value = ''; img.hidden = true; icone.style.display = ''; return;
            }

            img.src = URL.createObjectURL(arq);   // prévia local, sem enviar nada ainda
            img.hidden = false;
            icone.style.display = 'none';
        });
    })();
    </script>
<?php require __DIR__ . '/partials/pet_foot.php'; ?>
