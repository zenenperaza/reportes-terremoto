# Asignación de períodos históricos — 29/09/2026

Archivo a importar: `asignar_periodos_historicos_20260929.sql`.

Aplicado y verificado en `asonacop_registros` local. No se ejecutó en el servidor.

| Fecha de atención (inclusive) | Período | Registros actualizados | Total final local |
| --- | --- | ---: | ---: |
| 01/06/2026–27/07/2026 | Julio 2026 | 443 | 443 |
| 28/07/2026–31/08/2026 | Agosto 2026 | 183 | 183 |
| Desde 01/09/2026 | Septiembre 2026 | 137 | 139 |

Se actualizaron 763 registros sin período. Los otros 2 ya tenían Septiembre y
no se tocaron. Permanecen 765 registros y 4.497 beneficiarios. Se verificaron
huellas SHA-256 de todos los demás campos de registros, de los beneficiarios y
de la configuración antes de confirmar la transacción: permanecieron idénticos.
Julio y Agosto se agregaron al administrador de períodos como abiertos; no se
cambió el período actual ni el estado de ningún período existente.

## Importar en phpMyAdmin de cPanel

1. Exporte un respaldo completo de la base del servidor y evite ediciones durante
   la importación.
2. Seleccione la base de datos de SIA (`asonacop_registros` en la copia revisada).
3. En **Importar**, seleccione el archivo `.sql` e impórtelo completo como SQL UTF-8.
   No necesita ejecutar `TemporaryMaintenanceController` para esta asignación.
4. Revise el resumen final. En la primera ejecución sobre la misma copia:
   - `periodos_abiertos_debe_ser_1`: **1**.
   - `actualizados_en_esta_ejecucion`: **763**.
   - `objetivos_debe_ser_763`: **763**.
   - `objetivos_con_periodo_correcto_debe_ser_763`: **763**.
5. Verifique los filtros de Julio, Agosto y Septiembre en los informes.

Si ya se había ejecutado, el contador de actualizados será 0 y el de objetivos
correctos seguirá siendo 763. La repetición se comprobó localmente sin cambios
adicionales ni alteraciones del respaldo original.

## Protecciones

- Solo incluye los IDs y fechas de la copia revisada; no modifica futuros registros.
- Solo asigna períodos donde el valor anterior sigue siendo NULL.
- Comprueba fecha de atención, fecha de creación y última modificación. Si un
  registro cambió desde la copia local, se omite en lugar de sobrescribirlo.
- Si Julio, Agosto o Septiembre están cerrados, no realiza la asignación. No abre
  períodos automáticamente.
- Guarda el valor anterior antes de actualizar en
  `sia_respaldo_periodos_20260929`. Conserve esa tabla para una eventual reversión.
- No modifica fechas, nombres, indicadores, estado reportado ni beneficiarios.
- Un resumen con menos de 763 objetivos correctos requiere revisar los cambios
  o cierres del servidor; no fuerce una actualización sin comprobarlos.

SQL verificado con MariaDB 10.4.32. SHA-256:
`fa106579d183aab7e4f34aa23307d29b1ef9425f81b3c6c7d39470265b589546`.

## Corrección del error #1267 de cotejamientos

El archivo inicial comparaba columnas de período con cotejamientos distintos.
La captura del error corresponde al resumen final, después del COMMIT. Por eso
los cambios podrían haberse confirmado a pesar del error del resumen.

Ejecute primero `verificar_periodos_historicos_20260929.sql` en la misma base.
Es de solo lectura y no requiere la tabla temporal de la importación anterior.
Si devuelve 763 respaldados con período correcto, no necesita volver a asignar.
Si devuelve menos, conserve el resultado y revise las diferencias antes de forzar
cualquier modificación.

El archivo de asignación de esta carpeta está corregido: compara los códigos de
período byte a byte y no necesita cambiar el cotejamiento de tablas existentes.
Sus consultas de comprobación ahora se evalúan antes del COMMIT y no reutilizan
la tabla temporal dentro de una misma consulta. Se probó el archivo completo,
incluido el resumen, con tablas temporales unicode_ci y general_ci: 763 cambios
en la primera ejecución y 0 al repetir; ambas terminaron con 763 objetivos correctos.
La prueba reprodujo el #1267 original sin modificar tablas persistentes.
