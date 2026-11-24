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
