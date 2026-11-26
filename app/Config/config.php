<?php
declare(strict_types=1);

/**
 * Configuracoes da aplicacao.
 * Em producao, prefira variaveis de ambiente (getenv) em vez de valores fixos.
 */
return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'comedouro_iot',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'timezone' => 'America/Sao_Paulo',
    // Janela (minutos) em que um agendamento ainda pode ser disparado apos o horario
    'janela_agendamento_min' => 30,

    // URL pública da aplicação (usada nos links de e-mail e no redirect do Google).
    // NÃO use o header Host da requisição para isto: pode ser forjado.
    'app_url' => getenv('APP_URL') ?: 'http://localhost:8000',

    // MÓDULO 1 - e-mail. driver: 'mail' (função mail()) ou 'log' (escreve no error_log, ótimo p/ testes locais)
    'mail' => [
        'driver'    => getenv('MAIL_DRIVER') ?: 'mail',
        'from'      => getenv('MAIL_FROM') ?: 'no-reply@localhost',
        'from_nome' => 'PetSync',
    ],

    // MÓDULO 2 - Google OAuth
    'google' => [
        'client_id'     => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    ],
];
