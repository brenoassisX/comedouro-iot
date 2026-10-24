<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\UploadImagem;
use App\Models\Pet;
use App\Services\CalculoEnergetico;
use PDOException;
use RuntimeException;

/**
 * Meus Pets: listar, cadastrar, visualizar (perfil), editar e excluir.
 *
 * Rotas (public/index.php):
 *   GET  /pets             -> index()      lista os pets do usuário
 *   GET  /pets/novo        -> novo()       formulário de cadastro
 *   POST /pets             -> salvar()     grava o novo pet
 *   GET  /pets/perfil?id=  -> perfil()     visualizar (perfil alimentar + cálculo energético)
 *   GET  /pets/editar?id=  -> editar()     formulário de edição (mesma View do cadastro)
 *   POST /pets/atualizar   -> atualizar()  grava a edição
 *   POST /pets/excluir     -> excluir()    apaga o pet (e a foto)
 *
 * Todas exigem login: sem $_SESSION['usuario_id'] voltam para /login.
 * Erros de banco (MySQL desligado, tabela inexistente...) seguem a mesma lógica do UsuarioController:
 * veja a lista de mensagens SQLSTATE no comentário do login() de lá.
 */
class PetController
{
    private const PASTA_FOTOS = '/public/uploads/pets/';

    // ==================================================================
    // GET /pets
    // ==================================================================
    /**
     * POSSÍVEIS ERROS:
     *  - "Unknown column 'porte'..." (SQLSTATE 42S22) -> falta rodar database/migracoes/05_pets_cadastro.sql.
     *  - Lista vazia mesmo tendo cadastrado -> o pet foi salvo com outro usuario_id (outro login).
     * O parâmetro $erro existe para o excluir() poder reaproveitar esta tela mostrando uma mensagem.
     */
    public function index(string $erro = ''): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $pets = [];

        try {
            $pets = Pet::listarPorUsuario((int) $_SESSION['usuario_id']);
        } catch (PDOException $e) { // PDOException antes de RuntimeException (é "filha" dela)
            error_log('[pets] ' . $e->getMessage());
            $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, use mensagem genérica
        } catch (RuntimeException $e) {
            error_log('[pets] ' . $e->getMessage());
            $erro = $e->getMessage();
        }

        require BASE_PATH . '/app/Views/pets.php';
    }

    // ==================================================================
    // GET /pets/novo
    // ==================================================================
    public function novo(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $modo      = 'novo';
        $erro      = '';
        $old       = [];
        $petId     = null;
        $fotoAtual = null;
        require BASE_PATH . '/app/Views/pet_form.php';
    }

    // ==================================================================
    // POST /pets  ->  valida, salva a foto, calcula a porção e grava
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *
     *  1) Campo inválido -> $erro com a mensagem e o formulário reaparece com o que foi digitado
     *     (veja lerFormulario()). A foto NÃO é guardada nesse caso: o navegador exige escolher de novo.
     *
     *  2) Problema com a foto (grande demais, formato errado, pasta sem permissão)
     *       -> UploadImagem lança RuntimeException com mensagem amigável; nada vai para o banco.
     *          Se a foto simplesmente não chega, falta enctype="multipart/form-data" no <form>.
     *
     *  3) Erros do MySQL ao gravar (PDOException):
     *       "Unknown column 'porte' in 'field list'"  (42S22) -> migração 05 não foi rodada.
     *       "Cannot add or update a child row: a foreign key constraint fails" (1452)
     *            -> o usuario_id da sessão não existe mais na tabela usuarios. Saia e entre de novo.
     *     Se o INSERT falhar depois de a foto ser salva, a foto é apagada para não sobrar lixo.
     *
     *  4) MySQL desligado -> RuntimeException "Erro ao conectar ao banco de dados." (veja UsuarioController).
     *
     *  5) Tudo certo -> grava, calcula pets.porcao_padrao_g e redireciona para /pets/perfil?id=<novo>.
     */
    public function salvar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        [$erro, $dados, $old] = $this->lerFormulario();

        if ($erro === '') {
            $fotoSalva = null;

            try {
                $fotoSalva = UploadImagem::salvar($_FILES['foto'] ?? [], BASE_PATH . self::PASTA_FOTOS);

                // Cálculo energético: a porção por refeição vira o padrão do comedouro para este pet
                $calc = CalculoEnergetico::calcular($dados['especie'], (float) $dados['peso_kg'], $dados['idade_meses'], $dados['porte']);

                $dados['foto']            = $fotoSalva;
                $dados['porcao_padrao_g'] = $calc['g_refeicao'] ?? 50;

                $id = Pet::criar((int) $_SESSION['usuario_id'], $dados);

                header('Location: /pets/perfil?id=' . $id);
                exit;

            } catch (PDOException $e) {
                $this->apagarFoto($fotoSalva);
                error_log('[pets/salvar] ' . $e->getMessage());
                $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, use mensagem genérica
            } catch (RuntimeException $e) {
                // Pode ser erro de foto (mensagem amigável) ou de conexão com o banco
                $this->apagarFoto($fotoSalva);
                error_log('[pets/salvar] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        // Só chega aqui se deu erro: mostra o formulário de novo, mantendo o que foi digitado
        $modo      = 'novo';
        $petId     = null;
        $fotoAtual = null;
        require BASE_PATH . '/app/Views/pet_form.php';
    }

    // ==================================================================
    // GET /pets/perfil?id=3  ->  visualizar
    // ==================================================================
    /**
     * POSSÍVEIS ERROS:
     *  - "Pet não encontrado." (404) -> o id não existe OU pertence a outro usuário.
     *  - Cards de nutrição avisam que falta o peso -> pet antigo, sem peso. Use Editar.
     *  - Valores estranhos nos cards -> a conta está em CalculoEnergetico::calcular().
     */
    public function perfil(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $pet = $this->carregarPetOu404((int) ($_GET['id'] ?? 0));

        $calc = CalculoEnergetico::calcular(
            $pet['especie'],
            $pet['peso_kg'] !== null ? (float) $pet['peso_kg'] : null,
            $pet['idade_meses'] !== null ? (int) $pet['idade_meses'] : null,
            $pet['porte']
        );

        require BASE_PATH . '/app/Views/pet_perfil.php';
    }

    // ==================================================================
    // GET /pets/editar?id=3  ->  formulário preenchido com os dados do pet
    // ==================================================================
    /**
     * POSSÍVEIS ERROS:
     *  - "Pet não encontrado." (404) -> id inexistente ou de outro usuário.
     *  - Idade aparece estranha -> o banco guarda MESES; aqui convertemos para anos quando
     *    é múltiplo de 6 meses (ex.: 18 meses -> "1.5 anos"), senão mostramos em meses.
     */
    public function editar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $pet = $this->carregarPetOu404((int) ($_GET['id'] ?? 0));

        $meses = $pet['idade_meses'] !== null ? (int) $pet['idade_meses'] : null;
        if ($meses === null) {
            $idade = '';
            $unidade = 'anos';
        } elseif ($meses % 6 === 0) {
            $idade = (string) ($meses / 12);
            $unidade = 'anos';
        } else {
            $idade = (string) $meses;
            $unidade = 'meses';
        }

        $old = [
            'nome'          => $pet['nome'],
            'especie'       => $pet['especie'],
            'raca'          => $pet['raca'] ?? '',
            'porte'         => $pet['porte'],
            'idade'         => $idade,
            'unidade_idade' => $unidade,
            'peso'          => $pet['peso_kg'] !== null ? rtrim(rtrim((string) $pet['peso_kg'], '0'), '.') : '',
            'observacoes'   => $pet['observacoes'] ?? '',
        ];

        $modo      = 'editar';
        $erro      = '';
        $petId     = (int) $pet['id'];
        $fotoAtual = $pet['foto'];
        require BASE_PATH . '/app/Views/pet_form.php';
    }

    // ==================================================================
    // POST /pets/atualizar  ->  grava a edição
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *  1) id inexistente / de outro usuário -> 404 "Pet não encontrado."
     *  2) Campo inválido -> formulário reaparece com a mensagem (a foto precisa ser escolhida de novo).
     *  3) Nenhuma foto nova -> a foto atual é mantida.
     *  4) Foto nova -> substitui a antiga; o arquivo antigo só é apagado DEPOIS de o banco atualizar
     *     com sucesso (se o UPDATE falhar, a foto antiga continua valendo e a nova é descartada).
     *  5) Erros de banco / foto -> mesmas mensagens do salvar().
     *  6) Tudo certo -> a porção por refeição é recalculada (pets.porcao_padrao_g) e vai para o perfil.
     */
    public function atualizar(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $id  = (int) ($_POST['id'] ?? 0);
        $pet = $this->carregarPetOu404($id);

        [$erro, $dados, $old] = $this->lerFormulario();

        if ($erro === '') {
            $novaFoto = null;

            try {
                $novaFoto = UploadImagem::salvar($_FILES['foto'] ?? [], BASE_PATH . self::PASTA_FOTOS);

                $calc = CalculoEnergetico::calcular($dados['especie'], (float) $dados['peso_kg'], $dados['idade_meses'], $dados['porte']);

                $dados['foto']            = $novaFoto ?? $pet['foto'];
                $dados['porcao_padrao_g'] = $calc['g_refeicao'] ?? 50;

                Pet::atualizar($id, (int) $_SESSION['usuario_id'], $dados);

                if ($novaFoto !== null) {
                    $this->apagarFoto($pet['foto']); // troca concluída: remove a foto antiga
                }

                header('Location: /pets/perfil?id=' . $id);
                exit;

            } catch (PDOException $e) {
                $this->apagarFoto($novaFoto);
                error_log('[pets/atualizar] ' . $e->getMessage());
                $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, use mensagem genérica
            } catch (RuntimeException $e) {
                $this->apagarFoto($novaFoto);
                error_log('[pets/atualizar] ' . $e->getMessage());
                $erro = $e->getMessage();
            }
        }

        $modo      = 'editar';
        $petId     = $id;
        $fotoAtual = $pet['foto'];
        require BASE_PATH . '/app/Views/pet_form.php';
    }

    // ==================================================================
    // POST /pets/excluir  ->  apaga o pet e a foto
    // ==================================================================
    /**
     * O QUE ACONTECE EM CADA SITUAÇÃO:
     *  1) id inexistente / de outro usuário -> 404 "Pet não encontrado."
     *  2) O pet estava ligado a um comedouro -> o comedouro continua existindo, só fica sem pet
     *     (FK dispositivos.pet_id com ON DELETE SET NULL). O histórico de alimentação também é mantido.
     *  3) "Cannot delete or update a parent row: a foreign key constraint fails" (1451)
     *       -> alguma FK foi criada sem ON DELETE SET NULL/CASCADE. Confira o schema.sql.
     *  4) MySQL desligado -> "Erro ao conectar ao banco de dados."
     *  5) Tudo certo -> apaga a linha, apaga o arquivo da foto e volta para a lista.
     *
     * Por ser POST (e não um link), um robô ou o botão "voltar" não consegue excluir sem querer.
     */
    public function excluir(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        $id  = (int) ($_POST['id'] ?? 0);
        $pet = $this->carregarPetOu404($id);

        try {
            Pet::excluir($id, (int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[pets/excluir] ' . $e->getMessage());
            $this->index('Não foi possível excluir: ' . $e->getMessage()); // na apresentação final, mensagem genérica
            return;
        } catch (RuntimeException $e) {
            error_log('[pets/excluir] ' . $e->getMessage());
            $this->index($e->getMessage());
            return;
        }

        $this->apagarFoto($pet['foto']);

        header('Location: /pets');
        exit;
    }

    // ==================================================================
    // Funções de apoio (usadas por mais de um método acima)
    // ==================================================================

    /**
     * Lê e valida o formulário (usado no cadastro E na edição).
     * Devolve [$erro, $dados, $old]:
     *   $erro  -> '' se está tudo certo, ou a mensagem do primeiro problema encontrado
     *   $dados -> valores prontos para o banco (sem foto/porção; quem chama completa)
     *   $old   -> valores digitados, para reabrir o formulário em caso de erro
     *
     * ERROS POSSÍVEIS:
     *  - "Informe o nome..." etc. -> campo vazio ou fora do limite (mensagens abaixo).
     *  - "O envio ficou grande demais..." -> envio maior que post_max_size do php.ini: o PHP descarta
     *    $_POST e $_FILES inteiros (por isso testamos o CONTENT_LENGTH, senão o erro seria "preencha o nome").
     */
    private function lerFormulario(): array
    {
        $erro    = '';
        $nome    = trim((string) ($_POST['nome'] ?? ''));
        $especie = (string) ($_POST['especie'] ?? '');
        $raca    = trim((string) ($_POST['raca'] ?? ''));
        $porte   = (string) ($_POST['porte'] ?? '');
        $idade   = str_replace(',', '.', trim((string) ($_POST['idade'] ?? '')));
        $unidade = (string) ($_POST['unidade_idade'] ?? 'anos');
        $peso    = str_replace(',', '.', trim((string) ($_POST['peso'] ?? '')));
        $obs     = trim((string) ($_POST['observacoes'] ?? ''));

        // Idade sempre em MESES no banco (-1 = valor inválido)
        $idadeMeses = is_numeric($idade) ? (int) round((float) $idade * ($unidade === 'meses' ? 1 : 12)) : -1;

        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $erro = 'O envio ficou grande demais para o servidor (post_max_size). Use uma foto menor.';
        } elseif ($nome === '' || strlen($nome) > 80) {
            $erro = 'Informe o nome do pet (até 80 caracteres).';
        } elseif (!in_array($especie, ['cao', 'gato'], true)) {
            $erro = 'Escolha a espécie do pet.';
        } elseif (strlen($raca) > 80) {
            $erro = 'A raça pode ter no máximo 80 caracteres.';
        } elseif (!in_array($porte, ['pequeno', 'medio', 'grande'], true)) {
            $erro = 'Escolha o porte do pet.';
        } elseif (!in_array($unidade, ['anos', 'meses'], true) || $idadeMeses < 0 || $idadeMeses > 360) {
            $erro = 'Informe uma idade válida (de 0 a 30 anos).';
        } elseif (!is_numeric($peso) || (float) $peso < 0.1 || (float) $peso > 120) {
            $erro = 'Informe um peso válido, entre 0,1 e 120 kg.';
        } elseif (strlen($obs) > 500) {
            $erro = 'As observações podem ter no máximo 500 caracteres.';
        }

        $dados = [
            'nome'        => $nome,
            'especie'     => $especie,
            'raca'        => $raca,
            'porte'       => $porte,
            'idade_meses' => $idadeMeses,
            'peso_kg'     => is_numeric($peso) ? number_format((float) $peso, 2, '.', '') : '0',
            'observacoes' => $obs,
        ];
        $old = [
            'nome' => $nome, 'especie' => $especie, 'raca' => $raca, 'porte' => $porte,
            'idade' => $idade, 'unidade_idade' => $unidade, 'peso' => $peso, 'observacoes' => $obs,
        ];

        return [$erro, $dados, $old];
    }

    /**
     * Busca o pet do usuário logado. Se não existir (ou for de outro usuário), responde 404 e encerra.
     * Erros de banco aqui também encerram com mensagem (500).
     */
    private function carregarPetOu404(int $id): array
    {
        try {
            $pet = Pet::buscar($id, (int) $_SESSION['usuario_id']);
        } catch (PDOException $e) {
            error_log('[pets] ' . $e->getMessage());
            http_response_code(500);
            echo 'Erro no banco de dados: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        } catch (RuntimeException $e) {
            error_log('[pets] ' . $e->getMessage());
            http_response_code(500);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        }

        if ($pet === null) {
            http_response_code(404);
            echo 'Pet não encontrado.';
            exit;
        }

        return $pet;
    }

    /** Apaga o arquivo de foto da pasta de uploads (ignora se for null ou se o arquivo não existir). */
    private function apagarFoto(?string $arquivo): void
    {
        if ($arquivo !== null && $arquivo !== '') {
            @unlink(BASE_PATH . self::PASTA_FOTOS . basename($arquivo));
        }
    }
}
