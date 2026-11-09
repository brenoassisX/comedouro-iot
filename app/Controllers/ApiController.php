<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Dispositivo;
use App\Models\Notificacao;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * API chamada pelo ESP32 (não é uma tela: só responde JSON).
 *
 *   POST /api/sincronizar        (a cada 10 s)
 *
 * O ESP32 envia MEDIDAS CRUAS; quem decide e calcula é o servidor:
 *   {
 *     "codigo_mac_esp":    "AA:BB:CC:DD:EE:FF",   obrigatório
 *     "distancia_cm":      12.4,                  ultrassônico: distância sensor -> ração (vira % aqui)
 *     "peso_tigela_g":     183.5,                 balança HX711: peso atual da tigela, em gramas
 *     "comando_concluido": 12                     opcional: id do comando que acabou de executar
 *   }
 *   Compatibilidade/testes: em vez de distancia_cm dá para enviar "nivel_racao": 0..100 (já em %).
 *   Todos os sensores são opcionais: sem distancia_cm/nivel_racao (sensor falhou) o nível anterior é mantido;
 *   sem peso_tigela_g (sem balança) libera a quantidade inteira.
 *
 * O PHP responde:
 *   {"status":"ok","comando":"alimentar","gramas":80,"alvo":90,"id":12,"nivel":62}  -> girar o motor (80 g)
 *   {"status":"ok","comando":"nenhum","gramas":0,"nivel":62}                          -> nada a fazer
 *   {"status":"ok","comando":"nenhum","gramas":0,"motivo":"tigela_cheia","alvo":90}   -> pedido dispensado
 *   {"status":"erro","mensagem":"..."}                                                 -> veja a tabela abaixo
 *
 * REGRA "COMPLETAR A PORÇÃO" (evita desperdício): a quantidade pedida (alvo, ex.: 90 g) é o quanto deve
 * HAVER na tigela. O servidor compara com o peso atual que o ESP32 mandou:
 *     tigela vazia (peso <= MARGEM_ERRO_G)            -> libera o alvo inteiro (90 g)
 *     tigela com 10 g                                 -> libera a diferença (80 g)
 *     faltam só MARGEM_ERRO_G ou menos (já está cheia) -> não libera (pedido 'dispensado')
 *     sem peso (ESP32 sem balança)                    -> libera o alvo inteiro
 * Vale para "Liberar agora" e para os horários programados.
 *
 * Identificação: só pelo MAC (sem token, de propósito, por simplicidade).
 */
class ApiController
{
    /** Depois de concluída, a refeição continua "acompanhando" o peso da tigela por este tempo. */
    private const JANELA_CONSUMO_MIN = 60;
    /** Um horário programado ainda dispara até 30 min depois do previsto (ex.: ESP32 voltou do offline). */
    private const JANELA_AGENDAMENTO_MIN = 30;
    /** Aviso "Próxima refeição" sai com esta antecedência. */
    private const AVISO_ANTECEDENCIA_MIN = 15;
    /** Margem de erro da balança: abaixo disso a tigela conta como vazia / já completa. */
    private const MARGEM_ERRO_G = 5.0;
    /** Nível (%) a partir do qual avisamos "ração baixa". */
    private const NIVEL_BAIXO = 20;
    /** Alerta de consumo: sobrou pelo menos 50% do que havia na tigela (e no mínimo 20 g). */
    private const SOBRA_ALERTA = 0.5;
    private const SOBRA_MINIMA_G = 20.0;

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
     *  4) (removido) Sem distancia_cm nem nivel_racao agora é aceito: mantém o nível anterior
     *  5) MAC não cadastrado (ou ativo = 0)                -> 404 "Dispositivo não cadastrado ou inativo."
     *  6) MySQL desligado ou erro de SQL                   -> 500
     *       "Unknown column 'enviado_g'/'offline_avisado'" ou "Table '...notificacoes' doesn't exist"
     *                                                      -> falta rodar a migração 10.
     *       "Unknown column 'distancia_cm'/'liberado_g'"   -> falta rodar a migração 09.
     *       "Unknown column 'codigo_mac_esp'"              -> falta rodar a migração 07.
     *  7) Tudo certo -> 200 com o comando (ou "nenhum").
     *
     * Falhas ao criar NOTIFICAÇÕES nunca derrubam a sincronização: o erro vai só para o log.
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

        // Sem distancia_cm nem nivel_racao (ex.: o ultrassônico não leu) a sincronização continua valendo:
        // o ping, o peso e os comandos seguem normalmente e o nível anterior é mantido.

        try {
            $pdo       = Database::getConnection(); // pode lançar RuntimeException (MySQL desligado)
            $agora     = date('Y-m-d H:i:s');       // hora do PHP (fuso definido no index.php)
            $hoje      = date('Y-m-d');
            $horaAtual = date('H:i:s');

            // ---- 2) Quem é este comedouro? (calibração, pet e nível anterior) ----
            $stmt = $pdo->prepare(
                'SELECT d.id, d.usuario_id, d.nome, d.pet_id, d.nivel_racao AS nivel_anterior,
                        d.reservatorio_cheio_cm, d.reservatorio_vazio_cm, p.nome AS pet_nome
                   FROM dispositivos d
                   LEFT JOIN pets p ON p.id = d.pet_id
                  WHERE d.codigo_mac_esp = :mac AND d.ativo = 1 LIMIT 1'
            );
            $stmt->execute([':mac' => $mac]);
            $dispositivo = $stmt->fetch();

            if ($dispositivo === false) {
                $this->responder(['status' => 'erro', 'mensagem' => 'Dispositivo não cadastrado ou inativo.'], 404);
            }
            $dispositivoId = (int) $dispositivo['id'];
            $usuarioId     = (int) $dispositivo['usuario_id'];
            $petNome       = (string) ($dispositivo['pet_nome'] ?? 'seu pet');

            // ---- 3) Distância -> porcentagem (a conversão é feita aqui, no servidor) ----
            $nivel = $distancia !== null
                ? Dispositivo::distanciaParaPercentual($distancia, (float) $dispositivo['reservatorio_cheio_cm'], (float) $dispositivo['reservatorio_vazio_cm'])
                : $nivelDireto;

            // ---- 4) Grava leituras e "último ping" (define Online/Offline). Voltou? Zera o aviso de offline ----
            // COALESCE: se o ESP32 não mandou o peso/distância desta vez, mantém o valor anterior.
            $stmt = $pdo->prepare(
                'UPDATE dispositivos
                    SET nivel_racao = COALESCE(:nivel, nivel_racao),
                        distancia_cm = COALESCE(:distancia, distancia_cm),
                        peso_tigela_g = COALESCE(:peso, peso_tigela_g),
                        ultimo_ping = :agora, offline_avisado = 0
                  WHERE id = :id'
            );
            $stmt->execute([':nivel' => $nivel, ':distancia' => $distancia, ':peso' => $peso, ':agora' => $agora, ':id' => $dispositivoId]);

            // ---- 4b) Ração baixa: avisa quando o nível CRUZA o limite (no máximo 1 vez por dia) ----
            $anterior = $dispositivo['nivel_anterior'] !== null ? (int) $dispositivo['nivel_anterior'] : null;
            if ($nivel !== null && $nivel <= self::NIVEL_BAIXO && ($anterior === null || $anterior > self::NIVEL_BAIXO)) {
                $this->avisar($usuarioId, $dispositivoId, 'nivel', 'Ração baixa',
                    'O reservatório de ' . $dispositivo['nome'] . ' está com ' . $nivel . '% de ração.',
                    '/dispositivos?id=' . $dispositivoId, 'nivel:' . $dispositivoId . ':' . $hoje);
            }

            // ---- 5) O ESP32 avisou que terminou o comando anterior? Fecha a LIBERAÇÃO ----
            if ($concluidoId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT peso_antes_g, enviado_g FROM historico
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
                        ':consumido' => $peso !== null ? 0 : null, ':id' => $concluidoId,
                    ]);

                    $gramasAviso = $liberado !== null ? (int) round($liberado) : (int) ($comandoEnviado['enviado_g'] ?? 0);
                    $this->avisar($usuarioId, $dispositivoId, 'alimentacao', 'Alimentação realizada',
                        'O comedouro liberou ' . $gramasAviso . ' g de ração para ' . $petNome . '.',
                        '/alimentacao?dispositivo=' . $dispositivoId, 'alimentacao:' . $concluidoId);
                }
            }

            // ---- 6) Acompanha o CONSUMO da última refeição concluída (queda do peso na tigela) ----
            // Só a última refeição e só dentro da janela de 60 min. GREATEST: o consumo nunca diminui;
            // LEAST: nunca passa do que havia na tigela depois de liberar.
            if ($peso !== null) {
                $stmt = $pdo->prepare(
                    "UPDATE historico
                        SET consumido_g = GREATEST(COALESCE(consumido_g, 0), LEAST(peso_depois_g, peso_depois_g - :peso))
                      WHERE dispositivo_id = :dispositivo AND status = 'concluido'
                        AND peso_depois_g IS NOT NULL AND concluido_em >= :janela
                      ORDER BY concluido_em DESC LIMIT 1"
                );
                $stmt->execute([
                    ':peso' => $peso, ':dispositivo' => $dispositivoId,
                    ':janela' => date('Y-m-d H:i:s', time() - self::JANELA_CONSUMO_MIN * 60),
                ]);
            }

            // ---- 7) Alerta de consumo: refeições cuja janela de 60 min já terminou e ainda não foram avaliadas ----
            $stmt = $pdo->prepare(
                "SELECT id, peso_depois_g, consumido_g FROM historico
                  WHERE dispositivo_id = :dispositivo AND status = 'concluido' AND consumo_avaliado = 0
                    AND peso_depois_g IS NOT NULL
                    AND concluido_em < :fim_janela AND concluido_em > :desde
                  LIMIT 5"
            );
            $stmt->execute([
                ':dispositivo' => $dispositivoId,
                ':fim_janela'  => date('Y-m-d H:i:s', time() - self::JANELA_CONSUMO_MIN * 60),
                ':desde'       => date('Y-m-d H:i:s', time() - 24 * 3600),
            ]);
            foreach ($stmt->fetchAll() as $refeicao) {
                $depois = (float) $refeicao['peso_depois_g'];
                $sobra  = max(0.0, $depois - (float) ($refeicao['consumido_g'] ?? 0));

                if ($depois >= self::SOBRA_MINIMA_G && $sobra / $depois >= self::SOBRA_ALERTA) {
                    $this->avisar($usuarioId, $dispositivoId, 'consumo', 'Alerta de consumo',
                        'Foi identificada uma quantidade significativa de ração restante (' . (int) round($sobra) . ' g). Verifique se ' . $petNome . ' está se alimentando normalmente.',
                        '/relatorios?pet=' . (int) $dispositivo['pet_id'], 'consumo:' . (int) $refeicao['id']);
                }
                $pdo->prepare('UPDATE historico SET consumo_avaliado = 1 WHERE id = :id')->execute([':id' => $refeicao['id']]);
            }

            // ---- 8) Horários programados que venceram viram pedidos 'pendente' (uma vez por dia cada) ----
            // Janela: do horário até 30 min depois (não atravessa a meia-noite). Os dias seguem a coluna dias_semana.
            $dias       = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
            $diaHoje    = $dias[(int) date('w')];
            $horaInicio = date('H:i:s', time() - self::JANELA_AGENDAMENTO_MIN * 60);
            if ($horaInicio > $horaAtual) {
                $horaInicio = '00:00:00';
            }

            $stmt = $pdo->prepare(
                "INSERT INTO historico (dispositivo_id, pet_id, agendamento_id, origem, porcao_g, status, criado_em)
                 SELECT a.dispositivo_id, :pet, a.id, 'agendado', a.porcao_g, 'pendente', :agora
                   FROM agendamentos a
                  WHERE a.dispositivo_id = :dispositivo AND a.ativo = 1
                    AND FIND_IN_SET(:dia, a.dias_semana) > 0
                    AND a.horario <= :hora_atual AND a.horario >= :hora_inicio
                    AND NOT EXISTS (SELECT 1 FROM historico h WHERE h.agendamento_id = a.id AND DATE(h.criado_em) = :hoje)"
            );
            $stmt->execute([
                ':pet' => $dispositivo['pet_id'], ':agora' => $agora, ':dispositivo' => $dispositivoId,
                ':dia' => $diaHoje, ':hora_atual' => $horaAtual, ':hora_inicio' => $horaInicio, ':hoje' => $hoje,
            ]);

            // ---- 9) Aviso "Próxima refeição" (15 min antes), uma vez por horário por dia ----
            $ate = date('H:i:s', time() + self::AVISO_ANTECEDENCIA_MIN * 60);
            if ($ate < $horaAtual) {
                $ate = '23:59:59';
            }
            $stmt = $pdo->prepare(
                'SELECT id, horario, porcao_g FROM agendamentos
                  WHERE dispositivo_id = :dispositivo AND ativo = 1 AND FIND_IN_SET(:dia, dias_semana) > 0
                    AND horario > :hora_atual AND horario <= :ate'
            );
            $stmt->execute([':dispositivo' => $dispositivoId, ':dia' => $diaHoje, ':hora_atual' => $horaAtual, ':ate' => $ate]);
            foreach ($stmt->fetchAll() as $proximo) {
                $this->avisar($usuarioId, $dispositivoId, 'proxima', 'Próxima refeição',
                    'Hoje às ' . substr((string) $proximo['horario'], 0, 5) . ': ' . (int) $proximo['porcao_g'] . ' g para ' . $petNome . '. Se já houver ração na tigela, só o que faltar será liberado.',
                    '/alimentacao?dispositivo=' . $dispositivoId, 'proxima:' . (int) $proximo['id'] . ':' . $hoje);
            }

            // ---- 10) Existe pedido pendente? Aplica a regra de COMPLETAR a porção ----
            $stmt = $pdo->prepare(
                "SELECT id, porcao_g FROM historico
                  WHERE dispositivo_id = :dispositivo AND status = 'pendente'
                  ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([':dispositivo' => $dispositivoId]);
            $comando = $stmt->fetch();

            if ($comando !== false) {
                $alvo   = (int) $comando['porcao_g'];
                $gramas = $this->gramasParaCompletar($alvo, $peso);

                if ($gramas > 0) {
                    // Marca como "enviado" e guarda o peso ANTES de liberar. O "AND status = 'pendente'"
                    // garante que, se duas requisições chegarem juntas, só uma ganha o comando.
                    $stmt = $pdo->prepare(
                        "UPDATE historico SET status = 'enviado', enviado_em = :agora, peso_antes_g = :antes, enviado_g = :gramas
                          WHERE id = :id AND status = 'pendente'"
                    );
                    $stmt->execute([':agora' => $agora, ':antes' => $peso, ':gramas' => $gramas, ':id' => $comando['id']]);

                    if ($stmt->rowCount() === 1) {
                        $this->responder([
                            'status' => 'ok', 'comando' => 'alimentar', 'gramas' => $gramas,
                            'alvo' => $alvo, 'id' => (int) $comando['id'], 'nivel' => $nivel ?? $anterior,
                        ]);
                    }
                } else {
                    // A tigela já tem (quase) tudo: não libera nada e registra o motivo.
                    $stmt = $pdo->prepare(
                        "UPDATE historico
                            SET status = 'dispensado', enviado_em = :agora, concluido_em = :agora2, peso_antes_g = :antes, enviado_g = 0
                          WHERE id = :id AND status = 'pendente'"
                    );
                    $stmt->execute([':agora' => $agora, ':agora2' => $agora, ':antes' => $peso, ':id' => $comando['id']]);

                    if ($stmt->rowCount() === 1) {
                        $this->avisar($usuarioId, $dispositivoId, 'alimentacao', 'Refeição dispensada',
                            'A tigela de ' . $petNome . ' já tinha ' . (int) round(max(0.0, (float) $peso)) . ' g (pedido: ' . $alvo . ' g). Nada foi liberado para evitar desperdício.',
                            '/alimentacao?dispositivo=' . $dispositivoId, 'dispensada:' . (int) $comando['id']);

                        $this->responder([
                            'status' => 'ok', 'comando' => 'nenhum', 'gramas' => 0,
                            'motivo' => 'tigela_cheia', 'alvo' => $alvo, 'nivel' => $nivel ?? $anterior,
                        ]);
                    }
                }
            }

            // ---- 11) Nada a fazer ----
            $this->responder(['status' => 'ok', 'comando' => 'nenhum', 'gramas' => 0, 'nivel' => $nivel ?? $anterior]);

        } catch (PDOException $e) { // PDOException antes de RuntimeException (é "filha" dela)
            error_log('[api/sincronizar] ' . $e->getMessage());
            // Para a apresentação final, troque por uma mensagem genérica.
            $this->responder(['status' => 'erro', 'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()], 500);
        } catch (RuntimeException $e) {
            error_log('[api/sincronizar] ' . $e->getMessage());
            $this->responder(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * Regra de completar a porção: quantos gramas mandar ao motor para a tigela chegar ao alvo?
     *   exemplos com alvo = 90 g e margem de 5 g:
     *     peso null (sem balança) -> 90      tigela com 0 g ou 4 g (vazia) -> 90
     *     tigela com 10 g         -> 80      tigela com 60 g              -> 30
     *     tigela com 86 g (faltam 4, dentro da margem) -> 0 (não libera)
     * Um alvo pequeno (ex.: 3 g) com tigela vazia ainda libera: a margem só vale para "já tem ração".
     */
    private function gramasParaCompletar(int $alvo, ?float $peso): int
    {
        if ($peso === null) {
            return $alvo;                                   // sem balança: não dá para saber, libera tudo
        }

        $atual = max(0.0, $peso);                           // leituras negativas = ruído da balança
        if ($atual <= self::MARGEM_ERRO_G) {
            return $alvo;                                   // tigela praticamente vazia
        }

        $faltante = $alvo - $atual;
        return $faltante <= self::MARGEM_ERRO_G ? 0 : (int) round($faltante);
    }

    /** Cria uma notificação sem nunca derrubar a sincronização (erros vão só para o log). */
    private function avisar(int $usuarioId, int $dispositivoId, string $tipo, string $titulo, string $mensagem, string $url, string $chave): void
    {
        try {
            Notificacao::criar($usuarioId, $tipo, $titulo, $mensagem, $url, $chave, null, $dispositivoId);
        } catch (Throwable $e) {
            error_log('[api/notificacao] ' . $e->getMessage());
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
