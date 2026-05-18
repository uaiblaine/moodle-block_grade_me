<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Grade Me block language strings.
 *
 * @package    block_grade_me
 * @copyright  2026 block_grade_me contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Califícame';
$string['title'] = 'Califícame';
$string['datetime'] = '%B %d, %l:%M %p';
$string['excess'] = 'Más de {$a->maxcourses} cursos tienen elementos pendientes — mostrando los primeros {$a->maxcourses}.';
$string['nothing'] = 'No hay elementos pendientes de calificar en tus cursos.';
$string['link_gradebook_icon'] = 'Ir a calificaciones de {$a->course_name}…';
$string['link_gradebook'] = 'Ir a {$a->course_name}…';
$string['link_mod_img'] = 'Ir a {$a->mod_name} en calificaciones…';
$string['link_mod'] = 'Ir a {$a->mod_name}';
$string['link_grade_img'] = 'Evaluar tarea…';
$string['link_user_profile'] = 'Perfil de {$a->first_name}…';
$string['alt_gradebook'] = 'Ir a calificaciones de {$a->course_name}…';
$string['alt_mod'] = 'Ir a {$a->mod_name} en calificaciones…';
$string['alt_mark'] = 'check';
$string['settings_maxcourses'] = 'Número máximo de cursos para mostrar';
$string['settings_configmaxcourses'] = 'Configure el número máximo de cursos para mostrar. Un número alto afecta al rendimiento de la web.';
$string['settings_adminviewall'] = 'Los Administradores ven todo';
$string['settings_configadminviewall'] = 'Permitir a los administradores el derecho a ver todo el trabajo sin evaluar -no sólo de los cursos donde tienen un';
$string['settings_enablepre'] = 'Mostrar';
$string['settings_configenablepre'] = '¿Debería el bloque Califícame mostrar actividades sin evaluar del módulo "{$a->plugin_name}" ?';
$string['grade_me:addinstance'] = 'Añadir un nuevo bloque Califícame';
$string['grade_me:myaddinstance'] = 'Añadir un nuevo bloque Califícame a mi página personal';
$string['grade_me:resetdata'] = 'Reiniciar los datos de SLA / capacidad de respuesta';
$string['grade_me:viewdashboard'] = 'Ver la página Panel del Profesor';
$string['grade_me:viewresponsiveness'] = 'Ver la sección Capacidad de Respuesta de Calificación';
$string['grade_me:viewschoolaverage'] = 'Ver la mediana del centro y la comparación del 10% más rápido';

$string['badge_loading'] = 'Cargando recuento de pendientes…';
$string['badge_unknown'] = 'Recuento no disponible';
$string['ws_get_gradeable_count'] = 'Cuenta y lista los elementos pendientes de calificar para un tipo de módulo en un curso.';
$string['staledatanotice'] = 'Los recuentos se actualizan cada 15 minutos.';
$string['lastsynced'] = 'Última sincronización: {$a->time}';
$string['lastsynced_pending'] = 'Última sincronización: —';

// Configuración de la sección Capacidad de Respuesta de Calificación.
$string['settings_responsiveness_heading'] = 'Capacidad de Respuesta de Calificación';
$string['settings_config_responsiveness_heading'] = 'Configuración de la sección de SLA / capacidad de respuesta, el Panel del Profesor y el Informe Detallado.';
$string['settings_show_responsiveness'] = 'Mostrar sección de capacidad de respuesta';
$string['settings_config_show_responsiveness'] = 'Añade la sección Capacidad de Respuesta de Calificación bajo la lista de actividades del bloque.';
$string['settings_show_school_comparison'] = 'Mostrar comparación del centro';
$string['settings_config_show_school_comparison'] = 'Incluye la fila de comparación "Mediana del centro" y "10% más rápido" dentro de cada tarjeta de grupo.';
$string['settings_showhiddenactivities'] = 'Mostrar elementos de actividades ocultas';
$string['settings_configshowhiddenactivities'] = 'Muestra elementos de actividades cuyo módulo de curso está oculto (cm.visible = 0). Independiente del ajuste de cursos ocultos.';
$string['settings_sla_thresholds'] = 'Umbrales de SLA (horas)';
$string['settings_config_sla_thresholds'] = 'Lista separada por comas con tres enteros en orden creciente: el límite superior de los buckets Excelente, Bueno y Regular. Cualquier valor por encima del tercero se considera Crítico.';
$string['settings_sla_goal'] = 'Objetivo de SLA (horas)';
$string['settings_config_sla_goal'] = 'Marca en horas mostrada como línea de objetivo discontinua en la barra de SLA. Normalmente igual al umbral Excelente.';
$string['settings_sla_drain_batch'] = 'Tamaño de lote de la tarea de drenado';
$string['settings_config_sla_drain_batch'] = 'Número máximo de tuplas (curso, grupo, tipo) que la tarea de drenado recalcula en una ejecución de cron.';
$string['settings_sla_backfill_chunk'] = 'Tamaño de lote de la tarea de reconstrucción';
$string['settings_config_sla_backfill_chunk'] = 'Número máximo de filas de envío que la tarea de reconstrucción procesa por ejecución de cron antes de ceder.';
$string['settings_report_pagesize'] = 'Tamaño de página del informe';
$string['settings_config_report_pagesize'] = 'Número de envíos por página en el Informe Detallado.';
$string['settings_reset_sla'] = 'Reiniciar datos de capacidad de respuesta';
$string['settings_config_reset_sla'] = 'Trunca las tablas de ledger, rollup, tendencia y cola de SLA y encola una reconstrucción completa. <a href="{$a}">Reiniciar ahora</a>.';
$string['reset_sla_done'] = 'Datos de capacidad de respuesta reiniciados; la reconstrucción se ejecutará en el próximo ciclo de cron.';
$string['reset_sla_confirm'] = 'Esto truncará las tablas de SLA y reconstruirá desde {assign_submission}. ¿Continuar?';

// Nombres de tareas programadas.
$string['task_sla_drain'] = 'Drenar la cola de SLA (recalcular agregaciones)';
$string['task_sla_trend_recompute'] = 'Recalcular la tendencia de SLA de 30 días';
$string['task_sla_site_stats'] = 'Recalcular las estadísticas de SLA del sitio';
$string['task_sla_backfill'] = 'Reconstruir el ledger de SLA desde cero';
$string['task_sla_recompute_one'] = 'Recalcular una tupla de SLA (adhoc, activado tras calificar)';

// Sección de Capacidad de Respuesta y tarjeta.
$string['responsiveness'] = 'Capacidad de Respuesta de Calificación';
$string['responsiveness_loading'] = 'Cargando datos de capacidad de respuesta…';
$string['responsiveness_no_groups'] = 'No perteneces a ningún grupo en este curso.';
$string['responsiveness_load_failed'] = 'Error al cargar los datos. Inténtalo de nuevo más tarde.';
$string['typical_response'] = 'Respuesta típica';
$string['within_90'] = '90% en menos de';
$string['longest_wait'] = 'Mayor espera';
$string['school_median'] = 'Mediana del centro';
$string['top_10'] = '10% más rápido';
$string['pending'] = 'Pendientes';
$string['critical'] = 'Críticos';
$string['no_rule'] = 'Sin regla';
$string['rule'] = 'Regla';
$string['bucket_excellent'] = 'Excelente';
$string['bucket_good'] = 'Bueno';
$string['bucket_regular'] = 'Regular';
$string['bucket_critical'] = 'Crítico';
$string['open_dashboard'] = 'Abrir Panel del Profesor';
$string['last_30_days'] = 'Últimos 30 días';
$string['compare_you'] = 'Tú';
$string['compare_top10'] = '10% más rápido';
$string['activities_open_close'] = 'Actividades · regla de apertura/cierre';
$string['goal_label'] = 'Objetivo';
$string['ws_get_responsiveness'] = 'Devuelve los datos de Capacidad de Respuesta de Calificación por grupo para un curso.';

// Panel del Profesor.
$string['dashboard_greeting'] = 'Hola, {$a->firstname}';
$string['dashboard_subtitle'] = '{$a->pending} envíos esperando · {$a->overgoal} por encima del objetivo';
$string['dashboard_active_courses'] = 'Cursos activos';
$string['dashboard_median_wait'] = 'Mediana de espera';
$string['dashboard_critical_total'] = 'Críticos';
$string['dashboard_grade_now'] = 'Calificar ahora · priorizado';
$string['dashboard_open_submission'] = 'Abrir envío';
$string['dashboard_courses_header'] = 'Tus cursos';
$string['dashboard_col_course'] = 'Curso';
$string['dashboard_col_median'] = 'Mediana';
$string['dashboard_col_trend'] = '30 días';
$string['dashboard_col_sla'] = 'SLA';
$string['dashboard_open_course'] = 'Abrir';
$string['dashboard_empty'] = 'Aún no perteneces a ningún grupo en tus cursos.';
$string['dashboard_sort_label'] = 'Ordenar por';
$string['dashboard_sort_apply'] = 'Aplicar';
$string['dashboard_sort_critical_first'] = 'Críticos primero';
$string['dashboard_sort_pending'] = 'Cantidad pendiente';
$string['dashboard_sort_median'] = 'Mediana de espera';
$string['dashboard_sort_title'] = 'Título del curso';
$string['dashboard_overdue'] = 'Atrasado';
$string['dashboard_closes_today'] = 'Cierra hoy';
$string['dashboard_closes_soon'] = 'Cierra pronto';

// Informe Detallado.
$string['report_title'] = 'Envíos Pendientes · Informe Detallado';
$string['report_breadcrumb_back'] = 'Volver al panel';
$string['report_filter_all'] = 'Todos';
$string['report_sort_waiting'] = 'Mayor espera';
$string['report_sort_activity'] = 'Actividad';
$string['report_sort_student'] = 'Nombre del estudiante';
$string['report_col_student'] = 'Estudiante';
$string['report_col_activity'] = 'Actividad';
$string['report_col_submitted'] = 'Enviado el';
$string['report_col_waiting'] = 'Esperando';
$string['report_grade_button'] = 'Calificar →';
$string['report_empty'] = 'Ningún envío coincide con los filtros actuales.';
$string['report_user_override'] = 'Excepción';
$string['report_user_override_tooltip'] = 'Este estudiante tiene una excepción individual en esta actividad.';
