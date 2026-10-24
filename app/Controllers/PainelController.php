<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use Throwable;

/**
 * Painel (tela inicial) do utilizador autenticado.
 */
class PainelController
{
    /** GET /painel */
    public function index(): void
    {
        // 1) Rota protegida: sem sessão -> volta para o login
        if (empty($_SESSION['usuario_id'])) {
            $this->redirecionar('/login');
        }

        // 2) Busca o nome do utilizador
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT nome FROM usuarios WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => (int) $_SESSION['usuario_id']]);
            $usuario = $stmt->fetch();
        } catch (Throwable $e) {
            error_log('[PainelController::index] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro interno. Tente novamente em instantes.';
            return;
        }

        // Sessão aponta para um utilizador que já não existe (conta apagada)
        if (!$usuario) {
            unset($_SESSION['usuario_id']);
            $this->redirecionar('/login');
        }

        // Variáveis consumidas por app/Views/painel.php
        $nomeUsuario  = $usuario['nome'];
        $primeiroNome = explode(' ', trim($usuario['nome']))[0];

        // Impede que o botão "voltar" do navegador mostre o painel depois do logout
        header('Cache-Control: no-store, no-cache, must-revalidate');

        require_once __DIR__ . '/../Views/painel.php';
    }

    private function redirecionar(string $destino): never
    {
        header('Location: ' . $destino, true, 303);
        exit;
    }
}
