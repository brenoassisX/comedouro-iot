<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use PDOException;
use RuntimeException;

/**
 * UsuarioController (versão simples): tela de login, login, cadastro e logout.
 *
 * COMO FUNCIONA (resumo para a apresentação):
 *  - O formulário envia os dados por POST para /login ou /cadastrar (rotas no public/index.php).
 *  - Se deu certo: guardamos o id do usuário em $_SESSION['usuario_id'] e mandamos para /painel.
 *  - Se deu errado: preenchemos a variável $erro e mostramos a tela de login de novo.
 *    A View (app/Views/login.php) exibe $erro numa caixa vermelha.
 *
 * ERROS QUE VALEM PARA TODAS AS FUNÇÕES (e como resolver):
 *  1) "Cannot modify header information - headers already sent by (output started at X:linha)"
 *     -> algo foi impresso (echo, espaço ou linha em branco antes do <?php) ANTES do header().
 *        A mensagem diz o arquivo e a linha exatos: apague o espaço/echo que está lá.
 *  2) "Undefined global variable $_SESSION" / a sessão nunca guarda o login
 *     -> o session_start() não rodou. Ele fica no public/index.php (confira se não foi apagado).
 *  3) "Class App\Controllers\UsuarioController not found"
 *     -> nome do arquivo diferente do nome da classe, ou arquivo fora de app/Controllers/.
 *        (No Linux, maiúscula/minúscula importa.)
 *  4) "Undefined constant BASE_PATH"
 *     -> o controller foi chamado fora do index.php. O BASE_PATH é definido lá.
 *
 * ONDE VER AS MENSAGENS TÉCNICAS: as funções abaixo gravam o erro real com error_log().
 *  - Servidor embutido (php -S): aparece no terminal onde você rodou o comando.
 *  - XAMPP: xampp/apache/logs/error.log
 */
class UsuarioController
{
    // ==================================================================
    // GET /login  (e GET /)  ->  mostra a tela de login/cadastro
    // ==================================================================
    /**
     * POSSÍVEIS ERROS:
     *  - "Failed opening required '.../app/Views/login.php'"
     *      -> o arquivo da View não está em app/Views/login.php (nome ou pasta errados).
     *  - Página em branco (sem nada)
     *      -> erro de PHP escondido. Ligue a exibição de erros no php.ini (display_errors=On)
     *         ou olhe o log citado no topo deste arquivo.
     */
    public function mostrar(): void
    {
        // Quem já está logado não precisa ver a tela de login
        if (!empty($_SESSION['usuario_id'])) {
            header('Location: /painel');
            exit; // sem o exit o PHP continuaria executando o resto do código
        }

        $aba = 'entrar'; // a View usa esta variável para saber qual aba abrir
        require BASE_PATH . '/app/Views/login.php';
    }

    // ==================================================================
    // POST /login  ->  confere e-mail e senha
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *
     *  1) Campo vazio
     *       -> $erro = 'Informe o e-mail e a senha.' (nem consulta o banco).
     *
     *  2) MySQL DESLIGADO (ou host/porta errados em app/Config/config.php)
     *       -> Database::getConnection() lança RuntimeException('Erro ao conectar ao banco de dados.')
     *          e o usuário vê "Erro ao conectar ao banco de dados."
     *          O motivo real fica no log, linha "Falha na conexao com o banco: ...":
     *            SQLSTATE[HY000] [2002] Connection refused / No connection could be made
     *              -> MySQL desligado. Ligue o MySQL (XAMPP: botão Start no MySQL) ou confira host/porta.
     *            SQLSTATE[HY000] [1045] Access denied for user 'x'@'localhost'
     *              -> usuário ou senha do banco errados no config.php.
     *            SQLSTATE[HY000] [1049] Unknown database 'comedouro_iot'
     *              -> o banco não existe (rode database/schema.sql) ou o nome no config.php está errado.
     *
     *  3) Tabela ou coluna inexistente
     *       -> PDOException; o usuário vê "Erro no banco de dados: <mensagem>". Exemplos:
     *            SQLSTATE[42S02] ... Table 'comedouro_iot.usuarios' doesn't exist
     *              -> rode o schema.sql no banco certo.
     *            SQLSTATE[42S22] ... Unknown column 'senha_hash'
     *              -> a tabela usuarios foi criada com colunas diferentes das que o código usa.
     *
     *  4) E-mail NÃO existe na tabela
     *       -> fetch() devolve false -> $erro = 'E-mail ou senha inválidos.'
     *          (a mensagem é igual à da senha errada de propósito, para não revelar quais e-mails existem)
     *
     *  5) E-mail existe, mas a senha NÃO bate com o hash
     *       -> password_verify() devolve false -> mesma mensagem 'E-mail ou senha inválidos.'
     *          Se você TEM CERTEZA que a senha está certa e mesmo assim falha, olhe a coluna senha_hash:
     *            - Se tem a senha em texto puro (ex.: "123456"), nunca vai bater: o campo precisa
     *              guardar o resultado do password_hash() (começa com $2y$ e tem ~60 caracteres).
     *            - O usuário de teste do schema.sql tem um hash FALSO ('$2y$10$substitua...'):
     *              não dá para entrar com ele. Crie a conta pela tela de cadastro.
     *            - Se a coluna senha_hash for menor que 60 caracteres, o hash é cortado e quebra.
     *
     *  6) senha_hash é NULL (conta criada sem senha, ex.: testes do login com Google)
     *       -> o "senha_hash !== null" abaixo evita um TypeError do password_verify e cai no
     *          erro 'E-mail ou senha inválidos.'
     *
     *  7) Tudo certo
     *       -> guarda $_SESSION['usuario_id'] e redireciona para /painel.
     */
    public function login(): void
    {
        $erro = '';

        // (string) evita erro caso alguém envie senha[] (array) em vez de texto
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $senha = (string) ($_POST['senha'] ?? '');

        if ($email === '' || $senha === '') {
            $erro = 'Informe o e-mail e a senha.';
        } else {
            try {
                $pdo = Database::getConnection(); // pode lançar RuntimeException (caso 2)

                // Prepared statement: o e-mail vai separado do SQL, então não há SQL Injection
                $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $usuario = $stmt->fetch(); // array com os dados, ou false se não achou (caso 4)

                if ($usuario !== false
                    && $usuario['senha_hash'] !== null
                    && password_verify($senha, $usuario['senha_hash'])) {

                    $_SESSION['usuario_id'] = (int) $usuario['id'];
                    $_SESSION['usuario_nome'] = (string) $usuario['nome']; // mostrado no canto da tela
                    header('Location: /painel');
                    exit;
                }

                // Caiu aqui: e-mail não existe (4), senha não bate (5) ou senha_hash vazio (6)
                $erro = 'E-mail ou senha inválidos.';

            } catch (PDOException $e) {
                // ATENÇÃO: PDOException precisa vir ANTES de RuntimeException (ela é "filha" dela).
                // Erro dentro de uma consulta SQL (caso 3).
                error_log('[login] ' . $e->getMessage());
                // Para a apresentação final, troque por uma mensagem genérica (não mostre detalhes do banco).
                $erro = 'Erro no banco de dados: ' . $e->getMessage();
            } catch (RuntimeException $e) {
                // Não conseguiu nem conectar (caso 2).
                error_log('[login] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        // Só chega aqui se deu erro: mostra a tela de novo, mantendo o e-mail digitado
        $aba = 'entrar';
        $old = ['email' => $email];
        require BASE_PATH . '/app/Views/login.php';
    }

    // ==================================================================
    // POST /cadastrar  ->  cria a conta e já deixa o usuário logado
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *
     *  1) Campo vazio / e-mail inválido / senha curta ou longa demais
     *       -> $erro com a mensagem correspondente; nada é gravado no banco.
     *          (Senha: mínimo 8 e máximo 72 caracteres. O limite de 72 existe porque o bcrypt do
     *           password_hash ignora tudo que passa disso.)
     *
     *  2) E-MAIL JÁ EXISTE na tabela (restrição UNIQUE uq_usuarios_email)
     *       -> o MySQL recusa o INSERT e o PDO lança PDOException:
     *            SQLSTATE[23000]: Integrity constraint violation: 1062
     *            Duplicate entry 'fulano@x.com' for key 'usuarios.uq_usuarios_email'
     *          O código identifica pelo número 1062 ($e->errorInfo[1]) e mostra
     *          'Este e-mail já está cadastrado.'
     *          Se ESSE aviso aparecer e você acha que o e-mail é novo, ele já foi cadastrado num teste
     *          anterior: apague a linha (DELETE FROM usuarios WHERE email = '...') ou use outro e-mail.
     *
     *  3) Texto maior que a coluna (nome > 100 ou e-mail > 150 caracteres)
     *       -> o MySQL em modo estrito dá "SQLSTATE[22001] ... 1406 Data too long for column".
     *          O código já barra isso antes (caso 1), então só acontece se você mudar o tamanho das
     *          colunas sem mudar os limites aqui.
     *
     *  4) A tabela tem outra coluna obrigatória (NOT NULL sem valor padrão)
     *       -> "SQLSTATE[HY000]: General error: 1364 Field 'xyz' doesn't have a default value".
     *          Ou dê um DEFAULT/NULL para a coluna, ou inclua ela no INSERT abaixo.
     *
     *  5) MySQL desligado, banco inexistente, tabela inexistente
     *       -> iguais ao login(): veja a lista completa de mensagens e soluções no comentário dele.
     *
     *  6) Tudo certo
     *       -> grava com a senha já transformada por password_hash(), guarda o id novo na sessão
     *          (lastInsertId) e redireciona para /painel.
     */
    public function cadastrar(): void
    {
        $erro = '';

        $nome  = trim((string) ($_POST['nome'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $senha = (string) ($_POST['senha'] ?? '');

        // Validações simples. Só a primeira que falhar gera mensagem (elseif).
        if ($nome === '' || $email === '' || $senha === '') {
            $erro = 'Preencha nome, e-mail e senha.';
        } elseif (strlen($nome) > 100) {
            $erro = 'O nome pode ter no máximo 100 caracteres.';
        } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif (strlen($senha) < 8) {
            $erro = 'A senha deve ter pelo menos 8 caracteres.';
        } elseif (strlen($senha) > 72) {
            $erro = 'A senha deve ter no máximo 72 caracteres.';
        } else {
            try {
                $pdo = Database::getConnection(); // pode lançar RuntimeException (caso 5)

                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios (nome, email, senha_hash) VALUES (:nome, :email, :senha_hash)'
                );
                $stmt->execute([
                    ':nome'       => $nome,
                    ':email'      => $email,
                    // NUNCA grave a senha pura: password_hash gera o "embaralhado" seguro
                    ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                ]);

                $_SESSION['usuario_id'] = (int) $pdo->lastInsertId(); // id da linha recém-criada
                $_SESSION['usuario_nome'] = $nome;
                header('Location: /painel');
                exit;

            } catch (PDOException $e) {
                // PDOException vem ANTES de RuntimeException (ela é "filha" dela).
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    // Caso 2: e-mail duplicado (UNIQUE)
                    $erro = 'Este e-mail já está cadastrado.';
                } else {
                    // Casos 3, 4 e tabela/coluna inexistente
                    error_log('[cadastrar] ' . $e->getMessage());
                    // Para a apresentação final, troque por uma mensagem genérica.
                    $erro = 'Erro no banco de dados: ' . $e->getMessage();
                }
            } catch (RuntimeException $e) {
                // Não conseguiu conectar (MySQL desligado, senha do banco errada...)
                error_log('[cadastrar] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        // Só chega aqui se deu erro: reabre a tela na aba "Criar Conta", mantendo nome e e-mail
        $aba = 'cadastrar';
        $old = ['nome' => $nome, 'email' => $email]; // a senha nunca volta para a tela
        require BASE_PATH . '/app/Views/login.php';
    }

    // ==================================================================
    // GET /logout  ->  encerra a sessão
    // ==================================================================
    /**
     * POSSÍVEIS ERROS:
     *  - "session_destroy(): Trying to destroy uninitialized session"
     *      -> o session_start() não rodou (veja erro 2 no topo do arquivo).
     *  - Depois de sair, o botão "voltar" do navegador mostra o painel
     *      -> é só a página guardada em cache; ao recarregar ela pede login de novo,
     *         porque o PainelController confere a sessão a cada acesso.
     */
    public function logout(): void
    {
        $_SESSION = [];     // esvazia os dados da sessão (some o usuario_id)
        session_destroy();  // apaga a sessão no servidor

        header('Location: /login');
        exit;
    }
}
