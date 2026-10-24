-- MÓDULO 4: limite de tentativas (rate limiting) por IP e por e-mail
-- "chave" é o SHA-256 de "ip:1.2.3.4" ou "email:fulano@x.com" (não guardamos e-mails em claro aqui).
CREATE TABLE limite_requisicoes (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    acao       VARCHAR(30) NOT NULL,              -- login | cadastro | reset | reenvio
    chave      CHAR(64) NOT NULL,
    criado_em  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_limite_busca (acao, chave, criado_em)
) ENGINE=InnoDB;
