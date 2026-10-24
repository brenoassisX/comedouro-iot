<?php
declare(strict_types=1);
/**
 * View: app/Views/pets.php  (Meus Pets - lista)
 * Variáveis do PetController::index(): $pets (array), $erro (string)
 */
$titulo    = 'Meus Pets';
$menuAtivo = 'pets';
require __DIR__ . '/partials/app_head.php';
?>
        <header class="cab">
            <h1>Meus pets</h1>
            <div class="cab-acoes">
                <a class="btn btn-peq" href="/pets/novo"><svg aria-hidden="true"><use href="#i-mais"/></svg>Cadastrar pet</a>
            </div>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <?php if (empty($pets)): ?>
            <div class="vazio">
                <p>Você ainda não cadastrou nenhum pet.</p>
                <a class="btn" href="/pets/novo"><svg aria-hidden="true"><use href="#i-mais"/></svg>Cadastrar meu primeiro pet</a>
            </div>
        <?php else: ?>
            <div class="grade-pets">
                <?php foreach ($pets as $p): ?>
                    <a class="card-pet" href="/pets/perfil?id=<?= (int) $p['id'] ?>">
                        <div class="foto-box">
                            <?php if (!empty($p['foto'])): ?>
                                <img src="/uploads/pets/<?= $e($p['foto']) ?>" alt="Foto de <?= $e($p['nome']) ?>">
                            <?php else: ?>
                                <svg aria-hidden="true"><use href="#i-pets"/></svg>
                            <?php endif; ?>
                        </div>
                        <strong><?= $e($p['nome']) ?></strong>
                        <span><?= $e($p['raca'] ?: ($p['especie'] === 'gato' ? 'Gato' : 'Cão')) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
