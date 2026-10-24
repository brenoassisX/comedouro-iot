<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela pets. Todas as consultas filtram por usuario_id:
 * assim um usuário nunca enxerga, altera ou apaga o pet de outro (mesmo mudando o id na URL).
 *
 * REGRA DE NEGÓCIO: 1 comedouro = 1 pet. Quem guarda o vínculo é dispositivos.pet_id
 * (coluna única, ver migração 06). Ao EXCLUIR um pet, o comedouro dele NÃO é apagado:
 * o MySQL zera dispositivos.pet_id sozinho (FK com ON DELETE SET NULL).
 */
class Pet
{
    /** @return array<int, array<string, mixed>> */
    public static function listarPorUsuario(int $usuarioId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, nome, especie, raca, porte, idade_meses, peso_kg, foto
               FROM pets
              WHERE usuario_id = :usuario_id
              ORDER BY nome'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return $stmt->fetchAll();
    }

    /** Devolve o pet, ou null se não existir / não for deste usuário. */
    public static function buscar(int $id, int $usuarioId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, nome, especie, raca, porte, idade_meses, peso_kg, observacoes, foto, porcao_padrao_g
               FROM pets
              WHERE id = :id AND usuario_id = :usuario_id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
        $pet = $stmt->fetch();

        return $pet === false ? null : $pet;
    }

    /** Insere o pet e devolve o id criado. */
    public static function criar(int $usuarioId, array $d): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO pets (usuario_id, nome, especie, raca, porte, idade_meses, peso_kg, observacoes, foto, porcao_padrao_g)
             VALUES (:usuario_id, :nome, :especie, :raca, :porte, :idade_meses, :peso_kg, :observacoes, :foto, :porcao)'
        );
        $stmt->execute([
            ':usuario_id'  => $usuarioId,
            ':nome'        => $d['nome'],
            ':especie'     => $d['especie'],
            ':raca'        => $d['raca'] !== '' ? $d['raca'] : null,
            ':porte'       => $d['porte'],
            ':idade_meses' => $d['idade_meses'],
            ':peso_kg'     => $d['peso_kg'],
            ':observacoes' => $d['observacoes'] !== '' ? $d['observacoes'] : null,
            ':foto'        => $d['foto'],
            ':porcao'      => $d['porcao_padrao_g'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Atualiza todos os campos do pet (o WHERE com usuario_id impede mexer em pet alheio). */
    public static function atualizar(int $id, int $usuarioId, array $d): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE pets
                SET nome = :nome, especie = :especie, raca = :raca, porte = :porte,
                    idade_meses = :idade_meses, peso_kg = :peso_kg, observacoes = :observacoes,
                    foto = :foto, porcao_padrao_g = :porcao
              WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute([
            ':nome'        => $d['nome'],
            ':especie'     => $d['especie'],
            ':raca'        => $d['raca'] !== '' ? $d['raca'] : null,
            ':porte'       => $d['porte'],
            ':idade_meses' => $d['idade_meses'],
            ':peso_kg'     => $d['peso_kg'],
            ':observacoes' => $d['observacoes'] !== '' ? $d['observacoes'] : null,
            ':foto'        => $d['foto'],
            ':porcao'      => $d['porcao_padrao_g'],
            ':id'          => $id,
            ':usuario_id'  => $usuarioId,
        ]);
    }

    public static function excluir(int $id, int $usuarioId): void
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM pets WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
    }
}
