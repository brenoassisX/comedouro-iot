<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notificacao;
use PDOException;
use RuntimeException;

/**
 * Notificações (tela aberta pelo sininho do Início).
 *
 * Rotas (public/index.php):
 *   GET  /notificacoes         -> index()   lista os avisos (e marca todos como lidos)
 *   POST /notificacoes/limpar  -> limpar()  apaga todos os avisos do usuário
 *
 * POSSÍVEIS SITUAÇÕES:
 *  - "Table '...notificacoes' doesn't exist" (42S02) -> falta rodar a migração 10.
 *  - Lista vazia -> ainda não houve eventos, ou as categorias estão desligadas em Configuração.
 *  - "Dispositivo offline" demora a aparecer -> ele só é gerado quando você abre o Início ou esta tela,
 *    e só depois de 5 minutos sem sinal do ESP32. A hora mostrada é a da queda, não a da abertura.
 */
class NotificacaoController
{
    // ==================================================================
    // GET /notificacoes
    // ==================================================================
    public function index(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $usuarioId     = (int) $_SESSION['usuario_id'];
        $erro          = '';
        $notificacoes  = [];

        try {
            // Avisos que dependem de checagem "na hora" (um comedouro offline não consegue avisar sozinho)
            Notificacao::verificarOffline($usuarioId);
            Notificacao::verificarRelatorioSemanal($usuarioId);

            $notificacoes = Notificacao::listar($usuarioId, 50);   // 'lida' ainda reflete o estado ANTES desta visita
            Notificacao::marcarTodasLidas($usuarioId);              // as novas ficam destacadas só nesta visita
        } catch (PDOException $e) { // PDOException antes de RuntimeException (é "filha" dela)
            error_log('[notificacoes] ' . $e->getMessage());
            $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, mensagem genérica
        } catch (RuntimeException $e) {
            error_log('[notificacoes] ' . $e->getMessage());
            $erro = $e->getMessage();
        }

        header('Cache-Control: no-store, no-cache, must-revalidate');
        require BASE_PATH . '/app/Views/notificacoes.php';
    }

    // ==================================================================
    // POST /notificacoes/limpar
    // ==================================================================
    public function limpar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        try {
            Notificacao::limpar((int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[notificacoes/limpar] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        } catch (RuntimeException $e) {
            error_log('[notificacoes/limpar] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }

        header('Location: /notificacoes');
        exit;
    }
}
