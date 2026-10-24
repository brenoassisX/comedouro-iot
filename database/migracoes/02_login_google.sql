-- MÓDULO 2: Login com Google
-- Contas criadas via Google não têm senha.
ALTER TABLE usuarios MODIFY senha_hash VARCHAR(255) NULL;
