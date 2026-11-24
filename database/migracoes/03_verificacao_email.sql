-- MÓDULO 3: confirmação de e-mail no cadastro
ALTER TABLE usuarios
    ADD COLUMN email_verificado_em DATETIME NULL AFTER email,
    ADD COLUMN verificacao_token   CHAR(64) NULL AFTER email_verificado_em,   -- SHA-256 do token enviado
    ADD COLUMN verificacao_expira  DATETIME NULL AFTER verificacao_token,
    ADD INDEX idx_usuarios_verificacao_token (verificacao_token);

-- Contas já existentes ficam NÃO verificadas de propósito (precisam confirmar via
-- "Reenviar confirmação" ou recuperar a senha). Em ambiente de DESENVOLVIMENTO, se quiser
-- pular isso para os seus utilizadores de teste, rode:
-- UPDATE usuarios SET email_verificado_em = NOW() WHERE email_verificado_em IS NULL;
