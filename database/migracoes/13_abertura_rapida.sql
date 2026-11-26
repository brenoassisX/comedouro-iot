-- 13: aberturas rápidas de 30° (tempo aberta padrão 300 ms). Rode com o banco comedouro_iot selecionado.
ALTER TABLE dispositivos MODIFY servo_abertura_ms SMALLINT UNSIGNED NOT NULL DEFAULT 300;
UPDATE dispositivos SET servo_abertura_ms = 300;
