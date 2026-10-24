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
