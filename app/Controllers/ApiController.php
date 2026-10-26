<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Dispositivo;
use PDOException;
use RuntimeException;

/**
 * API chamada pelo ESP32 (não é uma tela: só responde JSON).
 *
 *   POST /api/sincronizar        (a cada 10 s)
 *
 * O ESP32 envia MEDIDAS CRUAS; quem faz as contas é o servidor:
 *   {
 *     "codigo_mac_esp":    "AA:BB:CC:DD:EE:FF",   obrigatório
 *     "distancia_cm":      12.4,                  ultrassônico: distância sensor -> ração (vira % aqui)
 *     "peso_tigela_g":     183.5,                 balança HX711: peso atual da tigela, em gramas
 *     "comando_concluido": 12                     opcional: id do comando que acabou de executar
 *   }
 *   Compatibilidade/testes: em vez de distancia_cm dá para enviar "nivel_racao": 0..100 (já em %).
 *   Precisa vir distancia_cm OU nivel_racao. peso_tigela_g é opcional (sem balança = sem consumo).
 *
 * O PHP responde:
 *   {"status":"ok","comando":"alimentar","gramas":50,"id":12,"nivel":62}   -> girar o motor (50 g)
 *   {"status":"ok","comando":"nenhum","gramas":0,"nivel":62}                -> nada a fazer
 *   {"status":"erro","mensagem":"..."}                                       -> veja a tabela abaixo
 *
 * Identificação: só pelo MAC (sem token, de propósito, por simplicidade).
 */
class ApiController
{
    /** Depois de concluída, a refeição continua "acompanhando" o peso da tigela por este tempo. */
    private const JANELA_CONSUMO_MIN = 60;

    // ==================================================================
    // POST /api/sincronizar
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO (código HTTP -> o que o ESP32 vê no Monitor Serial):
     *
     *  1) Corpo que não é JSON (ou vazio)                  -> 400 "Corpo inválido: envie um JSON."
     *  2) codigo_mac_esp ausente/fora de AA:BB:CC:DD:EE:FF -> 422
     *  3) Campo numérico inválido                          -> 422 "Campo X inválido (use um número de A a B)."
     *       distancia_cm: 0 a 400 | peso_tigela_g: -100 a 50000 | nivel_racao: 0 a 100.
     *       Se o ultrassônico não leu (pulseIn devolveu 0), NÃO envie distancia_cm: omita o campo.
     *  4) Nem distancia_cm nem nivel_racao                 -> 422
     *  5) MAC não cadastrado (ou ativo = 0)                -> 404 "Dispositivo não cadastrado ou inativo."
     *  6) MySQL desligado ou erro de SQL                   -> 500
     *       "Unknown column 'distancia_cm'/'liberado_g'"   -> falta rodar a migração 09.
     *       "Unknown column 'codigo_mac_esp'"              -> falta rodar a migração 07.
     *  7) Tudo certo -> 200 com o comando (ou "nenhum").
     *
     * COMO O CONSUMO É CALCULADO (resumo):
     *  - Ao ENTREGAR um comando, guarda peso_antes_g = peso atual da tigela.
     *  - Quando o ESP32 CONFIRMA (comando_concluido), guarda peso_depois_g e
     *    liberado_g = peso_depois - peso_antes (o que de fato caiu na tigela).
     *  - Nas sincronizações seguintes (por 60 min) o consumido_g da ÚLTIMA refeição sobe conforme o
     *    peso cai: consumido = peso_depois - peso_atual (nunca maior que o liberado, nunca diminui).
     *
     * Erros que NÃO chegam aqui (o ESP32 nem recebe resposta do PHP):
     *  - HTTPClient -1 (connection refused): IP errado, PHP iniciado só em localhost ou firewall.
     *  - HTTPClient -11 (timeout), 404 {"erro":"Rota nao encontrada."} (URL errada), 405 (usou GET).
     */
    public function sincronizar(): void
    {
        // ---- 1) Lê e valida o que o ESP32 mandou ----
        $dados = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($dados)) {
            $this->responder(['status' => 'erro', 'mensagem' => 'Corpo inválido: envie um JSON.'], 400);
        }

        $mac = strtoupper(trim((string) ($dados['codigo_mac_esp'] ?? '')));
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            $this->responder(['status' => 'erro', 'mensagem' => 'codigo_mac_esp inválido (use AA:BB:CC:DD:EE:FF).'], 422);
        }

        $limites = ['distancia_cm' => [0, 400], 'peso_tigela_g' => [-100, 50000], 'nivel_racao' => [0, 100]];
        foreach ($limites as $campo => [$min, $max]) {
            if (isset($dados[$campo]) && (!is_numeric($dados[$campo]) || $dados[$campo] < $min || $dados[$campo] > $max)) {
                $this->responder(['status' => 'erro', 'mensagem' => "Campo $campo inválido (use um número de $min a $max)."], 422);
            }
        }

        $distancia   = isset($dados['distancia_cm']) ? (float) $dados['distancia_cm'] : null;
        $peso        = isset($dados['peso_tigela_g']) ? (float) $dados['peso_tigela_g'] : null;
        $nivelDireto = isset($dados['nivel_racao']) ? (int) round((float) $dados['nivel_racao']) : null;
        $concluidoId = (int) ($dados['comando_concluido'] ?? 0);

        if ($distancia === null && $nivelDireto === null) {
            $this->responder(['status' => 'erro', 'mensagem' => 'Envie distancia_cm (ultrassônico) ou nivel_racao (0 a 100).'], 422);
        }

        try {
            $pdo   = Database::getConnection(); // pode lançar RuntimeException (MySQL desligado)
            $agora = date('Y-m-d H:i:s');       // hora do PHP (fuso definido no index.php)

            // ---- 2) Quem é este comedouro? (e como ele está calibrado) ----
            $stmt = $pdo->prepare(
                'SELECT id, reservatorio_cheio_cm, reservatorio_vazio_cm
                   FROM dispositivos WHERE codigo_mac_esp = :mac AND ativo = 1 LIMIT 1'
            );
            $stmt->execute([':mac' => $mac]);
            $dispositivo = $stmt->fetch();

            if ($dispositivo === false) {
                $this->responder(['status' => 'erro', 'mensagem' => 'Dispositivo não cadastrado ou inativo.'], 404);
            }
            $dispositivoId = (int) $dispositivo['id'];

            // ---- 3) Distância -> porcentagem (a conversão é feita aqui, no servidor) ----
            $nivel = $distancia !== null
                ? Dispositivo::distanciaParaPercentual($distancia, (float) $dispositivo['reservatorio_cheio_cm'], (float) $dispositivo['reservatorio_vazio_cm'])
                : $nivelDireto;

            // ---- 4) Grava leituras e "último ping" (é isto que define Online/Offline) ----
            // COALESCE: se o ESP32 não mandou o peso/distância desta vez, mantém o valor anterior.
            $stmt = $pdo->prepare(
                'UPDATE dispositivos
                    SET nivel_racao = :nivel,
                        distancia_cm = COALESCE(:distancia, distancia_cm),
                        peso_tigela_g = COALESCE(:peso, peso_tigela_g),
                        ultimo_ping = :agora
                  WHERE id = :id'
            );
            $stmt->execute([':nivel' => $nivel, ':distancia' => $distancia, ':peso' => $peso, ':agora' => $agora, ':id' => $dispositivoId]);

            // ---- 5) O ESP32 avisou que terminou o comando anterior? Fecha a LIBERAÇÃO ----
            if ($concluidoId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT peso_antes_g FROM historico
                      WHERE id = :id AND dispositivo_id = :dispositivo AND status = 'enviado' LIMIT 1"
                );
                $stmt->execute([':id' => $concluidoId, ':dispositivo' => $dispositivoId]);
                $comandoEnviado = $stmt->fetch();

                if ($comandoEnviado !== false) {
                    $antes    = $comandoEnviado['peso_antes_g'] !== null ? (float) $comandoEnviado['peso_antes_g'] : null;
                    // liberado = o que a balança viu CAIR na tigela; sem balança fica NULL
                    $liberado = ($antes !== null && $peso !== null) ? max(0.0, $peso - $antes) : null;

                    $stmt = $pdo->prepare(
                        "UPDATE historico
                            SET status = 'concluido', concluido_em = :agora,
                                peso_depois_g = :depois, liberado_g = :liberado, consumido_g = :consumido
                          WHERE id = :id AND status = 'enviado'"
                    );
                    $stmt->execute([
                        ':agora' => $agora, ':depois' => $peso, ':liberado' => $liberado,
                        ':consumido' => $liberado !== null ? 0 : null, ':id' => $concluidoId,
                    ]);
                }
            }

            // ---- 6) Acompanha o CONSUMO da última refeição concluída (queda do peso na tigela) ----
            // Só a última refeição (ORDER BY ... LIMIT 1) e só dentro da janela de 60 min.
            // GREATEST: o consumo nunca diminui; LEAST: nunca passa do que foi liberado.
            if ($peso !== null) {
                $janela = date('Y-m-d H:i:s', time() - self::JANELA_CONSUMO_MIN * 60);
                $stmt = $pdo->prepare(
                    "UPDATE historico
                        SET consumido_g = GREATEST(COALESCE(consumido_g, 0), LEAST(liberado_g, peso_depois_g - :peso))
                      WHERE dispositivo_id = :dispositivo AND status = 'concluido'
                        AND liberado_g IS NOT NULL AND peso_depois_g IS NOT NULL
                        AND concluido_em >= :janela
                      ORDER BY concluido_em DESC LIMIT 1"
                );
                $stmt->execute([':peso' => $peso, ':dispositivo' => $dispositivoId, ':janela' => $janela]);
            }

            // ---- 7) Existe "alimentar" pendente? (cada comedouro tem 1 pet, então a fila é do comedouro) ----
            $stmt = $pdo->prepare(
                "SELECT id, porcao_g FROM historico
                  WHERE dispositivo_id = :dispositivo AND status = 'pendente'
                  ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([':dispositivo' => $dispositivoId]);
            $comando = $stmt->fetch();

            if ($comando !== false) {
                // Marca como "enviado" e guarda o peso da tigela ANTES de liberar. O "AND status = 'pendente'"
                // garante que, se duas requisições chegarem juntas, só uma ganha o comando.
                $stmt = $pdo->prepare(
                    "UPDATE historico SET status = 'enviado', enviado_em = :agora, peso_antes_g = :antes
                      WHERE id = :id AND status = 'pendente'"
                );
                $stmt->execute([':agora' => $agora, ':antes' => $peso, ':id' => $comando['id']]);

                if ($stmt->rowCount() === 1) {
                    $this->responder([
                        'status'  => 'ok',
                        'comando' => 'alimentar',
                        'gramas'  => (int) $comando['porcao_g'],
                        'id'      => (int) $comando['id'],
                        'nivel'   => $nivel,
                    ]);
                }
            }

            // ---- 8) Nada pendente ----
            $this->responder(['status' => 'ok', 'comando' => 'nenhum', 'gramas' => 0, 'nivel' => $nivel]);

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
