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
