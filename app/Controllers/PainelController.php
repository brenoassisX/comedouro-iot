<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Agendamento;
use App\Models\Dispositivo;
use App\Models\Pet;
use App\Services\CalculoEnergetico;
use PDOException;
use RuntimeException;

/**
 * Início (dashboard): resumo de UM pet por vez.
 *
 *   GET /painel          -> mostra o primeiro pet
 *   GET /painel?pet=ID   -> mostra o pet escolhido (as setas da tela só trocam este ?pet=)
 *
 * Tudo que aparece muda junto com o pet: perfil, status do comedouro dele, próxima alimentação,
 * ração restante e necessidade energética.
 *
 * POSSÍVEIS SITUAÇÕES:
 *  - Sem pets cadastrados -> a tela mostra o convite para cadastrar o primeiro pet.
 *  - Pet sem comedouro -> o card de status avisa e oferece "Vincular comedouro".
 *  - Comedouro sem horários -> "Próxima alimentação" mostra "—".
 *  - Pet sem peso (cadastro antigo) -> a necessidade energética mostra "—".
 *  - "Unknown column 'distancia_cm'" (42S22) -> falta rodar a migração 09 (e a 07/08 antes dela).
 *  - Sessão apontando para usuário que não existe mais -> volta para /login.
 */
class PainelController
{
    // ==================================================================
    // GET /painel
    // ==================================================================
    public function index(): void
    {
        // 1) Rota protegida: sem sessão -> volta para o login
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $usuarioId = (int) $_SESSION['usuario_id'];

        $erro        = '';
        $pets        = [];
        $pet         = null;
        $dispositivo = null;
        $status      = null;
        $proxima     = null;
        $calc        = null;
        $anteriorId  = 0;
        $seguinteId  = 0;
        $posicao     = 0;
        $totalPets   = 0;

        try {
            // 2) Nome do usuário
            $stmt = Database::getConnection()->prepare('SELECT nome FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $usuarioId]);
            $usuario = $stmt->fetch();

            if (!$usuario) { // sessão aponta para um usuário que já não existe (conta apagada)
                unset($_SESSION['usuario_id']);
                header('Location: /login');
                exit;
            }

            // 3) Pets e escolha do pet mostrado (carrossel por ?pet=)
            $pets      = Pet::listarPorUsuario($usuarioId);
            $totalPets = count($pets);

            if ($totalPets > 0) {
                $ids    = array_map('intval', array_column($pets, 'id'));
                $indice = array_search((int) ($_GET['pet'] ?? 0), $ids, true);
                $indice = $indice === false ? 0 : $indice;

                $pet        = $pets[$indice];
                $posicao    = $indice + 1;
                $anteriorId = $ids[($indice - 1 + $totalPets) % $totalPets]; // da primeira volta para a última
                $seguinteId = $ids[($indice + 1) % $totalPets];              // da última volta para a primeira

                // 4) Comedouro deste pet, status e próxima refeição
                $dispositivo = Dispositivo::buscarPorPet((int) $pet['id'], $usuarioId);
                if ($dispositivo !== null) {
                    $status  = Dispositivo::status($dispositivo);
                    $proxima = Agendamento::proximo((int) $dispositivo['id']);
                }

                // 5) Necessidade energética (mesma conta da tela do pet)
                $calc = CalculoEnergetico::calcular(
                    $pet['especie'],
                    $pet['peso_kg'] !== null ? (float) $pet['peso_kg'] : null,
                    $pet['idade_meses'] !== null ? (int) $pet['idade_meses'] : null,
                    $pet['porte']
                );
            }
        } catch (PDOException $e) { // PDOException antes de RuntimeException (é "filha" dela)
            error_log('[painel] ' . $e->getMessage());
            $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, mensagem genérica
        } catch (RuntimeException $e) {
            error_log('[painel] ' . $e->getMessage());
            $erro = $e->getMessage();
        }

        // Variáveis consumidas por app/Views/painel.php
        $nomeUsuario  = $usuario['nome'] ?? '';
        $primeiroNome = explode(' ', trim((string) $nomeUsuario))[0] ?: 'Usuário';

        // Impede que o botão "voltar" do navegador mostre o painel depois do logout
        header('Cache-Control: no-store, no-cache, must-revalidate');

        require_once __DIR__ . '/../Views/painel.php';
    }
}
