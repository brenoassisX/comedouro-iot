<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\PlanoAbertura;

/**
 * Tamanhos de porção do usuário: baixa, media e alta (tabela porcoes - migração 12).
 * O usuário pode trocar o NOME ("Baixa" -> "Petisco") e as GRAMAS de cada tamanho.
 * Padrões: Pequena 20 g (1 abertura de 30°), Média 40 g (2), Grande 60 g (3).
 */
class Porcao
{
    public const CHAVES  = ['baixa', 'media', 'alta'];
    public const PADROES = [
        'baixa' => ['nome' => 'Pequena', 'gramas' => 20],
        'media' => ['nome' => 'Média',   'gramas' => 40],
        'alta'  => ['nome' => 'Grande',  'gramas' => 60],
    ];

    /**
     * Devolve ['baixa' => ['nome'=>..,'gramas'=>..], 'media' => ..., 'alta' => ...].
     * Se o usuário ainda não tem as linhas, cria com os valores padrão.
     * ERRO "Table 'porcoes' doesn't exist" -> importe a migração 12.
     */
    public static function doUsuario(int $usuarioId): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT chave, nome, gramas FROM porcoes WHERE usuario_id = :u');
        $stmt->execute([':u' => $usuarioId]);

        $lista = [];
        foreach ($stmt->fetchAll() as $l) {
            $lista[$l['chave']] = ['nome' => (string) $l['nome'], 'gramas' => (int) $l['gramas']];
        }

        $ins = $pdo->prepare('INSERT IGNORE INTO porcoes (usuario_id, chave, nome, gramas) VALUES (:u, :c, :n, :g)');
        foreach (self::PADROES as $chave => $p) {
            if (!isset($lista[$chave])) {
                $ins->execute([':u' => $usuarioId, ':c' => $chave, ':n' => $p['nome'], ':g' => $p['gramas']]);
                $lista[$chave] = $p;
            }
        }

        return $lista;
    }

    public static function salvar(int $usuarioId, string $chave, string $nome, int $gramas): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE porcoes SET nome = :n, gramas = :g WHERE usuario_id = :u AND chave = :c'
        );
        $stmt->execute([':n' => $nome, ':g' => $gramas, ':u' => $usuarioId, ':c' => $chave]);
    }

    /** Nome do tamanho cujas gramas são iguais às informadas (para mostrar "Média" em vez de "50 g"). */
    public static function nomePorGramas(array $porcoes, int $gramas): ?string
    {
        foreach ($porcoes as $p) {
            if ($p['gramas'] === $gramas) {
                return $p['nome'];
            }
        }
        return null;
    }

    /** Texto "≈ 40° de abertura" para ajudar o usuário a entender cada tamanho. */
    public static function anguloTexto(int $gramas, array $dispositivo = []): string
    {
        $v = (float) ($dispositivo['servo_g90_vazio'] ?? 60.0);
        $c = (float) ($dispositivo['servo_g90_cheio'] ?? 60.0);
        $p = PlanoAbertura::calcular((float) $gramas, null, $v, $c);

        return $p['pulsos'] . ' abertura(s) de ' . $p['angulo'] . '°';
    }
}
