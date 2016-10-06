--
-- Actualización al día 5 de octubre de 2016
--

BEGIN;

ALTER TABLE dte_emitido ADD anulado BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE dte_intercambio_resultado_dte ALTER glosa DROP NOT NULL;
ALTER TABLE dte_tipo
    ADD categoria CHAR(1) NOT NULL DEFAULT 'T' CHECK (categoria IN ('T', 'I')),
    ADD enviar BOOLEAN NOT NULL DEFAULT false CHECK ((enviar = true AND electronico = true) OR enviar = false),
    ADD cedible BOOLEAN NOT NULL DEFAULT false CHECK ((categoria = 'T' AND cedible = true) OR cedible = false),
    ADD operacion CHAR(1) CHECK ((categoria = 'T' AND operacion IN ('S', 'R')) OR (categoria != 'T' AND operacion IS NULL))
;

COMMIT;