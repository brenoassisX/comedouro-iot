<?php
declare(strict_types=1);
/**
 * View: app/Views/configuracao.php  (visão geral)
 * Variáveis do ConfiguracaoController::index(): $usuario (array), $sucesso (string)
 */
$titulo    = 'Configuração';
$menuAtivo = 'config';
require __DIR__ . '/partials/app_head.php';
?>
        <?php if (!empty($sucesso)): ?><div class="alerta ok" role="status"><?= $e($sucesso) ?></div><?php endif; ?>

        <section class="caixa" aria-labelledby="t-perfil">
            <div class="cfg-cab">
                <span class="cfg-icone"><svg aria-hidden="true"><use href="#i-user"/></svg></span>
                <h2 id="t-perfil">Perfil do tutor</h2>
            </div>
            <div class="cfg-corpo cfg-linha">
                <div>
                    <div class="dado"><small>Nome</small><span><?= $e($usuario['nome']) ?></span></div>
                    <div class="dado"><small>E-mail</small><span><?= $e($usuario['email']) ?></span></div>
                </div>
                <a class="btn btn-sec" href="/configuracao/perfil">Editar perfil</a>
            </div>
        </section>

        <!-- As notificações e o idioma ficam no mesmo formulário: mexer em qualquer um salva sozinho -->
        <form id="formPrefs" action="/configuracao/preferencias" method="POST" style="display: contents">

            <section class="caixa" aria-labelledby="t-notif">
                <div class="cfg-cab">
                    <span class="cfg-icone"><svg aria-hidden="true"><use href="#i-sino"/></svg></span>
                    <div>
                        <h2 id="t-notif">Notificações</h2>
                        <p>Escolha quais alertas deseja receber.</p>
                    </div>
                </div>
                <div class="cfg-corpo">
                    <div class="pref">
                        <div><strong>Horários de refeição</strong><span>Avisos antes das refeições programadas</span></div>
                        <label class="toggle">
                            <input type="checkbox" name="notif_horarios" value="1"<?= (int) $usuario['notif_horarios'] === 1 ? ' checked' : '' ?> aria-label="Avisos de horários de refeição">
                            <span class="toggle-trilho"></span>
                        </label>
                    </div>
                    <div class="pref">
                        <div><strong>Alertas de alimentação</strong><span>Avisos sobre o nível de ração</span></div>
                        <label class="toggle">
                            <input type="checkbox" name="notif_alertas" value="1"<?= (int) $usuario['notif_alertas'] === 1 ? ' checked' : '' ?> aria-label="Alertas de consumo e nível de ração">
                            <span class="toggle-trilho"></span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="caixa" aria-labelledby="t-sistema">
                <div class="cfg-cab">
                    <span class="cfg-icone"><svg aria-hidden="true"><use href="#i-config"/></svg></span>
                    <h2 id="t-sistema">Sistema</h2>
                </div>
                <div class="cfg-corpo">
                    <div class="cfg-linha">
                        <div class="dado"><small>Idioma</small></div>
                        <div class="select-idioma">
                            <select name="idioma" aria-label="Idioma">
                                <option value="pt-BR" selected>Português</option>
                                <option value="en" disabled>English (em breve)</option>
                                <option value="es" disabled>Español (em breve)</option>
                            </select>
                        </div>
                    </div>
                    <p class="ajuda">As preferências são aplicadas a este dispositivo.</p>
                </div>
                <noscript><button type="submit" class="btn btn-peq" style="margin-top: 12px">Salvar preferências</button></noscript>
            </section>
        </form>

        <script>
            document.getElementById('formPrefs').addEventListener('change', function () { this.submit(); });
        </script>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
