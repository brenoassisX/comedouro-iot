<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limite de tentativas em janela deslizante, guardado no MySQL (tabela limite_requisicoes).
 * Cada tentativa vira uma linha; "excedeu" conta as linhas dentro da janela.
 */
final class RateLimiter
{
    public static function excedeu(string $acao, string $chave, int $max, int $janelaSegundos): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM limite_requisicoes
              WHERE acao = :acao AND chave = :chave AND criado_em > :desde'
        );
        $stmt->execute([
            ':acao'  => $acao,
            ':chave' => hash('sha256', $chave),
            ':desde' => date('Y-m-d H:i:s', time() - $janelaSegundos),
        ]);

        return (int) $stmt->fetchColumn() >= $max;
    }

    public static function registrar(string $acao, string $chave): void
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO limite_requisicoes (acao, chave, criado_em) VALUES (:acao, :chave, :agora)'
        );
        $stmt->execute([':acao' => $acao, ':chave' => hash('sha256', $chave), ':agora' => date('Y-m-d H:i:s')]);

        // Faxina ocasional (~1% das chamadas): nenhuma janela usada passa de 1 dia
        if (random_int(1, 100) === 1) {
            $pdo->prepare('DELETE FROM limite_requisicoes WHERE criado_em < :corte')
                ->execute([':corte' => date('Y-m-d H:i:s', time() - 86400)]);
        }
    }

    /** Zera o contador de uma chave (ex.: login bem-sucedido zera as falhas do e-mail). */
    public static function limpar(string $acao, string $chave): void
    {
        Database::getConnection()
            ->prepare('DELETE FROM limite_requisicoes WHERE acao = :acao AND chave = :chave')
            ->execute([':acao' => $acao, ':chave' => hash('sha256', $chave)]);
    }
}
