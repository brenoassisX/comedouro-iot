-- REGRA DE NEGÓCIO: cada comedouro tem no máximo UM pet, e um pet pertence a no máximo UM comedouro.
-- dispositivos.pet_id já é uma coluna única por linha (1 comedouro -> 1 pet). Este índice UNIQUE
-- garante o outro lado: o mesmo pet não pode estar em dois comedouros.
-- (O MySQL aceita vários NULL: comedouros sem pet continuam permitidos.)
-- Se der erro #1062 Duplicate entry, já existe um pet ligado a 2 comedouros: corrija antes, ex.:
--   UPDATE dispositivos SET pet_id = NULL WHERE id = <id do comedouro que sobrou>;
ALTER TABLE dispositivos ADD UNIQUE KEY uq_dispositivos_pet (pet_id);
