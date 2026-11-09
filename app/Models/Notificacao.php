<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

/**
 * Notificações dentro do sistema (tela Notificações + sininho do Início).
 *
 * Quem cria:
 *  - ApiController (a cada sincronização do ESP32): alimentação realizada/dispensada, próxima refeição,
 *    ração baixa e alerta de consumo.
 *  - verificarOffline() e verificarRelatorioSemanal(): chamadas quando o usuário abre o Início ou as
 *    Notificações, porque um dispositivo que caiu não consegue avisar sozinho.
 *
 * As preferências da tela Configuração valem aqui: se o usuário desligou a categoria, o aviso não é criado.
 */
class Notificacao
{
    /** Sem sinal por mais de 5 minutos = "Dispositivo offline" (o status Offline da tela aparece com 30 s). */
    public const SEGUNDOS_OFFLINE = 300;

    private const CATEGORIAS = [
        'alimentacao' => 'horarios',
        'proxima'     => 'horarios',
        'consumo'     => 'alertas',
        'nivel'       => 'alertas',
        'offline'     => 'alertas',
        'relatorio'   => 'sistema',   // sempre ligado
    ];

    /** Cria o aviso. Devolve false se o usuário desligou a categoria ou se a "chave" já existe. */
    public static function criar(
        int $usuarioId, string $tipo, string $titulo, string $mensagem,
        ?string $url = null, ?string $chave = null, ?string $criadoEm = null, ?int $dispositivoId = null
    ): bool {
        $pdo       = Database::getConnection();
        $categoria = self::CATEGORIAS[$tipo] ?? 'sistema';

        if ($categoria !== 'sistema') {
            $coluna = $categoria === 'horarios' ? 'notif_horarios' : 'notif_alertas';
            $stmt   = $pdo->prepare("SELECT $coluna FROM usuarios WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $usuarioId]);
            $ligado = $stmt->fetchColumn();
            if ($ligado === false || (int) $ligado !== 1) {
                return false;
            }
        }

        // INSERT IGNORE + chave única: se o mesmo aviso já existe, simplesmente não duplica
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO notificacoes (usuario_id, dispositivo_id, tipo, titulo, mensagem, url, chave, criado_em)
             VALUES (:u, :d, :tipo, :titulo, :mensagem, :url, :chave, :criado)'
        );
        $stmt->execute([
            ':u' => $usuarioId, ':d' => $dispositivoId, ':tipo' => $tipo,
            ':titulo' => mb_substr($titulo, 0, 80), ':mensagem' => mb_substr($mensagem, 0, 255),
            ':url' => $url, ':chave' => $chave, ':criado' => $criadoEm ?? date('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount() === 1;
    }

    /** @return array<int, array<string, mixed>> mais recentes primeiro */
    public static function listar(int $usuarioId, int $limite = 50): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, tipo, titulo, mensagem, url, lida, criado_em
               FROM notificacoes WHERE usuario_id = :u
              ORDER BY criado_em DESC, id DESC LIMIT :limite'
        );
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function contarNaoLidas(int $usuarioId): int
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = :u AND lida = 0');
        $stmt->execute([':u' => $usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    public static function marcarTodasLidas(int $usuarioId): void
    {
        Database::getConnection()->prepare('UPDATE notificacoes SET lida = 1 WHERE usuario_id = :u AND lida = 0')
            ->execute([':u' => $usuarioId]);
    }

    public static function limpar(int $usuarioId): void
    {
        Database::getConnection()->prepare('DELETE FROM notificacoes WHERE usuario_id = :u')->execute([':u' => $usuarioId]);
    }

    /**
     * "Dispositivo offline": comedouros sem ping há mais de 5 min e ainda não avisados.
     * A hora do aviso é a da queda (último ping + 5 min), não a de quando você abriu o sistema.
     * O ApiController zera offline_avisado quando o ESP32 volta, para avisar a próxima queda.
     */
    public static function verificarOffline(int $usuarioId): void
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nome, ultimo_ping FROM dispositivos
              WHERE usuario_id = :u AND ativo = 1 AND offline_avisado = 0
                AND ultimo_ping IS NOT NULL AND ultimo_ping < :limite'
        );
        $stmt->execute([':u' => $usuarioId, ':limite' => date('Y-m-d H:i:s', time() - self::SEGUNDOS_OFFLINE)]);

        foreach ($stmt->fetchAll() as $d) {
            $ping   = strtotime((string) $d['ultimo_ping']);
            $codigo = 'ESP32-' . str_pad((string) $d['id'], 3, '0', STR_PAD_LEFT);

            self::criar(
                $usuarioId, 'offline', 'Dispositivo offline',
                'O dispositivo ' . $codigo . ' (' . $d['nome'] . ') está sem conexão.',
                '/dispositivos?id=' . (int) $d['id'],
                'offline:' . (int) $d['id'] . ':' . date('YmdHis', $ping),
                date('Y-m-d H:i:s', $ping + self::SEGUNDOS_OFFLINE),
                (int) $d['id']
            );

            // Marca como avisado mesmo se o usuário desligou os alertas (para não checar de novo)
            $pdo->prepare('UPDATE dispositivos SET offline_avisado = 1 WHERE id = :id')->execute([':id' => $d['id']]);
        }
    }

    /**
     * "Relatório disponível": uma vez por semana, se algum pet teve refeições na semana anterior.
     * Fica pronto na segunda-feira (08:00) e leva direto ao relatório daquela semana.
     */
    public static function verificarRelatorioSemanal(int $usuarioId): void
    {
        $pdo          = Database::getConnection();
        $semanaAtual  = (new DateTimeImmutable('today'))->modify('monday this week');
        $inicio       = $semanaAtual->modify('-7 days');
        $chave        = 'relatorio:' . $inicio->format('Y-m-d');

        $stmt = $pdo->prepare('SELECT 1 FROM notificacoes WHERE usuario_id = :u AND chave = :c LIMIT 1');
        $stmt->execute([':u' => $usuarioId, ':c' => $chave]);
        if ($stmt->fetchColumn()) {
            return; // já avisado
        }

        $stmt = $pdo->prepare(
            "SELECT p.id FROM pets p
              WHERE p.usuario_id = :u
                AND EXISTS (SELECT 1 FROM historico h
                             WHERE h.pet_id = p.id AND h.status IN ('enviado', 'concluido')
                               AND COALESCE(h.concluido_em, h.enviado_em) >= :ini
                               AND COALESCE(h.concluido_em, h.enviado_em) < :fim)
              LIMIT 1"
        );
        $stmt->execute([':u' => $usuarioId, ':ini' => $inicio->format('Y-m-d H:i:s'), ':fim' => $semanaAtual->format('Y-m-d H:i:s')]);
        $petId = $stmt->fetchColumn();
        if ($petId === false) {
            return; // semana sem refeições: nada a reportar
        }

        $quando = min(time(), strtotime($semanaAtual->format('Y-m-d') . ' 08:00:00'));

        self::criar(
            $usuarioId, 'relatorio', 'Relatório disponível',
            'Seu relatório semanal (' . $inicio->format('d/m') . ' a ' . $semanaAtual->modify('-1 day')->format('d/m') . ') já está disponível para download.',
            '/relatorios?' . http_build_query(['pet' => (int) $petId, 'periodo' => 'semanal', 'data' => $inicio->format('Y-m-d')]),
            $chave,
            date('Y-m-d H:i:s', $quando)
        );
    }
}
