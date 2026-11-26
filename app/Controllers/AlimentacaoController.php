<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Agendamento;
use App\Models\Dispositivo;
use App\Models\Historico;
use App\Models\Porcao;
use PDOException;
use RuntimeException;

/**
 * Alimentação: horários programados (listar, adicionar, editar, excluir) e "Liberar agora".
 *
 * Rotas (public/index.php):
 *   GET  /alimentacao                 -> index()      (?dispositivo= escolhe o comedouro)
 *   GET  /alimentacao/novo?dispositivo= -> novo()
 *   POST /alimentacao                 -> salvar()
 *   GET  /alimentacao/editar?id=      -> editar()
 *   POST /alimentacao/atualizar       -> atualizar()
 *   POST /alimentacao/excluir         -> excluir()
 *   POST /alimentacao/liberar         -> liberar()    (alimentação manual: porção baixa/média/alta)
 *   POST /alimentacao/porcoes         -> salvarPorcoes() (editar nomes e gramas das porções)
 *
 * IMPORTANTE: os horários ficam salvos no banco, mas só viram "pedidos" para o ESP32 quando a
 * parte do hardware (api/sincronizar) for ligada a eles. Já o "Liberar agora" cria o pedido na hora.
 *
 * ERROS DE BANCO: "Table '...agendamentos' doesn't exist" -> rode o schema.sql;
 * "Erro ao conectar ao banco de dados." -> MySQL desligado (veja o UsuarioController).
 */
class AlimentacaoController
{
    private const MENSAGENS = [
        'criado'           => 'Horário adicionado.',
        'atualizado'       => 'Horário atualizado.',
        'excluido'         => 'Horário excluído.',
        'liberado'         => 'Refeição liberada! O comedouro serve na próxima sincronização (até 10 segundos).',
        'liberado_offline' => 'Pedido registrado, mas o comedouro está offline. Ele serve assim que voltar a se conectar.',
        'ja_pendente'      => 'Já existe uma liberação aguardando o comedouro. Aguarde ele servir.',
        'quantidade_invalida' => 'Escolha uma porção válida.',
        'porcoes_salvas'   => 'Porções atualizadas.',
        'porcoes_invalidas' => 'Confira os nomes (até 30 letras) e as quantidades (1 a 500 g).',
    ];

    // ==================================================================
    // GET /alimentacao
    // ==================================================================
    public function index(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $erro         = '';
        $dispositivos = [];
        $atual        = null;
        $horarios     = [];
        $ultima       = null;
        $porcoes      = [];
        $sucesso      = self::MENSAGENS[(string) ($_GET['msg'] ?? '')] ?? '';

        try {
            $dispositivos = Dispositivo::listarPorUsuario((int) $_SESSION['usuario_id']);

            $pedido = (int) ($_GET['dispositivo'] ?? 0);
            $atual  = $dispositivos[0] ?? null;
            foreach ($dispositivos as $d) {
                if ((int) $d['id'] === $pedido) {
                    $atual = $d;
                }
            }

            if ($atual !== null) {
                $horarios = Agendamento::listarPorDispositivo((int) $atual['id']);
                $ultima   = Historico::ultima((int) $atual['id']);
            }
            $porcoes = Porcao::doUsuario((int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[alimentacao] ' . $e->getMessage());
            $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, mensagem genérica
        } catch (RuntimeException $e) {
            error_log('[alimentacao] ' . $e->getMessage());
            $erro = $e->getMessage();
        }

        require BASE_PATH . '/app/Views/alimentacao.php';
    }

    // ==================================================================
    // GET /alimentacao/novo?dispositivo=1
    // ==================================================================
    public function novo(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $dispositivo = $this->carregarDispositivoOu404((int) ($_GET['dispositivo'] ?? 0));

        $modo = 'novo';
        $erro = '';
        $id   = null;
        $old  = ['horario' => '', 'porcao' => 'media'];
        $porcoes = $this->porcoesOuVazio();
        require BASE_PATH . '/app/Views/alimentacao_form.php';
    }

    // ==================================================================
    // POST /alimentacao
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *  1) Horário fora do formato HH:MM, ou quantidade fora de 1 a 2000 g -> mensagem e o formulário reaparece.
     *  2) Já existe um horário igual neste comedouro -> "Já existe um horário às 08:00".
     *  3) Comedouro que não é do usuário -> 404.
     *  4) Tudo certo -> grava e volta para a lista.
     */
    public function salvar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $dispositivo = $this->carregarDispositivoOu404((int) ($_POST['dispositivo_id'] ?? 0));
        $dispositivoId = (int) $dispositivo['id'];

        [$erro, $horario, $gramas, $old] = $this->lerFormulario();

        if ($erro === '') {
            try {
                if (Agendamento::existeHorario($dispositivoId, $horario)) {
                    $erro = 'Já existe um horário às ' . $horario . ' neste comedouro.';
                } else {
                    Agendamento::criar($dispositivoId, $horario, $gramas);
                    header('Location: /alimentacao?dispositivo=' . $dispositivoId . '&msg=criado');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[alimentacao/salvar] ' . $e->getMessage());
                $erro = 'Erro no banco de dados: ' . $e->getMessage();
            } catch (RuntimeException $e) {
                error_log('[alimentacao/salvar] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        $modo = 'novo';
        $id   = null;
        $porcoes = $this->porcoesOuVazio();
        require BASE_PATH . '/app/Views/alimentacao_form.php';
    }

    // ==================================================================
    // GET /alimentacao/editar?id=5
    // ==================================================================
    public function editar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $ag          = $this->carregarHorarioOu404((int) ($_GET['id'] ?? 0));
        $dispositivo = $this->carregarDispositivoOu404((int) $ag['dispositivo_id']);

        $modo = 'editar';
        $erro = '';
        $id   = (int) $ag['id'];
        $porcoes = $this->porcoesOuVazio();
        $chaveAtual = 'media';
        foreach ($porcoes as $chave => $p) {
            if ($p['gramas'] === (int) $ag['porcao_g']) {
                $chaveAtual = $chave;
            }
        }
        $old  = ['horario' => substr((string) $ag['horario'], 0, 5), 'porcao' => $chaveAtual];
        require BASE_PATH . '/app/Views/alimentacao_form.php';
    }

    // ==================================================================
    // POST /alimentacao/atualizar
    // ==================================================================
    public function atualizar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $ag          = $this->carregarHorarioOu404($id);
        $dispositivo = $this->carregarDispositivoOu404((int) $ag['dispositivo_id']);
        $dispositivoId = (int) $dispositivo['id'];

        [$erro, $horario, $gramas, $old] = $this->lerFormulario();

        if ($erro === '') {
            try {
                if (Agendamento::existeHorario($dispositivoId, $horario, $id)) {
                    $erro = 'Já existe um horário às ' . $horario . ' neste comedouro.';
                } else {
                    Agendamento::atualizar($id, $horario, $gramas);
                    header('Location: /alimentacao?dispositivo=' . $dispositivoId . '&msg=atualizado');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[alimentacao/atualizar] ' . $e->getMessage());
                $erro = 'Erro no banco de dados: ' . $e->getMessage();
            } catch (RuntimeException $e) {
                error_log('[alimentacao/atualizar] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        $modo = 'editar';
        $porcoes = $this->porcoesOuVazio();
        require BASE_PATH . '/app/Views/alimentacao_form.php';
    }

    // ==================================================================
    // POST /alimentacao/excluir
    // ==================================================================
    public function excluir(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $ag = $this->carregarHorarioOu404((int) ($_POST['id'] ?? 0));

        try {
            Agendamento::excluir((int) $ag['id']);
        } catch (PDOException $e) {
            error_log('[alimentacao/excluir] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        } catch (RuntimeException $e) {
            error_log('[alimentacao/excluir] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }

        header('Location: /alimentacao?dispositivo=' . (int) $ag['dispositivo_id'] . '&msg=excluido');
        exit;
    }

    // ==================================================================
    // POST /alimentacao/liberar  ->  "Liberar agora" (alimentação manual)
    // ==================================================================
    /**
     * O QUE ACONTECE:
     *  - Cria um pedido 'pendente' (origem manual) em historico com a QUANTIDADE digitada na tela
     *    (1 a 2000 g). Campo em branco = porção padrão do pet (ou 50 g se o comedouro não tiver pet). O ESP32 pega esse pedido na próxima chamada a /api/sincronizar.
     *  - Se já existe um pedido pendente, não cria outro (evita servir em dobro por cliques repetidos).
     *  - Se o comedouro está offline, o pedido é criado mesmo assim e a tela avisa.
     *  - Depois redireciona (PRG): apertar F5 na tela NÃO libera outra refeição.
     */
    public function liberar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $dispositivo   = $this->carregarDispositivoOu404((int) ($_POST['dispositivo_id'] ?? 0));
        $dispositivoId = (int) $dispositivo['id'];
        $msg           = 'liberado';

        // Tamanho escolhido na tela (baixa / media / alta) -> gramas definidas pelo usuário
        $chave  = (string) ($_POST['porcao'] ?? '');
        $valida = in_array($chave, Porcao::CHAVES, true);
        $gramas = 0;
        if ($valida) {
            try {
                $gramas = Porcao::doUsuario((int) $_SESSION['usuario_id'])[$chave]['gramas'];
            } catch (PDOException $e) {
                error_log('[alimentacao/liberar] ' . $e->getMessage());
                http_response_code(500);
                echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                return;
            }
        }

        try {
            if (!$valida) {
                $msg = 'quantidade_invalida';
            } elseif (Historico::temPedidoPendente($dispositivoId)) {
                $msg = 'ja_pendente';
            } else {
                Historico::criarPedidoManual(
                    $dispositivoId,
                    $dispositivo['pet_id'] !== null ? (int) $dispositivo['pet_id'] : null,
                    $gramas
                );
                $msg = Dispositivo::status($dispositivo)['online'] ? 'liberado' : 'liberado_offline';
            }
        } catch (PDOException $e) {
            error_log('[alimentacao/liberar] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        } catch (RuntimeException $e) {
            error_log('[alimentacao/liberar] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }

        header('Location: /alimentacao?dispositivo=' . $dispositivoId . '&msg=' . $msg);
        exit;
    }

    // ==================================================================
    // Funções de apoio
    // ==================================================================

    /** Devolve [$erro, $horario 'HH:MM', $gramas, $old]. O tamanho (baixa/media/alta) vira gramas. */
    private function lerFormulario(): array
    {
        $horario = trim((string) ($_POST['horario'] ?? ''));
        $chave   = (string) ($_POST['porcao'] ?? '');
        $gramas  = 0;
        $erro    = '';

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horario)) {
            $erro = 'Informe um horário válido (HH:MM).';
        } elseif (!in_array($chave, Porcao::CHAVES, true)) {
            $erro = 'Escolha o tamanho da porção.';
        } else {
            try {
                $gramas = Porcao::doUsuario((int) $_SESSION['usuario_id'])[$chave]['gramas'];
            } catch (PDOException $e) {
                error_log('[alimentacao] ' . $e->getMessage());
                $erro = 'Erro no banco de dados: ' . $e->getMessage();
            }
        }

        return [$erro, $horario, $gramas, ['horario' => $horario, 'porcao' => $chave]];
    }

    /** Porções do usuário (para redesenhar o formulário). Em caso de erro de banco devolve os padrões. */
    private function porcoesOuVazio(): array
    {
        try {
            return Porcao::doUsuario((int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[alimentacao] ' . $e->getMessage());
            return Porcao::PADROES;
        }
    }

    // ==================================================================
    // POST /alimentacao/porcoes  ->  o usuário troca nomes e gramas de Baixa/Média/Alta
    // ==================================================================
    /**
     * O QUE ACONTECE:
     *  - Nome: 1 a 30 caracteres. Quantidade: 1 a 500 g. Qualquer erro -> nada é salvo e volta a mensagem.
     *  - Horários que já existem guardam os gramas de quando foram criados (não mudam sozinhos).
     */
    public function salvarPorcoes(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $dispositivoId = (int) ($_POST['dispositivo_id'] ?? 0);
        $dados = [];
        $ok    = true;
        foreach (Porcao::CHAVES as $chave) {
            $nome = trim((string) ($_POST['nome_' . $chave] ?? ''));
            $g    = trim((string) ($_POST['gramas_' . $chave] ?? ''));
            if ($nome === '' || mb_strlen($nome) > 30 || !ctype_digit($g) || (int) $g < 1 || (int) $g > 500) {
                $ok = false;
                break;
            }
            $dados[$chave] = [$nome, (int) $g];
        }

        $msg = 'porcoes_invalidas';
        if ($ok) {
            try {
                Porcao::doUsuario((int) $_SESSION['usuario_id']);   // garante que as 3 linhas existem
                foreach ($dados as $chave => [$nome, $g]) {
                    Porcao::salvar((int) $_SESSION['usuario_id'], $chave, $nome, $g);
                }
                $msg = 'porcoes_salvas';
            } catch (PDOException $e) {
                error_log('[alimentacao/porcoes] ' . $e->getMessage());
                http_response_code(500);
                echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                return;
            }
        }

        header('Location: /alimentacao?dispositivo=' . $dispositivoId . '&msg=' . $msg);
        exit;
    }

    private function carregarDispositivoOu404(int $id): array
    {
        try {
            $d = Dispositivo::buscar($id, (int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[alimentacao] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        } catch (RuntimeException $e) {
            error_log('[alimentacao] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        }

        if ($d === null) {
            http_response_code(404);
            echo 'Comedouro não encontrado.';
            exit;
        }
        return $d;
    }

    private function carregarHorarioOu404(int $id): array
    {
        try {
            $ag = Agendamento::buscar($id, (int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[alimentacao] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        } catch (RuntimeException $e) {
            error_log('[alimentacao] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        }

        if ($ag === null) {
            http_response_code(404);
            echo 'Horário não encontrado.';
            exit;
        }
        return $ag;
    }
}
