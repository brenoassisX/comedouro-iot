-- MÓDULO 1: Recuperação de senha
-- reset_token guarda o SHA-256 do token enviado por e-mail (nunca o token em claro).
ALTER TABLE usuarios
    ADD COLUMN reset_token   CHAR(64) NULL AFTER senha_hash,
    ADD COLUMN reset_expires DATETIME NULL AFTER reset_token,
    ADD INDEX idx_usuarios_reset_token (reset_token);
