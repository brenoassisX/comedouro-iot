<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Histórico de alimentação (tabela historico). Também é a "fila" de pedidos para o ESP32:
 *   pendente -> enviado (o ESP32 recebeu) -> concluido (o ESP32 confirmou)
 */
class Historico
{
    /** Cria um pedido "Liberar agora" (origem manual). O ESP32 recebe na próxima sincronização. */
    public static function criarPedidoManual(int $dispositivoId, ?int $petId, int $gramas): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO historico (dispositivo_id, pet_id, origem, porcao_g, status, criado_em)
             VALUES (:d, :pet, 'manual', :g, 'pendente', :agora)"
        );
        $stmt->execute([':d' => $dispositivoId, ':pet' => $petId, ':g' => $gramas, ':agora' => date('Y-m-d H:i:s')]);

        return (int) $pdo->lastInsertId();
    }

    public static function temPedidoPendente(int $dispositivoId): bool
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM historico WHERE dispositivo_id = :d AND status = 'pendente'"
        );
        $stmt->execute([':d' => $dispositivoId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Refeições servidas de um pet, agrupadas por dia (usado nos Relatórios).
     * Conta o que o ESP32 já recebeu ('enviado') ou confirmou ('concluido').
     * A data considerada é a da conclusão; se não houve confirmação, a do envio.
     *
     * @return array<string, array{refeicoes:int, gramas:int}>  chave = 'Y-m-d'
     */
    public static function resumoPorDia(int $petId, string $inicio, string $fimExclusivo): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT DATE(COALESCE(concluido_em, enviado_em)) AS dia, COUNT(*) AS refeicoes, SUM(porcao_g) AS gramas
               FROM historico
              WHERE pet_id = :pet AND status IN ('enviado', 'concluido')
                AND COALESCE(concluido_em, enviado_em) >= :ini
                AND COALESCE(concluido_em, enviado_em) < :fim
              GROUP BY dia ORDER BY dia"
        );
        $stmt->execute([':pet' => $petId, ':ini' => $inicio, ':fim' => $fimExclusivo]);

        $resumo = [];
        foreach ($stmt->fetchAll() as $linha) {
            $resumo[(string) $linha['dia']] = ['refeicoes' => (int) $linha['refeicoes'], 'gramas' => (int) $linha['gramas']];
        }

        return $resumo;
    }
}
