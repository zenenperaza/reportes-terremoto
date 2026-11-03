# Proyectos en el listado de registros

En `/reportes` el primer filtro es **Proyectos**, con selección múltiple, búsqueda y la acción **Seleccionar todos los proyectos**. Esta acción selecciona los proyectos disponibles y permite quitar cualquiera después; no envía un identificador ficticio al servidor.

Sin selección se muestran todos los registros permitidos, incluidos los históricos sin proyecto. Al elegir proyectos, solo se muestran registros de esos proyectos. El selector también incluye proyectos inactivos con historial visible y proyectos asignados al usuario.

La selección se aplica al listado por registro, la DataTable de beneficiarios y las exportaciones existentes, incluyendo el CSV del encabezado. Se combina con los demás filtros sin cambiar permisos, visibilidad de equipos ni acceso a datos personales. Los filtros se aplican con **Aplicar filtros**.

No modifica `/reportes/nuevo`, no cambia beneficiarios ni requiere migración. Para desplegar: controlador `ReportController`, vista `reports/index`, CSS `report-datatable.css`, nuevo JavaScript `record-project-filter.js` y renovar las cachés habituales.
