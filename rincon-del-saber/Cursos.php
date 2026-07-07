<?php
/**
 * Cursos.php
 * Lista fija de cursos para los que un profesor puede recomendar un
 * libro. Antes salía de "SELECT DISTINCT curso FROM estudiante", así
 * que si todavía no había ningún alumno cargado en un curso (o solo
 * había en 3ro), ese curso ni aparecía en el desplegable — y si no
 * había ninguno, el desplegable quedaba vacío y el modal de
 * "Recomendar libro" no se podía confirmar nunca (el campo "Curso" es
 * obligatorio y no había ninguna opción para elegir).
 *
 * Ahora es una lista fija de 1ro a 4to, independiente de qué alumnos
 * haya cargados. Para sumar más cursos (por ejemplo con división,
 * "1ro A", "1ro B", etc.) alcanza con agregarlos acá; se van a ver
 * automáticamente en Detalle.php y se van a aceptar en
 * RecomendarLibro.php sin tocar nada más.
 */
$CURSOS = ['1ro', '2do', '3ro', '4to'];