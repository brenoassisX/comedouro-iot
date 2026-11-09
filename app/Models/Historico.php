<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Histórico de alimentação (tabela historico). Também é a "fila" de pedidos para o ESP32:
 *   pendente -> enviado (o ESP32 recebeu) -> concluido (o ESP32 confirmou)
 *
 * LIBERADA x CONSUMIDA (veja database/migracoes/09_sensores_e_consumo.sql):
 *   liberado_g  = o que a balança mediu que caiu na tigela (sem balança, vale enviado_g = o que o motor recebeu
 *                 para liberar, já descontando o que havia na tigela; sem ele, a porcao_g)
 *   consumido_g = o que o pet comeu (só existe quando a balança mediu)
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
     * Refeições de um pet agrupadas por dia (Relatórios).
     * Conta o que o ESP32 já recebeu ('enviado') ou confirmou ('concluido').
     * A data considerada é a da conclusão; sem confirmação, a do envio.
     *
     * Por dia devolve:
     *   refeicoes -> quantidade de refeições
     *   liberado  -> gramas liberadas (medidas pela balança; sem medida, a porção pedida)
     *   consumido -> gramas consumidas (soma só das refeições que a balança mediu)
     *   medidas   -> quantas refeições têm consumo medido (0 = sem dados de consumo)
     *
     * @return array<string, array{refeicoes:int, liberado:float, consumido:float, medidas:int}>  chave = 'Y-m-d'
     */
    public static function resumoPorDia(int $petId, string $inicio, string $fimExclusivo): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT DATE(COALESCE(concluido_em, enviado_em)) AS dia,
                    COUNT(*) AS refeicoes,
                    SUM(COALESCE(liberado_g, enviado_g, porcao_g)) AS liberado,
                    SUM(COALESCE(consumido_g, 0)) AS consumido,
                    COUNT(consumido_g) AS medidas
               FROM historico
              WHERE pet_id = :pet AND status IN ('enviado', 'concluido')
                AND COALESCE(concluido_em, enviado_em) >= :ini
                AND COALESCE(concluido_em, enviado_em) < :fim
              GROUP BY dia ORDER BY dia"
        );
        $stmt->execute([':pet' => $petId, ':ini' => $inicio, ':fim' => $fimExclusivo]);

        $resumo = [];
        foreach ($stmt->fetchAll() as $linha) {
            $resumo[(string) $linha['dia']] = [
                'refeicoes' => (int) $linha['refeicoes'],
                'liberado'  => (float) $linha['liberado'],
                'consumido' => (float) $linha['consumido'],
                'medidas'   => (int) $linha['medidas'],
            ];
        }

        return $resumo;
    }

    /** Última refeição do comedouro (qualquer origem): usada no painel "Última liberação" da tela Alimentação. */
    public static function ultima(int $dispositivoId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, origem, porcao_g, enviado_g, peso_antes_g, peso_depois_g, liberado_g, consumido_g, status, criado_em, enviado_em, concluido_em
               FROM historico WHERE dispositivo_id = :d ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':d' => $dispositivoId]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }
}
