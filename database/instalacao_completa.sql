-- =====================================================================
-- PetFeeder - INSTALAÇÃO COMPLETA do banco (para um computador novo)
--
-- Junta, na ordem certa: schema.sql + migrações 05, 06, 07, 08, 09, 10 e 11.
-- (As migrações 01 a 04 eram de funcionalidades retiradas e ficam de fora.)
--
-- COMO USAR: phpMyAdmin -> aba "Importar" (sem escolher nenhum banco) -> escolha este arquivo -> Importar.
-- Ele cria o banco comedouro_iot do zero. Se o banco já existir com dados, NÃO use este arquivo.
-- =====================================================================


-- ##################################################################
-- ## schema.sql
-- ##################################################################

-- =====================================================================
-- Comedouro IoT - Schema MySQL 8+ (InnoDB / utf8mb4)
-- =====================================================================
CREATE DATABASE IF NOT EXISTS comedouro_iot
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE comedouro_iot;

-- ---------------------------------------------------------------------
-- usuarios: donos dos pets e dos dispositivos
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    senha_hash  VARCHAR(255) NOT NULL,              -- password_hash()
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- pets: um usuario possui N pets
-- ---------------------------------------------------------------------
CREATE TABLE pets (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id        INT UNSIGNED NOT NULL,
    nome              VARCHAR(80) NOT NULL,
    especie           ENUM('cao','gato','outro') NOT NULL DEFAULT 'cao',
    raca              VARCHAR(80) NULL,
    peso_kg           DECIMAL(5,2) NULL,
    porcao_padrao_g   SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    criado_em         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pets_usuario (usuario_id),
    CONSTRAINT fk_pets_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- dispositivos: cada ESP32. Autentica via token (guardamos apenas o hash)
-- ---------------------------------------------------------------------
CREATE TABLE dispositivos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id      INT UNSIGNED NOT NULL,
    pet_id          INT UNSIGNED NULL,               -- pet atendido por este comedouro
    nome            VARCHAR(80) NOT NULL,
    mac_address     CHAR(17) NOT NULL,               -- AA:BB:CC:DD:EE:FF
    token_hash      CHAR(64) NOT NULL,               -- hash('sha256', token)
    nivel_racao     TINYINT UNSIGNED NULL,           -- 0..100 (%)
    ultimo_contato  DATETIME NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dispositivos_mac (mac_address),
    UNIQUE KEY uq_dispositivos_token (token_hash),
    KEY idx_dispositivos_usuario (usuario_id),
    CONSTRAINT chk_nivel_racao CHECK (nivel_racao IS NULL OR nivel_racao <= 100),
    CONSTRAINT fk_dispositivos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_dispositivos_pet FOREIGN KEY (pet_id)
        REFERENCES pets (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- agendamentos: horarios recorrentes de alimentacao
-- ---------------------------------------------------------------------
CREATE TABLE agendamentos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dispositivo_id  INT UNSIGNED NOT NULL,
    horario         TIME NOT NULL,
    dias_semana     SET('dom','seg','ter','qua','qui','sex','sab')
                    NOT NULL DEFAULT 'dom,seg,ter,qua,qui,sex,sab',
    porcao_g        SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_agendamentos_disp (dispositivo_id, ativo, horario),
    CONSTRAINT fk_agendamentos_disp FOREIGN KEY (dispositivo_id)
        REFERENCES dispositivos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- historico: fila + registro de todas as dispensas (manuais e agendadas)
-- status: pendente -> enviado (entregue ao ESP32) -> concluido | falha
-- ---------------------------------------------------------------------
CREATE TABLE historico (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dispositivo_id  INT UNSIGNED NOT NULL,
    pet_id          INT UNSIGNED NULL,
    agendamento_id  INT UNSIGNED NULL,
    origem          ENUM('manual','agendado') NOT NULL,
    porcao_g        SMALLINT UNSIGNED NOT NULL,
    status          ENUM('pendente','enviado','concluido','falha') NOT NULL DEFAULT 'pendente',
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_em      DATETIME NULL,
    concluido_em    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_historico_fila (dispositivo_id, status),
    KEY idx_historico_agend_dia (agendamento_id, criado_em),
    CONSTRAINT fk_historico_disp FOREIGN KEY (dispositivo_id)
        REFERENCES dispositivos (id) ON DELETE CASCADE,
    CONSTRAINT fk_historico_pet FOREIGN KEY (pet_id)
        REFERENCES pets (id) ON DELETE SET NULL,
    CONSTRAINT fk_historico_agend FOREIGN KEY (agendamento_id)
        REFERENCES agendamentos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Dados de teste (token do ESP32 em texto puro: "TOKEN_DE_TESTE_123")
-- Em producao gere tokens com bin2hex(random_bytes(32)).
-- ---------------------------------------------------------------------
INSERT INTO usuarios (nome, email, senha_hash)
VALUES ('Admin', 'admin@exemplo.com', '$2y$10$substitua.por.password_hash');

INSERT INTO pets (usuario_id, nome, especie, porcao_padrao_g)
VALUES (1, 'Thor', 'cao', 80);

INSERT INTO dispositivos (usuario_id, pet_id, nome, mac_address, token_hash)
VALUES (1, 1, 'Comedouro da Sala', 'AA:BB:CC:DD:EE:FF', SHA2('TOKEN_DE_TESTE_123', 256));

INSERT INTO agendamentos (dispositivo_id, horario, porcao_g)
VALUES (1, '08:00:00', 80), (1, '19:00:00', 80);


-- ##################################################################
-- ## migracoes/05_pets_cadastro.sql
-- ##################################################################

-- MÓDULO PETS: campos do formulário "Cadastro do pet" + foto
-- (a tabela pets já tinha: id, usuario_id, nome, especie, raca, peso_kg, porcao_padrao_g, criado_em)
ALTER TABLE pets
    ADD COLUMN porte        ENUM('pequeno','medio','grande') NOT NULL DEFAULT 'pequeno' AFTER raca,
    ADD COLUMN idade_meses  SMALLINT UNSIGNED NULL AFTER porte,     -- guardamos em MESES (a tela converte anos -> meses)
    ADD COLUMN observacoes  VARCHAR(500) NULL AFTER peso_kg,
    ADD COLUMN foto         VARCHAR(100) NULL AFTER observacoes,    -- só o NOME do arquivo (ex.: 3f9a...c1.jpg)
    ADD CONSTRAINT chk_pets_peso CHECK (peso_kg IS NULL OR peso_kg > 0);

-- Observação: pets.porcao_padrao_g (já existente) passa a receber a "quantidade por refeição"
-- calculada no cadastro, para o comedouro usar como porção padrão.


-- ##################################################################
-- ## migracoes/06_um_pet_por_comedouro.sql
-- ##################################################################

-- REGRA DE NEGÓCIO: cada comedouro tem no máximo UM pet, e um pet pertence a no máximo UM comedouro.
-- dispositivos.pet_id já é uma coluna única por linha (1 comedouro -> 1 pet). Este índice UNIQUE
-- garante o outro lado: o mesmo pet não pode estar em dois comedouros.
-- (O MySQL aceita vários NULL: comedouros sem pet continuam permitidos.)
-- Se der erro #1062 Duplicate entry, já existe um pet ligado a 2 comedouros: corrija antes, ex.:
--   UPDATE dispositivos SET pet_id = NULL WHERE id = <id do comedouro que sobrou>;
ALTER TABLE dispositivos ADD UNIQUE KEY uq_dispositivos_pet (pet_id);


-- ##################################################################
-- ## migracoes/07_dispositivos_simples.sql
-- ##################################################################

-- =====================================================================
-- 07: tabela "dispositivos" simplificada para a comunicação ESP32 <-> PHP
--
-- A tabela dispositivos JÁ EXISTE (criada pelo schema.sql), então aqui só AJUSTAMOS:
--   mac_address    -> codigo_mac_esp   (o ESP32 se identifica pelo MAC)
--   ultimo_contato -> ultimo_ping      (Online = ping recente)
--   token_hash     -> removido         (sem token: identificação só pelo MAC)
-- As demais colunas já atendem a tela: id, usuario_id, pet_id, nome, nivel_racao (0-100).
--
-- Rode com o banco comedouro_iot selecionado.
-- Se der "#1054 Unknown column 'mac_address'" a migração já foi aplicada (ou a tabela foi
-- criada de outro jeito): confira a estrutura da tabela ou use o CREATE TABLE do fim do arquivo.
-- =====================================================================
ALTER TABLE dispositivos
    CHANGE COLUMN mac_address    codigo_mac_esp CHAR(17) NOT NULL,   -- ex.: AA:BB:CC:DD:EE:FF
    CHANGE COLUMN ultimo_contato ultimo_ping    DATETIME NULL,       -- gravado pelo PHP a cada sincronização
    DROP COLUMN token_hash;

-- ---------------------------------------------------------------------
-- DADOS PARA TESTAR (rode à mão, conforme precisar)
-- ---------------------------------------------------------------------
-- 1) Colocar o MAC REAL do seu ESP32 no comedouro de teste (o MAC aparece no Monitor Serial):
-- UPDATE dispositivos SET codigo_mac_esp = '24:6F:28:AA:BB:CC' WHERE id = 1;
--
-- 2) Criar um pedido de "alimentar" pendente (50 g) para o comedouro 1:
-- INSERT INTO historico (dispositivo_id, pet_id, origem, porcao_g, status)
-- VALUES (1, 1, 'manual', 50, 'pendente');
--
-- 3) Conferir o que aconteceu:
-- SELECT id, nome, codigo_mac_esp, nivel_racao, ultimo_ping FROM dispositivos;
-- SELECT id, porcao_g, status, enviado_em, concluido_em FROM historico ORDER BY id DESC;

-- ---------------------------------------------------------------------
-- SÓ SE a tabela NÃO existir (banco novo, sem rodar o schema.sql): estrutura final
-- ---------------------------------------------------------------------
-- CREATE TABLE dispositivos (
--     id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
--     usuario_id      INT UNSIGNED NOT NULL,
--     pet_id          INT UNSIGNED NULL,                 -- 1 comedouro = 1 pet
--     nome            VARCHAR(80) NOT NULL,              -- ex.: Comedouro 01
--     codigo_mac_esp  CHAR(17) NOT NULL,
--     nivel_racao     TINYINT UNSIGNED NULL,             -- 0 a 100 (%)
--     ultimo_ping     DATETIME NULL,
--     ativo           TINYINT(1) NOT NULL DEFAULT 1,
--     criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     PRIMARY KEY (id),
--     UNIQUE KEY uq_dispositivos_mac (codigo_mac_esp),
--     UNIQUE KEY uq_dispositivos_pet (pet_id),
--     CONSTRAINT chk_nivel_racao CHECK (nivel_racao IS NULL OR nivel_racao <= 100),
--     CONSTRAINT fk_dispositivos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
--     CONSTRAINT fk_dispositivos_pet FOREIGN KEY (pet_id) REFERENCES pets (id) ON DELETE SET NULL
-- ) ENGINE=InnoDB;


-- ##################################################################
-- ## migracoes/08_telas_configuracao_relatorios.sql
-- ##################################################################

-- 08: campos usados pelas telas Configuração e Relatórios
-- (Pré-requisito: migrações 05, 06 e 07 já importadas. Rode com o banco comedouro_iot selecionado.)
--
-- Telas e as tabelas que elas usam:
--   Dispositivos  -> dispositivos (+ pets)        [já existe, ajustada na 07]
--   Alimentação   -> agendamentos (horários) e historico (botão "Liberar agora")  [já existem]
--   Relatórios    -> historico (refeições servidas por pet e por dia)             [já existe]
--   Configuração  -> usuarios (colunas novas abaixo)

ALTER TABLE usuarios
    ADD COLUMN telefone        VARCHAR(20) NULL AFTER email,
    ADD COLUMN foto            VARCHAR(100) NULL AFTER telefone,                 -- foto de perfil (nome do arquivo)
    ADD COLUMN notif_horarios  TINYINT(1) NOT NULL DEFAULT 1 AFTER foto,         -- avisos antes das refeições
    ADD COLUMN notif_alertas   TINYINT(1) NOT NULL DEFAULT 1 AFTER notif_horarios, -- consumo e nível de ração
    ADD COLUMN idioma          VARCHAR(5) NOT NULL DEFAULT 'pt-BR' AFTER notif_alertas;

-- Acelera as consultas do relatório (por pet, status e data)
ALTER TABLE historico
    ADD INDEX idx_historico_relatorio (pet_id, status, enviado_em);


-- ##################################################################
-- ## migracoes/09_sensores_e_consumo.sql
-- ##################################################################

-- =====================================================================
-- 09: sensor ultrassônico (nível do reservatório) + célula de carga/HX711 (balança da tigela)
--     e separação entre ração LIBERADA e ração CONSUMIDA.
-- (Pré-requisito: migrações 05 a 08 já importadas. Rode com o banco comedouro_iot selecionado.)
--
-- COMO OS DADOS CHEGAM:
--   O ESP32 manda só medidas "cruas" a cada 10 s:
--       distancia_cm   -> sensor ultrassônico (distância do sensor até a ração)
--       peso_tigela_g  -> balança (peso atual da tigela, em gramas)
--   O SERVIDOR faz as contas:
--       nível em %  = conversão da distância usando a calibração do comedouro (colunas abaixo)
--       liberada    = peso da tigela DEPOIS de abrir a comporta  -  peso ANTES
--       consumida   = quanto o peso da tigela caiu depois de liberar (o que o pet comeu)
--
-- ENTENDENDO A TABELA historico (uma linha por refeição):
--   porcao_g       = quantidade PEDIDA/programada (o que o sistema mandou liberar)
--   peso_antes_g   = peso da tigela no momento do pedido
--   peso_depois_g  = peso da tigela logo depois de liberar
--   liberado_g     = o que a balança MEDIU que caiu na tigela (peso_depois - peso_antes)
--   consumido_g    = o que o pet comeu (sobe junto com a queda do peso, até o fim da janela de 60 min)
--   Sem balança (valores NULL) o relatório usa porcao_g como "liberada" e mostra "—" no consumo.
-- =====================================================================

ALTER TABLE dispositivos
    ADD COLUMN distancia_cm          DECIMAL(5,1) NULL AFTER nivel_racao,        -- última leitura do ultrassônico
    ADD COLUMN peso_tigela_g         DECIMAL(7,1) NULL AFTER distancia_cm,       -- última leitura da balança
    ADD COLUMN reservatorio_cheio_cm DECIMAL(5,1) NOT NULL DEFAULT 5.0  AFTER peso_tigela_g,   -- distância com o reservatório CHEIO (100%)
    ADD COLUMN reservatorio_vazio_cm DECIMAL(5,1) NOT NULL DEFAULT 25.0 AFTER reservatorio_cheio_cm; -- distância com o reservatório VAZIO (0%)

ALTER TABLE historico
    ADD COLUMN peso_antes_g  DECIMAL(7,1) NULL AFTER porcao_g,
    ADD COLUMN peso_depois_g DECIMAL(7,1) NULL AFTER peso_antes_g,
    ADD COLUMN liberado_g    DECIMAL(7,1) NULL AFTER peso_depois_g,
    ADD COLUMN consumido_g   DECIMAL(7,1) NULL AFTER liberado_g;

-- Conferir depois dos testes:
-- SELECT id, porcao_g, peso_antes_g, peso_depois_g, liberado_g, consumido_g, status FROM historico ORDER BY id DESC;
-- SELECT nome, nivel_racao, distancia_cm, peso_tigela_g, ultimo_ping FROM dispositivos;


-- ##################################################################
-- ## migracoes/10_completar_porcao_e_notificacoes.sql
-- ##################################################################

-- =====================================================================
-- 10: regra "completar a porção" (evita desperdício) + notificações
-- (Pré-requisito: migrações 05 a 09 já importadas. Rode com o banco comedouro_iot selecionado.)
--
-- REGRA DE NEGÓCIO: a quantidade configurada (ex.: 90 g) é a quantidade que deve HAVER na tigela.
--   Tigela vazia (até ~5 g)  -> libera os 90 g
--   Tigela com 10 g          -> libera só 80 g
--   Tigela já com 88 g (dentro da margem de 5 g) -> não libera nada (status 'dispensado')
-- historico.porcao_g continua sendo a quantidade PEDIDA (o alvo). Novas colunas:
--   enviado_g         = gramas mandadas ao motor, já descontando o que havia na tigela
--   consumo_avaliado  = 1 depois que o sistema checou se sobrou muita ração (alerta de consumo)
-- =====================================================================

ALTER TABLE historico
    MODIFY status ENUM('pendente','enviado','concluido','falha','dispensado') NOT NULL DEFAULT 'pendente',
    ADD COLUMN enviado_g SMALLINT UNSIGNED NULL AFTER porcao_g,
    ADD COLUMN consumo_avaliado TINYINT(1) NOT NULL DEFAULT 0 AFTER consumido_g;

-- Para avisar "dispositivo offline" uma única vez por queda
ALTER TABLE dispositivos
    ADD COLUMN offline_avisado TINYINT(1) NOT NULL DEFAULT 0 AFTER ultimo_ping;

-- ---------------------------------------------------------------------
-- Notificações (tela Notificações). Os avisos respeitam as preferências da tela Configuração:
--   notif_horarios -> alimentacao (realizada/dispensada) e proxima (antes da refeição)
--   notif_alertas  -> consumo, nivel (ração baixa) e offline
--   relatorio      -> sempre (informativo)
-- "chave" evita avisos repetidos (ex.: 'consumo:123'); é única por usuário.
-- ---------------------------------------------------------------------
CREATE TABLE notificacoes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id      INT UNSIGNED NOT NULL,
    dispositivo_id  INT UNSIGNED NULL,
    tipo            ENUM('consumo','alimentacao','relatorio','offline','nivel','proxima') NOT NULL,
    titulo          VARCHAR(80) NOT NULL,
    mensagem        VARCHAR(255) NOT NULL,
    url             VARCHAR(200) NULL,            -- para onde o aviso leva ao clicar
    chave           VARCHAR(80) NULL,
    lida            TINYINT(1) NOT NULL DEFAULT 0,
    criado_em       DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notificacoes_chave (usuario_id, chave),
    KEY idx_notificacoes_lista (usuario_id, lida, criado_em),
    CONSTRAINT fk_notificacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_notificacoes_dispositivo FOREIGN KEY (dispositivo_id) REFERENCES dispositivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- ##################################################################
-- ## migracoes/11_comporta_servo.sql
-- ##################################################################

-- =====================================================================
-- 11: comporta por servo - a quantidade pedida vira GRAUS de abertura
-- (Pré-requisito: migrações 05 a 10 já importadas. Rode com o banco comedouro_iot selecionado.)
--
-- COMO FUNCIONA:
--   O servo abre a comporta X graus, segura por um tempo curto e FECHA NA HORA (uma "abertura").
--   Quanto cai em uma abertura de 90° depende de quanta ração há no reservatório:
--     reservatório quase vazio -> servo_g90_vazio (padrão 100 g)
--     reservatório cheio       -> servo_g90_cheio (padrão 150 g)
--   O servidor interpola entre os dois pelo nível atual (%), calcula o ângulo e, se o pedido for maior
--   que uma abertura de 90°, divide em várias aberturas. O ESP32 só executa e, com balança, corrige.
--
-- historico guarda o plano usado em cada refeição (angulo_graus e pulsos) para consulta.
-- =====================================================================

ALTER TABLE dispositivos
    ADD COLUMN servo_g90_vazio     DECIMAL(6,1) NOT NULL DEFAULT 100.0 AFTER reservatorio_vazio_cm, -- g por abertura de 90° com reservatório quase vazio
    ADD COLUMN servo_g90_cheio     DECIMAL(6,1) NOT NULL DEFAULT 150.0 AFTER servo_g90_vazio,       -- g por abertura de 90° com reservatório cheio
    ADD COLUMN servo_abertura_ms   SMALLINT UNSIGNED NOT NULL DEFAULT 600 AFTER servo_g90_cheio;     -- tempo com a comporta aberta em cada abertura

ALTER TABLE historico
    ADD COLUMN angulo_graus TINYINT UNSIGNED NULL AFTER enviado_g,   -- abertura usada (graus)
    ADD COLUMN pulsos       TINYINT UNSIGNED NULL AFTER angulo_graus; -- quantas aberturas foram planejadas

-- Conferir:
-- SELECT id, porcao_g, enviado_g, angulo_graus, pulsos, liberado_g FROM historico ORDER BY id DESC LIMIT 10;

