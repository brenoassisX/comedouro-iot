<?php
declare(strict_types=1);
/**
 * View: app/Views/configuracao_perfil.php  (Editar perfil)
 * Variáveis do ConfiguracaoController: $usuario (array), $erro (string), $old (array)
 */
$titulo    = 'Configuração';
$menuAtivo = 'config';
require __DIR__ . '/partials/app_head.php';

$old = $old ?? [];
$v   = static fn (string $k): string => $e($old[$k] ?? '');
?>
        <header class="cfg-titulo">
            <h1>Configuração</h1>
            <p class="sub">Organize seus dados em grupos por tema para facilitar a leitura e edição.</p>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <!-- enctype="multipart/form-data" é OBRIGATÓRIO para enviar a foto -->
        <form class="form-config" action="/configuracao/perfil" method="POST" enctype="multipart/form-data" novalidate style="display: contents">

            <div class="grupo-cab"><b>Dados pessoais</b> Informações básicas da conta.</div>
            <section class="caixa">
                <div class="grade-cfg">
                    <div>
                        <label for="nome">Nome completo</label>
                        <input class="input-cfg" type="text" id="nome" name="nome" maxlength="100" required value="<?= $v('nome') ?>">
                    </div>
                    <div>
                        <label for="email">E-mail</label>
                        <input class="input-cfg" type="email" id="email" name="email" maxlength="150" required value="<?= $v('email') ?>">
                    </div>
                    <div>
                        <label for="telefone">Telefone</label>
                        <input class="input-cfg" type="tel" id="telefone" name="telefone" maxlength="20" placeholder="(00) 00000-0000" value="<?= $v('telefone') ?>">
                    </div>
                    <div>
                        <label for="senha">Nova senha</label>
                        <input class="input-cfg" type="password" id="senha" name="senha" placeholder="Deixe em branco para manter a senha atual" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="confirmar_senha">Confirmar nova senha</label>
                        <input class="input-cfg" type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Digite novamente a nova senha" autocomplete="new-password">
                    </div>
                    <div>
                        <label>Segurança da conta</label>
                        <div class="seguranca">
                            <strong>Segurança da conta</strong>
                            Use uma senha forte com letras e números.<br>
                            Nunca compartilhe sua senha com terceiros.
                        </div>
                    </div>
                </div>
            </section>

            <div class="grupo-cab"><b>Foto do perfil</b> Personalize sua conta com uma imagem.</div>
            <section class="caixa foto-perfil">
                <div class="avatar-grande">
                    <div class="anel" id="avatarAnel">
                        <?php if (!empty($usuario['foto'])): ?>
                            <img id="avatarImg" src="/uploads/usuarios/<?= $e($usuario['foto']) ?>" alt="Sua foto de perfil">
                        <?php else: ?>
                            <img id="avatarImg" alt="Prévia da foto" hidden>
                            <svg id="avatarIcone" aria-hidden="true"><use href="#i-user"/></svg>
                        <?php endif; ?>
                    </div>
                    <span class="camera"><svg aria-hidden="true"><use href="#i-camera"/></svg></span>
                </div>
                <div>
                    <h3>Foto do perfil</h3>
                    <p>Adicione uma foto para personalizar sua conta.</p>
                    <p>Formatos aceitos: JPG ou PNG</p>
                    <label class="btn-foto" for="foto">Alterar foto</label>
                    <input class="sr-only" type="file" id="foto" name="foto" accept="image/jpeg,image/png">
                    <p class="foto-msg" id="fotoMsg" role="status"></p>
                </div>
            </section>

            <div class="grupo-cab"><b>Ações</b> Salve ou cancele as alterações.</div>
            <section class="caixa acoes-cfg">
                <p>Seus dados são usados para identificar sua conta no PetFeeder.</p>
                <div style="display: flex; gap: 10px">
                    <a class="btn btn-sec" href="/configuracao">Cancelar</a>
                    <button type="submit" class="btn">Salvar alterações</button>
                </div>
            </section>
        </form>

        <script>
        (function () {
            var input = document.getElementById('foto');
            var img   = document.getElementById('avatarImg');
            var icone = document.getElementById('avatarIcone');
            var msg   = document.getElementById('fotoMsg');

            input.addEventListener('change', function () {
                var arq = input.files[0];
                msg.textContent = '';
                if (!arq) { return; }

                // Mesmas regras do servidor (ele confere de novo): JPG/PNG até 2 MB
                if (['image/jpeg', 'image/png'].indexOf(arq.type) === -1) { msg.textContent = 'Use JPG ou PNG.'; input.value = ''; return; }
                if (arq.size > 2 * 1024 * 1024) { msg.textContent = 'Máximo de 2 MB.'; input.value = ''; return; }

                img.src = URL.createObjectURL(arq);   // prévia local, sem enviar nada ainda
                img.hidden = false;
                if (icone) { icone.style.display = 'none'; }
            });
        })();
        </script>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
