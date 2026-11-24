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
