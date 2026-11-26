-- 16: preço do kg da ração, informado no cadastro do pet e usado no custo dos relatórios/PDF.
-- Rode com o banco comedouro_iot selecionado. Pets antigos ficam sem preço (NULL) = relatório mostra "—".
ALTER TABLE pets
    ADD COLUMN preco_kg_racao DECIMAL(8,2) NULL AFTER peso_kg;   -- R$ por kg (opcional)

-- Conferir:
-- SELECT id, nome, preco_kg_racao FROM pets;
