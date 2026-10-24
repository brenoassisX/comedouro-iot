<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use PDOException;
use RuntimeException;

/**
 * API chamada pelo ESP32 (não é uma tela: só responde JSON).
 *
 *   POST /api/sincronizar
 *
 * O ESP32 envia a cada 10 s:
 *   {"codigo_mac_esp": "AA:BB:CC:DD:EE:FF", "nivel_racao": 85}
 *   (opcional) "comando_concluido": 12  -> avisa que terminou de executar o comando de id 12
 *
 * O PHP responde:
 *   {"status":"ok","comando":"alimentar","gramas":50,"id":12}   -> girar o motor (50 g)
 *   {"status":"ok","comando":"nenhum","gramas":0}                -> nada a fazer
 *   {"status":"erro","mensagem":"..."}                           -> algo deu errado (veja a tabela abaixo)
 *
 * Identificação: só pelo MAC (sem token, de propósito, por simplicidade).
 */
class ApiController
{
    // ==================================================================
    // POST /api/sincronizar
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO (código HTTP -> o que o ESP32 vê no Monitor Serial):
     *
     *  1) Corpo que não é JSON (ou vazio)                 -> 400 "Corpo inválido: envie um JSON."
     *       -> no ESP32 faltou serializeJson()/o corpo do POST. O cabeçalho Content-Type não é
     *          obrigatório para o PHP ler, mas é boa prática (addHeader).
     *
     *  2) codigo_mac_esp ausente ou fora do formato AA:BB:CC:DD:EE:FF  -> 422
     *       -> use WiFi.macAddress() (só depois do WiFi.mode/WiFi.begin).
     *
     *  3) nivel_racao ausente, texto ou fora de 0 a 100   -> 422
     *
     *  4) MAC não cadastrado na tabela dispositivos (ou ativo = 0)  -> 404 "Dispositivo não cadastrado..."
     *       -> cadastre/atualize o MAC no banco:
     *          UPDATE dispositivos SET codigo_mac_esp = '24:6F:28:AA:BB:CC' WHERE id = 1;
     *
     *  5) MySQL desligado ou erro de SQL                  -> 500 "Erro no banco de dados: ..."
     *       "Erro ao conectar ao banco de dados." -> MySQL desligado / senha do banco errada (config.php).
     *       "Unknown column 'codigo_mac_esp'"     -> falta rodar database/migracoes/07_dispositivos_simples.sql.
     *       "Table '...historico' doesn't exist"  -> falta rodar o schema.sql.
     *
     *  6) Tudo certo -> 200 com o comando (ou "nenhum").
     *
     * Erros que NÃO chegam aqui (o ESP32 nem recebe resposta do PHP):
     *  - HTTPClient devolve -1 (connection refused): servidor inacessível. Confira o IP no URL,
     *    se o PHP foi iniciado com 0.0.0.0 (e não localhost) e se o firewall libera a porta 8000.
     *  - HTTPClient devolve -11 (read timeout): o servidor demorou mais de 5 s para responder.
     *  - 404 com {"erro":"Rota nao encontrada."}: a rota do URL está errada (ou o projeto está
     *    numa subpasta, ex.: /comedouro/public/api/sincronizar).
     *  - 405: foi usado GET; a rota só aceita POST.
     */
    public function sincronizar(): void
    {
        // ---- 1) Lê e valida o que o ESP32 mandou ----
        $dados = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($dados)) {
            $this->responder(['status' => 'erro', 'mensagem' => 'Corpo inválido: envie um JSON.'], 400);
        }

        $mac         = strtoupper(trim((string) ($dados['codigo_mac_esp'] ?? '')));
        $nivel       = filter_var($dados['nivel_racao'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        $concluidoId = (int) ($dados['comando_concluido'] ?? 0);

        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            $this->responder(['status' => 'erro', 'mensagem' => 'codigo_mac_esp inválido (use AA:BB:CC:DD:EE:FF).'], 422);
        }
        if ($nivel === false) {
            $this->responder(['status' => 'erro', 'mensagem' => 'nivel_racao deve ser um inteiro de 0 a 100.'], 422);
        }

        try {
            $pdo   = Database::getConnection(); // pode lançar RuntimeException (MySQL desligado)
            $agora = date('Y-m-d H:i:s');       // hora do PHP (fuso definido no index.php)

            // ---- 2) Quem é este comedouro? ----
            $stmt = $pdo->prepare('SELECT id FROM dispositivos WHERE codigo_mac_esp = :mac AND ativo = 1 LIMIT 1');
            $stmt->execute([':mac' => $mac]);
            $dispositivo = $stmt->fetch();

            if ($dispositivo === false) {
                $this->responder(['status' => 'erro', 'mensagem' => 'Dispositivo não cadastrado ou inativo.'], 404);
            }
            $dispositivoId = (int) $dispositivo['id'];

            // ---- 3) Atualiza nível de ração e "último ping" (é isto que define Online/Offline) ----
            $stmt = $pdo->prepare('UPDATE dispositivos SET nivel_racao = :nivel, ultimo_ping = :agora WHERE id = :id');
            $stmt->execute([':nivel' => $nivel, ':agora' => $agora, ':id' => $dispositivoId]);

            // ---- 4) O ESP32 avisou que terminou o comando anterior? Marca como concluído ----
            if ($concluidoId > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE historico SET status = 'concluido', concluido_em = :agora
                      WHERE id = :id AND dispositivo_id = :dispositivo AND status = 'enviado'"
                );
                $stmt->execute([':agora' => $agora, ':id' => $concluidoId, ':dispositivo' => $dispositivoId]);
            }

            // ---- 5) Existe "alimentar" pendente? (cada comedouro tem 1 pet, então a fila é do comedouro) ----
            $stmt = $pdo->prepare(
                "SELECT id, porcao_g FROM historico
                  WHERE dispositivo_id = :dispositivo AND status = 'pendente'
                  ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([':dispositivo' => $dispositivoId]);
            $comando = $stmt->fetch();

            if ($comando !== false) {
                // Marca como "enviado". O "AND status = 'pendente'" garante que, se duas requisições
                // chegarem juntas, só uma ganha o comando (rowCount = 1) e o motor não gira em dobro.
                $stmt = $pdo->prepare(
                    "UPDATE historico SET status = 'enviado', enviado_em = :agora
                      WHERE id = :id AND status = 'pendente'"
                );
                $stmt->execute([':agora' => $agora, ':id' => $comando['id']]);

                if ($stmt->rowCount() === 1) {
                    $this->responder([
                        'status'  => 'ok',
                        'comando' => 'alimentar',
                        'gramas'  => (int) $comando['porcao_g'],
                        'id'      => (int) $comando['id'],
                    ]);
                }
            }

            // ---- 6) Nada pendente ----
            $this->responder(['status' => 'ok', 'comando' => 'nenhum', 'gramas' => 0]);

        } catch (PDOException $e) { // PDOException antes de RuntimeException (é "filha" dela)
            error_log('[api/sincronizar] ' . $e->getMessage());
            // Para a apresentação final, troque por uma mensagem genérica.
            $this->responder(['status' => 'erro', 'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()], 500);
        } catch (RuntimeException $e) {
            error_log('[api/sincronizar] ' . $e->getMessage());
            $this->responder(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    /** Envia o JSON e encerra (o exit evita que algo mais seja impresso depois). */
    private function responder(array $dados, int $codigoHttp = 200): never
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
