<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Comedouros (tabela dispositivos). Toda consulta filtra por usuario_id:
 * um usuário nunca vê nem altera o comedouro de outro.
 *
 * REGRA DE NEGÓCIO: 1 comedouro = 1 pet (dispositivos.pet_id, único - migração 06).
 * Online/Offline: o ESP32 grava ultimo_ping a cada sincronização (a cada 10 s);
 * consideramos ONLINE se o último ping tem até SEGUNDOS_ONLINE segundos.
 */
class Dispositivo
{
    public const SEGUNDOS_ONLINE = 30;

    private const SELECT_BASE =
        'SELECT d.id, d.usuario_id, d.pet_id, d.nome, d.codigo_mac_esp, d.nivel_racao, d.distancia_cm, d.peso_tigela_g, d.reservatorio_cheio_cm, d.reservatorio_vazio_cm, d.ultimo_ping,
                p.nome AS pet_nome, p.foto AS pet_foto, p.porcao_padrao_g
           FROM dispositivos d
           LEFT JOIN pets p ON p.id = d.pet_id ';

    /** @return array<int, array<string, mixed>> */
    public static function listarPorUsuario(int $usuarioId): array
    {
        $stmt = Database::getConnection()->prepare(
            self::SELECT_BASE . 'WHERE d.usuario_id = :usuario_id AND d.ativo = 1 ORDER BY d.id'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $usuarioId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            self::SELECT_BASE . 'WHERE d.id = :id AND d.usuario_id = :usuario_id LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    /** Pets do usuário que ainda NÃO têm comedouro (mais o pet deste próprio comedouro, ao editar). */
    public static function petsDisponiveis(int $usuarioId, int $dispositivoAtualId = 0): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, nome FROM pets
              WHERE usuario_id = :usuario_id
                AND id NOT IN (SELECT pet_id FROM dispositivos WHERE pet_id IS NOT NULL AND id <> :atual)
              ORDER BY nome'
        );
        $stmt->execute([':usuario_id' => $usuarioId, ':atual' => $dispositivoAtualId]);

        return $stmt->fetchAll();
    }

    public static function criar(int $usuarioId, array $d): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO dispositivos (usuario_id, pet_id, nome, codigo_mac_esp, reservatorio_cheio_cm, reservatorio_vazio_cm)
             VALUES (:usuario_id, :pet_id, :nome, :mac, :cheio, :vazio)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId, ':pet_id' => $d['pet_id'], ':nome' => $d['nome'],
            ':mac' => $d['codigo_mac_esp'], ':cheio' => $d['cheio_cm'], ':vazio' => $d['vazio_cm'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function atualizar(int $id, int $usuarioId, array $d): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE dispositivos
                SET pet_id = :pet_id, nome = :nome, codigo_mac_esp = :mac,
                    reservatorio_cheio_cm = :cheio, reservatorio_vazio_cm = :vazio
              WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute([
            ':pet_id' => $d['pet_id'], ':nome' => $d['nome'], ':mac' => $d['codigo_mac_esp'],
            ':cheio' => $d['cheio_cm'], ':vazio' => $d['vazio_cm'], ':id' => $id, ':usuario_id' => $usuarioId,
        ]);
    }

    /** O comedouro (ativo) que atende este pet, ou null se o pet ainda não tem comedouro. */
    public static function buscarPorPet(int $petId, int $usuarioId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            self::SELECT_BASE . 'WHERE d.pet_id = :pet_id AND d.usuario_id = :usuario_id AND d.ativo = 1 LIMIT 1'
        );
        $stmt->execute([':pet_id' => $petId, ':usuario_id' => $usuarioId]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    /**
     * Converte a distância medida pelo sensor ultrassônico em porcentagem de ração.
     *   distância pequena (perto do sensor) = reservatório CHEIO  -> 100%
     *   distância grande (longe do sensor)  = reservatório VAZIO  ->   0%
     * Fórmula: (vazio - distância) / (vazio - cheio) x 100, limitada entre 0 e 100.
     * Se a calibração estiver errada (vazio <= cheio) devolve 0.
     */
    public static function distanciaParaPercentual(float $distanciaCm, float $cheioCm, float $vazioCm): int
    {
        if ($vazioCm <= $cheioCm) {
            return 0;
        }
        $pct = ($vazioCm - $distanciaCm) / ($vazioCm - $cheioCm) * 100;

        return (int) round(max(0.0, min(100.0, $pct)));
    }

    /** Apaga o comedouro. Os horários (agendamentos) e o histórico dele são apagados junto (FK CASCADE). */
    public static function excluir(int $id, int $usuarioId): void
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM dispositivos WHERE id = :id AND usuario_id = :usuario_id');
        $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
    }

    /** Transforma a linha do banco nos textos mostrados na tela Dispositivos. */
    public static function status(array $d): array
    {
        $ping   = !empty($d['ultimo_ping']) ? strtotime((string) $d['ultimo_ping']) : false;
        $online = $ping !== false && (time() - $ping) <= self::SEGUNDOS_ONLINE;
        $nivel  = $d['nivel_racao'] !== null ? (int) $d['nivel_racao'] : null;

        if ($nivel === null) {
            $reservatorio = 'Sem leitura';
        } elseif ($nivel === 0) {
            $reservatorio = 'Vazio';
        } elseif ($nivel <= 20) {
            $reservatorio = 'Pouca ração';
        } else {
            $reservatorio = 'Com ração';
        }

        return [
            'online'        => $online,
            'atualizado'    => $ping !== false ? date('H:i', $ping) : '—',
            'funcionamento' => $online ? 'Normal' : 'Sem comunicação',
            'reservatorio'  => $reservatorio,
            'conexao'       => $online ? 'Wi-Fi conectado' : 'Desconectado',
            'nivel'         => $nivel,
            'peso'          => isset($d['peso_tigela_g']) ? (float) $d['peso_tigela_g'] : null,
            'codigo'        => 'ESP32-' . str_pad((string) $d['id'], 3, '0', STR_PAD_LEFT),
        ];
    }
}
