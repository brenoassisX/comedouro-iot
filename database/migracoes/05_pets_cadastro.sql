-- MÓDULO PETS: campos do formulário "Cadastro do pet" + foto
-- (a tabela pets já tinha: id, usuario_id, nome, especie, raca, peso_kg, porcao_padrao_g, criado_em)
ALTER TABLE pets
    ADD COLUMN porte        ENUM('pequeno','medio','grande') NOT NULL DEFAULT 'pequeno' AFTER raca,
    ADD COLUMN idade_meses  SMALLINT UNSIGNED NULL AFTER porte,     -- guardamos em MESES (a tela converte anos -> meses)
    ADD COLUMN observacoes  VARCHAR(500) NULL AFTER peso_kg,
    ADD COLUMN foto         VARCHAR(100) NULL AFTER observacoes,    -- só o NOME do arquivo (ex.: 3f9a...c1.jpg)
    ADD CONSTRAINT chk_pets_peso CHECK (peso_kg IS NULL OR peso_kg > 0);

-- Observação: pets.porcao_padrao_g (já existente) passa a receber a "quantidade por refeição"
-- calculada no cadastro, para o comedouro usar como porção padrão.
