-- =====================================================================
-- 09: sensor ultrassônico (nível do reservatório) + célula de carga/HX711 (balança da tigela)
--     e separação entre ração LIBERADA e ração CONSUMIDA.
-- (Pré-requisito: migrações 05 a 08 já importadas. Rode com o banco comedouro_iot selecionado.)
--
-- COMO OS DADOS CHEGAM:
--   O ESP32 manda só medidas "cruas" a cada 10 s:
--       distancia_cm   -> sensor ultrassônico (distância do sensor até a ração)
--       peso_tigela_g  -> balança (peso atual da tigela, em gramas)
--   O SERVIDOR faz as contas:
--       nível em %  = conversão da distância usando a calibração do comedouro (colunas abaixo)
--       liberada    = peso da tigela DEPOIS de abrir a comporta  -  peso ANTES
--       consumida   = quanto o peso da tigela caiu depois de liberar (o que o pet comeu)
--
-- ENTENDENDO A TABELA historico (uma linha por refeição):
--   porcao_g       = quantidade PEDIDA/programada (o que o sistema mandou liberar)
--   peso_antes_g   = peso da tigela no momento do pedido
--   peso_depois_g  = peso da tigela logo depois de liberar
--   liberado_g     = o que a balança MEDIU que caiu na tigela (peso_depois - peso_antes)
--   consumido_g    = o que o pet comeu (sobe junto com a queda do peso, até o fim da janela de 60 min)
--   Sem balança (valores NULL) o relatório usa porcao_g como "liberada" e mostra "—" no consumo.
-- =====================================================================

ALTER TABLE dispositivos
    ADD COLUMN distancia_cm          DECIMAL(5,1) NULL AFTER nivel_racao,        -- última leitura do ultrassônico
    ADD COLUMN peso_tigela_g         DECIMAL(7,1) NULL AFTER distancia_cm,       -- última leitura da balança
    ADD COLUMN reservatorio_cheio_cm DECIMAL(5,1) NOT NULL DEFAULT 5.0  AFTER peso_tigela_g,   -- distância com o reservatório CHEIO (100%)
    ADD COLUMN reservatorio_vazio_cm DECIMAL(5,1) NOT NULL DEFAULT 25.0 AFTER reservatorio_cheio_cm; -- distância com o reservatório VAZIO (0%)

ALTER TABLE historico
    ADD COLUMN peso_antes_g  DECIMAL(7,1) NULL AFTER porcao_g,
    ADD COLUMN peso_depois_g DECIMAL(7,1) NULL AFTER peso_antes_g,
    ADD COLUMN liberado_g    DECIMAL(7,1) NULL AFTER peso_depois_g,
    ADD COLUMN consumido_g   DECIMAL(7,1) NULL AFTER liberado_g;

-- Conferir depois dos testes:
-- SELECT id, porcao_g, peso_antes_g, peso_depois_g, liberado_g, consumido_g, status FROM historico ORDER BY id DESC;
-- SELECT nome, nivel_racao, distancia_cm, peso_tigela_g, ultimo_ping FROM dispositivos;
