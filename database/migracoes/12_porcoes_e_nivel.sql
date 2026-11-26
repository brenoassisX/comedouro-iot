-- =====================================================================
-- 12: porções Baixa/Média/Alta (editáveis pelo usuário) e nova calibração da comporta
-- (Pré-requisito: migrações 05 a 11 já importadas. Rode com o banco comedouro_iot selecionado.)
--
-- 1) Nova calibração do servo: a 40° a comporta libera ~25 g  ->  a 90° ~56 g (proporcional).
--    Os dois valores (reservatório vazio/cheio) ficam iguais; dá para ajustar em Dispositivos > Editar.
-- 2) Tabela porcoes: cada usuário tem 3 tamanhos (baixa, media, alta) com NOME e GRAMAS editáveis.
--    As linhas são criadas sozinhas na primeira vez que o usuário abre a tela Alimentação.
-- =====================================================================

ALTER TABLE dispositivos
    MODIFY servo_g90_vazio DECIMAL(6,1) NOT NULL DEFAULT 56.3,
    MODIFY servo_g90_cheio DECIMAL(6,1) NOT NULL DEFAULT 56.3;

UPDATE dispositivos SET servo_g90_vazio = 56.3, servo_g90_cheio = 56.3;

CREATE TABLE IF NOT EXISTS porcoes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED NOT NULL,
    chave       ENUM('baixa','media','alta') NOT NULL,
    nome        VARCHAR(30) NOT NULL,                 -- termo mostrado ao usuário (ex.: "Baixa", "Petisco")
    gramas      SMALLINT UNSIGNED NOT NULL,           -- quantidade liberada neste tamanho
    PRIMARY KEY (id),
    UNIQUE KEY uq_porcoes_usuario_chave (usuario_id, chave),
    CONSTRAINT fk_porcoes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Conferir:
-- SELECT * FROM porcoes;
-- SELECT id, servo_g90_vazio, servo_g90_cheio FROM dispositivos;
