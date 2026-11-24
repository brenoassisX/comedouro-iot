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
