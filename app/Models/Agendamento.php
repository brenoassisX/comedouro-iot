<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Horários de alimentação programados (tabela agendamentos). Cada horário pertence a um comedouro. */
class Agendamento
{
    /** @return array<int, array<string, mixed>> */
    public static function listarPorDispositivo(int $dispositivoId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, horario, porcao_g FROM agendamentos
              WHERE dispositivo_id = :d AND ativo = 1 ORDER BY horario'
        );
        $stmt->execute([':d' => $dispositivoId]);

        return $stmt->fetchAll();
    }

    /** Devolve o horário só se o comedouro dele for do usuário (senão null). */
    public static function buscar(int $id, int $usuarioId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT a.id, a.dispositivo_id, a.horario, a.porcao_g
               FROM agendamentos a
               INNER JOIN dispositivos d ON d.id = a.dispositivo_id
              WHERE a.id = :id AND d.usuario_id = :usuario_id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    public static function existeHorario(int $dispositivoId, string $horario, int $ignorarId = 0): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM agendamentos
              WHERE dispositivo_id = :d AND horario = :h AND ativo = 1 AND id <> :ignorar'
        );
        $stmt->execute([':d' => $dispositivoId, ':h' => $horario . ':00', ':ignorar' => $ignorarId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function criar(int $dispositivoId, string $horario, int $gramas): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO agendamentos (dispositivo_id, horario, porcao_g) VALUES (:d, :h, :g)');
        $stmt->execute([':d' => $dispositivoId, ':h' => $horario . ':00', ':g' => $gramas]);

        return (int) $pdo->lastInsertId();
    }

    public static function atualizar(int $id, string $horario, int $gramas): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE agendamentos SET horario = :h, porcao_g = :g WHERE id = :id');
        $stmt->execute([':h' => $horario . ':00', ':g' => $gramas, ':id' => $id]);
    }

    public static function excluir(int $id): void
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM agendamentos WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
