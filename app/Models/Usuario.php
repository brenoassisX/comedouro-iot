<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Dados do usuário usados na tela Configuração (login e cadastro continuam no UsuarioController). */
class Usuario
{
    public static function buscar(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, nome, email, telefone, foto, notif_horarios, notif_alertas, idioma
               FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    /** $d['senha_hash'] = null mantém a senha atual. */
    public static function atualizarPerfil(int $id, array $d): void
    {
        $sql    = 'UPDATE usuarios SET nome = :nome, email = :email, telefone = :telefone, foto = :foto';
        $params = [':nome' => $d['nome'], ':email' => $d['email'], ':telefone' => $d['telefone'], ':foto' => $d['foto']];

        if ($d['senha_hash'] !== null) {
            $sql .= ', senha_hash = :senha_hash';
            $params[':senha_hash'] = $d['senha_hash'];
        }

        $params[':id'] = $id;
        Database::getConnection()->prepare($sql . ' WHERE id = :id')->execute($params);
    }

    public static function atualizarPreferencias(int $id, int $horarios, int $alertas, string $idioma): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE usuarios SET notif_horarios = :h, notif_alertas = :a, idioma = :i WHERE id = :id'
        );
        $stmt->execute([':h' => $horarios, ':a' => $alertas, ':i' => $idioma, ':id' => $id]);
    }
}
