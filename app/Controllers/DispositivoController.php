<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dispositivo;
use PDOException;
use RuntimeException;

/**
 * Dispositivos (comedouros): visualizar, cadastrar, editar e excluir.
 *
 * Rotas (public/index.php):
 *   GET  /dispositivos            -> index()      tela Dispositivos (?id= escolhe o comedouro)
 *   GET  /dispositivos/novo       -> novo()       formulário de cadastro
 *   POST /dispositivos            -> salvar()
 *   GET  /dispositivos/editar?id= -> editar()
 *   POST /dispositivos/atualizar  -> atualizar()
 *   POST /dispositivos/excluir    -> excluir()
 *
 * ERROS DE BANCO (valem para todos os métodos):
 *   "Unknown column 'codigo_mac_esp'" (42S22) -> falta rodar a migração 07.
 *   "Unknown column 'foto'" (42S22)           -> falta rodar a migração 05 (tabela pets).
 *   "Erro ao conectar ao banco de dados."     -> MySQL desligado (veja o UsuarioController).
 */
class DispositivoController
{
    private const MENSAGENS = [
        'criado'     => 'Comedouro cadastrado com sucesso.',
        'atualizado' => 'Comedouro atualizado.',
        'excluido'   => 'Comedouro excluído.',
    ];

    // ==================================================================
    // GET /dispositivos
    // ==================================================================
    /**
     * POSSÍVEIS SITUAÇÕES:
     *  - Nenhum comedouro cadastrado -> a tela mostra um aviso com o botão de cadastro.
     *  - Aparece "Offline" mesmo com o ESP32 ligado -> o ESP32 não está chamando /api/sincronizar
     *    (ou o MAC dele não é igual ao cadastrado aqui). Online = ping nos últimos 30 segundos.
     *  - A página recarrega sozinha a cada 15 s para atualizar nível e status.
     */
    public function index(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $erro         = '';
        $dispositivos = [];
        $sucesso      = self::MENSAGENS[(string) ($_GET['msg'] ?? '')] ?? '';

        try {
            $dispositivos = Dispositivo::listarPorUsuario((int) $_SESSION['usuario_id']);
        } catch (PDOException $e) { // PDOException antes de RuntimeException (é "filha" dela)
            error_log('[dispositivos] ' . $e->getMessage());
            $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, mensagem genérica
        } catch (RuntimeException $e) {
            error_log('[dispositivos] ' . $e->getMessage());
            $erro = $e->getMessage();
        }

        // Comedouro mostrado: o do ?id= ou, se não vier, o primeiro
        $pedido = (int) ($_GET['id'] ?? 0);
        $atual  = $dispositivos[0] ?? null;
        foreach ($dispositivos as $d) {
            if ((int) $d['id'] === $pedido) {
                $atual = $d;
            }
        }
        $status = $atual !== null ? Dispositivo::status($atual) : null;

        require BASE_PATH . '/app/Views/dispositivos.php';
    }

    // ==================================================================
    // GET /dispositivos/novo
    // ==================================================================
    public function novo(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $modo = 'novo';
        $erro = '';
        $old  = [];
        $id   = null;
        $pets = $this->carregarPets(0, $erro);
        require BASE_PATH . '/app/Views/dispositivo_form.php';
    }

    // ==================================================================
    // POST /dispositivos
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *  1) Nome vazio ou MAC fora do formato AA:BB:CC:DD:EE:FF -> mensagem e o formulário reaparece.
     *  2) MAC já cadastrado (UNIQUE uq_dispositivos_mac, erro 1062) -> "Este código MAC já está cadastrado".
     *  3) Pet já ligado a outro comedouro (UNIQUE uq_dispositivos_pet, erro 1062 - regra 1 pet = 1 comedouro)
     *     -> "Este pet já está ligado a outro comedouro".
     *  4) Pet que não é do usuário -> "Escolha um pet válido".
     *  5) Tudo certo -> grava e volta para /dispositivos?id=<novo>.
     */
    public function salvar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $usuarioId = (int) $_SESSION['usuario_id'];
        [$erro, $dados, $old] = $this->lerFormulario();
        $pets = $this->carregarPets(0, $erro);

        if ($erro === '' && !$this->petPermitido($dados['pet_id'], $pets)) {
            $erro = 'Escolha um pet válido que ainda não tenha comedouro.';
        }

        if ($erro === '') {
            try {
                $novoId = Dispositivo::criar($usuarioId, $dados);
                header('Location: /dispositivos?id=' . $novoId . '&msg=criado');
                exit;
            } catch (PDOException $e) {
                $erro = $this->mensagemDeErro($e);
            } catch (RuntimeException $e) {
                error_log('[dispositivos/salvar] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        $modo = 'novo';
        $id   = null;
        require BASE_PATH . '/app/Views/dispositivo_form.php';
    }

    // ==================================================================
    // GET /dispositivos/editar?id=1
    // ==================================================================
    public function editar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $d = $this->carregarOu404((int) ($_GET['id'] ?? 0));

        $modo = 'editar';
        $erro = '';
        $id   = (int) $d['id'];
        $old  = [
            'nome' => $d['nome'], 'codigo_mac_esp' => $d['codigo_mac_esp'], 'pet_id' => (string) ($d['pet_id'] ?? ''),
            'cheio_cm' => rtrim(rtrim((string) $d['reservatorio_cheio_cm'], '0'), '.'),
            'vazio_cm' => rtrim(rtrim((string) $d['reservatorio_vazio_cm'], '0'), '.'),
            'g90_vazio' => rtrim(rtrim((string) $d['servo_g90_vazio'], '0'), '.'),
            'g90_cheio' => rtrim(rtrim((string) $d['servo_g90_cheio'], '0'), '.'),
            'abertura_ms' => (string) (int) $d['servo_abertura_ms'],
        ];
        $pets = $this->carregarPets($id, $erro);
        require BASE_PATH . '/app/Views/dispositivo_form.php';
    }

    // ==================================================================
    // POST /dispositivos/atualizar
    // ==================================================================
    /** Mesmas situações do salvar(); id inexistente/de outro usuário -> 404 "Comedouro não encontrado." */
    public function atualizar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $this->carregarOu404($id);

        [$erro, $dados, $old] = $this->lerFormulario();
        $pets = $this->carregarPets($id, $erro);

        if ($erro === '' && !$this->petPermitido($dados['pet_id'], $pets)) {
            $erro = 'Escolha um pet válido que ainda não tenha comedouro.';
        }

        if ($erro === '') {
            try {
                Dispositivo::atualizar($id, (int) $_SESSION['usuario_id'], $dados);
                header('Location: /dispositivos?id=' . $id . '&msg=atualizado');
                exit;
            } catch (PDOException $e) {
                $erro = $this->mensagemDeErro($e);
            } catch (RuntimeException $e) {
                error_log('[dispositivos/atualizar] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        $modo = 'editar';
        require BASE_PATH . '/app/Views/dispositivo_form.php';
    }

    // ==================================================================
    // POST /dispositivos/excluir
    // ==================================================================
    /**
     * ATENÇÃO: os horários (agendamentos) e todo o histórico de alimentação deste comedouro
     * são apagados junto (FK com ON DELETE CASCADE). O pet NÃO é apagado.
     */
    public function excluir(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $this->carregarOu404($id);

        try {
            Dispositivo::excluir($id, (int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[dispositivos/excluir] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        } catch (RuntimeException $e) {
            error_log('[dispositivos/excluir] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }

        header('Location: /dispositivos?msg=excluido');
        exit;
    }

    // ==================================================================
    // Funções de apoio
    // ==================================================================

    /** Lê e valida o formulário. Devolve [$erro, $dados (para o banco), $old (para reabrir o formulário)]. */
    private function lerFormulario(): array
    {
        $nome  = trim((string) ($_POST['nome'] ?? ''));
        $mac   = strtoupper(str_replace('-', ':', trim((string) ($_POST['codigo_mac_esp'] ?? ''))));
        $petId = (int) ($_POST['pet_id'] ?? 0);
        $cheio = str_replace(',', '.', trim((string) ($_POST['cheio_cm'] ?? '5')));
        $vazio = str_replace(',', '.', trim((string) ($_POST['vazio_cm'] ?? '25')));
        // Calibração da comporta (servo): gramas por abertura de 90° e tempo aberta
        $g90v     = str_replace(',', '.', trim((string) ($_POST['g90_vazio'] ?? '60')));
        $g90c     = str_replace(',', '.', trim((string) ($_POST['g90_cheio'] ?? '60')));
        $abertura = trim((string) ($_POST['abertura_ms'] ?? '300'));
        $erro  = '';

        if ($nome === '' || strlen($nome) > 80) {
            $erro = 'Informe o nome do comedouro (até 80 caracteres).';
        } elseif (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            $erro = 'Informe o código MAC no formato AA:BB:CC:DD:EE:FF.';
        } elseif (!is_numeric($cheio) || !is_numeric($vazio) || (float) $cheio < 1 || (float) $vazio > 200 || (float) $vazio <= (float) $cheio) {
            // Calibração do ultrassônico: vazio fica MAIS LONGE do sensor que cheio (distância maior)
            $erro = 'Calibração do sensor: informe distâncias entre 1 e 200 cm, com o reservatório vazio mais longe que o cheio.';
        } elseif (!is_numeric($g90v) || !is_numeric($g90c) || (float) $g90v < 10 || (float) $g90c > 1000 || (float) $g90c < (float) $g90v) {
            // Cheio libera MAIS que vazio (mais ração em cima = mais pressão)
            $erro = 'Calibração da comporta: informe gramas por abertura de 90° entre 10 e 1000, com o reservatório cheio liberando igual ou mais que o vazio.';
        } elseif (!ctype_digit($abertura) || (int) $abertura < 100 || (int) $abertura > 3000) {
            $erro = 'Calibração da comporta: o tempo aberta deve ficar entre 100 e 3000 ms.';
        }

        return [
            $erro,
            [
                'nome' => $nome, 'codigo_mac_esp' => $mac, 'pet_id' => $petId > 0 ? $petId : null,
                'cheio_cm' => is_numeric($cheio) ? (float) $cheio : 5.0,
                'vazio_cm' => is_numeric($vazio) ? (float) $vazio : 25.0,
                'g90_vazio' => is_numeric($g90v) ? (float) $g90v : 60.0,
                'g90_cheio' => is_numeric($g90c) ? (float) $g90c : 60.0,
                'abertura_ms' => ctype_digit($abertura) ? (int) $abertura : 300,
            ],
            ['nome' => $nome, 'codigo_mac_esp' => $mac, 'pet_id' => (string) $petId, 'cheio_cm' => $cheio, 'vazio_cm' => $vazio,
             'g90_vazio' => $g90v, 'g90_cheio' => $g90c, 'abertura_ms' => $abertura],
        ];
    }

    /** Pets que o usuário pode ligar a este comedouro (sem comedouro ainda). */
    private function carregarPets(int $dispositivoId, string &$erro): array
    {
        try {
            return Dispositivo::petsDisponiveis((int) $_SESSION['usuario_id'], $dispositivoId);
        } catch (PDOException $e) {
            error_log('[dispositivos] ' . $e->getMessage());
            $erro = 'Erro no banco de dados: ' . $e->getMessage();
        } catch (RuntimeException $e) {
            error_log('[dispositivos] ' . $e->getMessage());
            $erro = $e->getMessage();
        }
        return [];
    }

    /** O pet escolhido (ou nenhum) está na lista de pets disponíveis? */
    private function petPermitido(?int $petId, array $pets): bool
    {
        return $petId === null || in_array($petId, array_map('intval', array_column($pets, 'id')), true);
    }

    private function mensagemDeErro(PDOException $e): string
    {
        if (($e->errorInfo[1] ?? 0) === 1062) {
            return str_contains($e->getMessage(), 'uq_dispositivos_pet')
                ? 'Este pet já está ligado a outro comedouro.'
                : 'Este código MAC já está cadastrado em outro comedouro.';
        }
        error_log('[dispositivos] ' . $e->getMessage());
        return 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, mensagem genérica
    }

    private function carregarOu404(int $id): array
    {
        try {
            $d = Dispositivo::buscar($id, (int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[dispositivos] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        } catch (RuntimeException $e) {
            error_log('[dispositivos] ' . $e->getMessage());
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
}
