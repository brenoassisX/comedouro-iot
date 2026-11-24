<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Recebe uma foto enviada por <input type="file"> e grava numa pasta.
 * Devolve o NOME do arquivo salvo (ou null se o usuário não enviou nada).
 * Em caso de problema lança RuntimeException com uma mensagem pronta para mostrar na tela.
 *
 * ERROS COMUNS (e o que fazer):
 *  - A foto simplesmente não chega ($_FILES vazio, sem mensagem de erro)
 *      -> faltou enctype="multipart/form-data" na tag <form> da View.
 *  - "A foto é grande demais" mesmo sendo pequena
 *      -> limite do php.ini: upload_max_filesize (padrão 2M) e post_max_size (padrão 8M).
 *  - "Class finfo not found" -> ative a extensão fileinfo no php.ini (extension=fileinfo).
 *  - "Não foi possível salvar a foto" / log com "Permission denied"
 *      -> a pasta public/uploads/pets não tem permissão de escrita (Linux: chmod 775 / chown www-data).
 */
final class UploadImagem
{
    private const TIPOS_ACEITOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const TAMANHO_MAXIMO = 2 * 1024 * 1024; // 2 MB

    public static function salvar(array $arquivo, string $pasta): ?string
    {
        $codigo = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return null; // foto é opcional
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('A foto é grande demais (máximo 2 MB).');
        }
        if ($codigo !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao enviar a foto (código de erro ' . $codigo . ').');
        }
        if ($arquivo['size'] > self::TAMANHO_MAXIMO) {
            throw new RuntimeException('A foto é grande demais (máximo 2 MB).');
        }
        if (!is_uploaded_file($arquivo['tmp_name'])) {
            throw new RuntimeException('Arquivo de foto inválido.');
        }

        // O tipo é descoberto pelo CONTEÚDO do arquivo (o nome/tipo informado pelo navegador pode mentir)
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!isset(self::TIPOS_ACEITOS[$mime])) {
            throw new RuntimeException('Formato de foto inválido. Use JPG, PNG ou WebP.');
        }
        if (@getimagesize($arquivo['tmp_name']) === false) {
            throw new RuntimeException('O arquivo enviado não parece ser uma imagem válida.');
        }

        if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
            throw new RuntimeException('Não foi possível criar a pasta de fotos no servidor.');
        }

        // Nome aleatório: evita sobrescrever fotos e nomes maliciosos
        $nome = bin2hex(random_bytes(16)) . '.' . self::TIPOS_ACEITOS[$mime];

        if (!move_uploaded_file($arquivo['tmp_name'], rtrim($pasta, '/') . '/' . $nome)) {
            throw new RuntimeException('Não foi possível salvar a foto no servidor (verifique a permissão da pasta).');
        }

        return $nome;
    }
}
