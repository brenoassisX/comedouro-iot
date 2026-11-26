-- 15: calibração medida (1 abertura de 30° = 20 g) e porções Pequena/Média/Grande = 20/40/60 g.
-- Rode com o banco comedouro_iot selecionado (depois da 12, 13 e 14; se a 14 não foi importada, tudo bem).
ALTER TABLE dispositivos
    MODIFY servo_g90_vazio DECIMAL(6,1) NOT NULL DEFAULT 60.0,
    MODIFY servo_g90_cheio DECIMAL(6,1) NOT NULL DEFAULT 60.0;
UPDATE dispositivos SET servo_g90_vazio = 60.0, servo_g90_cheio = 60.0;

UPDATE porcoes SET nome = 'Pequena', gramas = 20 WHERE chave = 'baixa';
UPDATE porcoes SET nome = 'Média',   gramas = 40 WHERE chave = 'media';
UPDATE porcoes SET nome = 'Grande',  gramas = 60 WHERE chave = 'alta';
