-- SOLO LECTURA. Seleccione la base de SIA y ejecute completo.
-- No depende de tablas temporales ni de la sesion de la importacion anterior.
-- El SQL original hacia COMMIT antes del resumen que dio error #1267.

SELECT DATABASE() AS base_de_datos,
    COUNT(*) AS registros_respaldados_debe_ser_763,
    COALESCE(SUM(r.id IS NOT NULL
        AND r.report_date = b.fecha_atencion
        AND (r.created_at <=> b.registro_creado)
        AND (BINARY r.reporting_period <=> BINARY b.periodo_nuevo)), 0)
        AS respaldados_con_periodo_correcto_debe_ser_763,
    COALESCE(SUM(r.id IS NULL), 0) AS registros_que_ya_no_existen,
    COALESCE(SUM(r.id IS NOT NULL AND r.reporting_period IS NULL), 0)
        AS respaldados_aun_sin_periodo
FROM sia_respaldo_periodos_20260929 AS b
LEFT JOIN reports AS r ON r.id = b.report_id;

SELECT reporting_period AS periodo, COUNT(*) AS registros
FROM reports
GROUP BY reporting_period
ORDER BY reporting_period;
