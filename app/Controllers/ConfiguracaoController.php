<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\UploadImagem;
use App\Models\Usuario;
use PDOException;
use RuntimeException;

/**
 * Configuração: perfil do tutor, notificações, idioma e edição de dados pessoais.
 *
 * Rotas (public/index.php):
 *   GET  /configuracao              -> index()             visão geral
 *   GET  /configuracao/perfil       -> perfil()            formulário "Editar perfil"
 *   POST /configuracao/perfil       -> salvarPerfil()
 *   POST /configuracao/preferencias -> salvarPreferencias() (toggles de notificação e idioma)
 *
 * ERROS DE BANCO: "Unknown column 'telefone'/'notif_horarios'" (42S22) -> falta rodar a migração 08.
 * "Erro ao conectar ao banco de dados." -> MySQL desligado (veja o UsuarioController).
 */
class ConfiguracaoController
{
    private const PASTA_FOTOS = '/public/uploads/usuarios/';
    private const MENSAGENS = [
        'perfil_salvo' => 'Dados atualizados com sucesso.',
        'preferencias' => 'Preferências salvas.',
    ];

    // ==================================================================
    // GET /configuracao
    // ==================================================================
    public function index(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $sucesso = self::MENSAGENS[(string) ($_GET['msg'] ?? '')] ?? '';
        $usuario = $this->carregarUsuarioOu404();

        require BASE_PATH . '/app/Views/configuracao.php';
    }

    // ==================================================================
    // GET /configuracao/perfil
    // ==================================================================
    public function perfil(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $usuario = $this->carregarUsuarioOu404();
        $erro    = '';
        $old     = [
            'nome'     => $usuario['nome'],
            'email'    => $usuario['email'],
            'telefone' => $usuario['telefone'] ?? '',
        ];

        require BASE_PATH . '/app/Views/configuracao_perfil.php';
    }

    // ==================================================================
    // POST /configuracao/perfil
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *  1) Campo inválido (nome vazio, e-mail inválido, telefone estranho, senha curta/diferente da
     *     confirmação) -> mensagem e o formulário reaparece. A foto precisa ser escolhida de novo.
     *  2) "Nova senha" em branco -> a senha atual é mantida.
     *  3) E-mail já usado por OUTRA conta (UNIQUE uq_usuarios_email, erro 1062)
     *       -> "Este e-mail já está em uso por outra conta."
     *  4) Foto: JPG/PNG/WebP até 2 MB (mesmas regras do cadastro de pet). A foto antiga é apagada
     *     só depois que o banco atualiza com sucesso.
     *  5) Tudo certo -> grava, atualiza o nome mostrado na tela e volta para /configuracao.
     */
    public function salvarPerfil(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $usuario   = $this->carregarUsuarioOu404();
        $usuarioId = (int) $usuario['id'];
        $erro      = '';

        $nome      = trim((string) ($_POST['nome'] ?? ''));
        $email     = strtolower(trim((string) ($_POST['email'] ?? '')));
        $telefone  = trim((string) ($_POST['telefone'] ?? ''));
        $senha     = (string) ($_POST['senha'] ?? '');
        $confirmar = (string) ($_POST['confirmar_senha'] ?? '');
        $old       = ['nome' => $nome, 'email' => $email, 'telefone' => $telefone];

        if ($nome === '' || strlen($nome) > 100) {
            $erro = 'Informe o nome completo (até 100 caracteres).';
        } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($telefone !== '' && !preg_match('/^[0-9()+\-\s]{8,20}$/', $telefone)) {
            $erro = 'Informe um telefone válido, ex.: (00) 00000-0000.';
        } elseif ($senha !== '' && (strlen($senha) < 8 || strlen($senha) > 72)) {
            $erro = 'A nova senha deve ter entre 8 e 72 caracteres.';
        } elseif ($senha !== $confirmar) {
            $erro = 'A confirmação não é igual à nova senha.';
        } else {
            $novaFoto = null;

            try {
                $novaFoto = UploadImagem::salvar($_FILES['foto'] ?? [], BASE_PATH . self::PASTA_FOTOS);

                Usuario::atualizarPerfil($usuarioId, [
                    'nome'       => $nome,
                    'email'      => $email,
                    'telefone'   => $telefone !== '' ? $telefone : null,
                    'foto'       => $novaFoto ?? $usuario['foto'],
                    'senha_hash' => $senha !== '' ? password_hash($senha, PASSWORD_DEFAULT) : null,
                ]);

                if ($novaFoto !== null) {
                    $this->apagarFoto($usuario['foto']);
                }
                $_SESSION['usuario_nome'] = $nome;

                header('Location: /configuracao?msg=perfil_salvo');
                exit;

            } catch (PDOException $e) {
                $this->apagarFoto($novaFoto);
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    $erro = 'Este e-mail já está em uso por outra conta.';
                } else {
                    error_log('[configuracao/perfil] ' . $e->getMessage());
                    $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, mensagem genérica
                }
            } catch (RuntimeException $e) {
                $this->apagarFoto($novaFoto);
                error_log('[configuracao/perfil] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        require BASE_PATH . '/app/Views/configuracao_perfil.php';
    }

    // ==================================================================
    // POST /configuracao/preferencias  (os toggles enviam o formulário sozinhos)
    // ==================================================================
    /**
     * Um checkbox desmarcado simplesmente NÃO é enviado pelo navegador; por isso
     * "isset($_POST[...])" = ligado e ausente = desligado.
     * Por enquanto o idioma aceito é só pt-BR. As notificações são apenas guardadas: o ENVIO
     * dos avisos (e-mail/push) ainda não existe.
     */
    public function salvarPreferencias(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        try {
            Usuario::atualizarPreferencias(
                (int) $_SESSION['usuario_id'],
                isset($_POST['notif_horarios']) ? 1 : 0,
                isset($_POST['notif_alertas']) ? 1 : 0,
                'pt-BR'
            );
        } catch (PDOException $e) {
            error_log('[configuracao/preferencias] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        } catch (RuntimeException $e) {
            error_log('[configuracao/preferencias] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }

        header('Location: /configuracao?msg=preferencias');
        exit;
    }

    // ==================================================================
    // Funções de apoio
    // ==================================================================

    private function carregarUsuarioOu404(): array
    {
        try {
            $u = Usuario::buscar((int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[configuracao] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        } catch (RuntimeException $e) {
            error_log('[configuracao] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        }

        if ($u === null) {   // conta apagada com a sessão ainda aberta
            unset($_SESSION['usuario_id']);
            header('Location: /login');
            exit;
        }
        return $u;
    }

    private function apagarFoto(?string $arquivo): void
    {
        if ($arquivo !== null && $arquivo !== '') {
            @unlink(BASE_PATH . self::PASTA_FOTOS . basename($arquivo));
        }
    }
}
