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

$string['alt_gradebook'] = 'Ir para o livro de notas de {$a->course_name}…';
$string['alt_mark'] = 'marcar';
$string['alt_mod'] = 'Ir para {$a->mod_name} no livro de notas…';
$string['badge_loading'] = 'Carregando contagem de itens não avaliados…';
$string['badge_unknown'] = 'Contagem indisponível';
$string['datetime'] = '%d/%m/%Y, %H:%M';
$string['excess'] = 'Mais de {$a->maxcourses} cursos possuem itens não avaliados — mostrando os primeiros {$a->maxcourses}.';
$string['expand'] = 'Recolher / Expandir tudo';
$string['grade_me:addinstance'] = 'Adicionar um novo bloco Grade Me';
$string['grade_me:myaddinstance'] = 'Adicionar um novo bloco Grade Me à página inicial';
$string['grade_me:resetdata'] = 'Resetar os dados de SLA / responsividade';
$string['grade_me:viewdashboard'] = 'Ver a página Painel do Professor';
$string['grade_me:viewresponsiveness'] = 'Ver a seção Responsividade de Correção';
$string['grade_me:viewschoolaverage'] = 'Ver a mediana da escola e o comparativo dos 10% melhores';
$string['grade_me_tools'] = 'Ferramentas';
$string['grade_me_tools_desc'] = '<p><a href="{$a}/blocks/grade_me/quiz_update_ngrade.php">Atualizar tentativas de questionário que precisam de avaliação</a></p>';
$string['lastsynced'] = 'Última sincronização: {$a->time}';
$string['lastsynced_pending'] = 'Última sincronização: —';
$string['link_grade_img'] = 'Avaliar tarefa…';
$string['link_gradebook'] = 'Ir para {$a->course_name}…';
$string['link_gradebook_icon'] = 'Ir para o livro de notas de {$a->course_name}…';
$string['link_mod'] = 'Ir para {$a->mod_name}';
$string['link_mod_img'] = 'Ir para {$a->mod_name} no livro de notas…';
$string['link_user_profile'] = 'Perfil de {$a->first_name}…';
$string['nothing'] = 'Não há itens não avaliados em seus cursos.';
$string['pluginname'] = 'Grade Me';
$string['pluginname-reset'] = 'Grade Me - redefinir tabela';
$string['privacy:metadata:block_grade_me_quiz_ngrade'] = 'Armazena informações em cache sobre questionários que precisam de notas.';
$string['privacy:metadata:block_grade_me_quiz_ngrade:attemptid'] = 'ID da tentativa';
$string['privacy:metadata:block_grade_me_quiz_ngrade:courseid'] = 'ID do curso';
$string['privacy:metadata:block_grade_me_quiz_ngrade:questionattemptstepid'] = 'Passo da tentativa da questão';
$string['privacy:metadata:block_grade_me_quiz_ngrade:quizid'] = 'ID do questionário';
$string['privacy:metadata:block_grade_me_quiz_ngrade:userid'] = 'ID do usuário';
$string['privacydata'] = 'Grade me';
$string['quiz_update_ngrade_complete'] = 'Atualização concluída';
$string['quiz_update_ngrade_success'] = 'Lista de tentativas de questionário atualizada com sucesso, atualmente existem {$a} questões que precisam de avaliação.';
$string['settings_adminviewall'] = 'Administradores veem tudo';
$string['settings_configadminviewall'] = 'Ative para dar aos administradores os direitos de ver todo o trabalho não avaliado — não apenas para cursos onde eles têm um papel de avaliador.';
$string['settings_configenablepre'] = 'O Grade Me deve mostrar atividades não avaliadas do módulo "{$a->plugin_name}"?';
$string['settings_configmaxage'] = 'A idade máxima dos itens avaliáveis, em dias, para mostrar. Itens mais antigos que este serão ocultados. Digite 0 para sem limite.';
$string['settings_configmaxcourses'] = 'Defina o número máximo de cursos não avaliados a serem exibidos. Definir este valor muito alto pode impactar o desempenho.';
$string['settings_configshowhidden'] = 'Ativar a exibição de itens para avaliar em cursos ocultos';
$string['settings_enablepre'] = 'Mostrar';
$string['settings_maxage'] = 'Idade Máxima';
$string['settings_maxcourses'] = 'Máximo de Cursos Exibidos';
$string['settings_showhidden'] = 'Itens de cursos ocultos mostrados';
$string['staledatanotice'] = 'As contagens são atualizadas a cada 15 minutos.';
$string['title'] = 'Grade Me';
$string['ws_get_gradeable_count'] = 'Conta e lista itens não avaliados para um tipo de módulo em um curso.';

// Configurações da seção Responsividade de Correção.
$string['settings_responsiveness_heading'] = 'Responsividade de Correção';
$string['settings_config_responsiveness_heading'] = 'Configurações da seção de SLA / responsividade, do Painel do Professor e do Relatório Detalhado.';
$string['settings_show_responsiveness'] = 'Mostrar seção de responsividade';
$string['settings_config_show_responsiveness'] = 'Acrescenta a seção Responsividade de Correção abaixo da lista de atividades do bloco.';
$string['settings_show_school_comparison'] = 'Mostrar comparativo escolar';
$string['settings_config_show_school_comparison'] = 'Inclui a linha de comparação "Mediana da escola" e "10% melhores" dentro de cada cartão de grupo.';
$string['settings_showhiddenactivities'] = 'Itens de atividades ocultas mostrados';
$string['settings_configshowhiddenactivities'] = 'Exibir itens de atividades cujo módulo de curso está oculto (cm.visible = 0). Independente da configuração de cursos ocultos.';
$string['settings_sla_thresholds'] = 'Limites de SLA (horas)';
$string['settings_config_sla_thresholds'] = 'Lista separada por vírgulas com três inteiros em ordem crescente: o limite superior das faixas Excelente, Bom e Regular. Qualquer valor acima do terceiro é considerado Crítico.';
$string['settings_sla_goal'] = 'Meta de SLA (horas)';
$string['settings_config_sla_goal'] = 'Marca em horas exibida como a linha tracejada de meta na barra de SLA. Normalmente igual ao limite Excelente.';
$string['settings_sla_drain_batch'] = 'Tamanho do lote da tarefa de drenagem';
$string['settings_config_sla_drain_batch'] = 'Quantidade máxima de tuplas (curso, grupo, tipo) recalculadas pela tarefa de drenagem em uma execução de cron.';
$string['settings_sla_backfill_chunk'] = 'Tamanho do lote da tarefa de reconstrução';
$string['settings_config_sla_backfill_chunk'] = 'Quantidade máxima de linhas de submissão processadas pela tarefa de reconstrução em cada execução de cron antes de ceder o tempo.';
$string['settings_report_pagesize'] = 'Tamanho de página do relatório';
$string['settings_config_report_pagesize'] = 'Quantidade de submissões por página no Relatório Detalhado.';
$string['settings_reset_sla'] = 'Resetar dados de responsividade';
$string['settings_config_reset_sla'] = 'Trunca as tabelas de ledger, rollup, tendência e fila de SLA e enfileira uma reconstrução completa. <a href="{$a}">Resetar agora</a>.';
$string['reset_sla_done'] = 'Dados de responsividade resetados; a reconstrução será executada no próximo ciclo do cron.';
$string['reset_sla_confirm'] = 'Isso truncará as tabelas de SLA e reconstruirá a partir de {assign_submission}. Continuar?';

// Nomes das tarefas agendadas.
$string['task_sla_drain'] = 'Drenar a fila de SLA (recalcular agregações)';
$string['task_sla_trend_recompute'] = 'Recalcular a tendência de SLA de 30 dias';
$string['task_sla_site_stats'] = 'Recalcular as estatísticas de SLA do site';
$string['task_sla_backfill'] = 'Reconstruir o ledger de SLA do zero';
$string['task_sla_recompute_one'] = 'Recalcular uma tupla de SLA (adhoc, disparado após avaliação)';

// Seção de Responsividade e cartão.
$string['responsiveness'] = 'Responsividade de Correção';
$string['responsiveness_loading'] = 'Carregando dados de responsividade…';
$string['responsiveness_no_groups'] = 'Você não pertence a nenhum grupo neste curso.';
$string['responsiveness_load_failed'] = 'Falha ao carregar dados de responsividade. Tente novamente em instantes.';
$string['typical_response'] = 'Resposta típica';
$string['within_90'] = '90% em até';
$string['longest_wait'] = 'Maior espera';
$string['school_median'] = 'Mediana da escola';
$string['top_10'] = '10% melhores';
$string['pending'] = 'Pendentes';
$string['critical'] = 'Críticos';
$string['no_rule'] = 'Sem regra';
$string['rule'] = 'Regra';
$string['bucket_excellent'] = 'Excelente';
$string['bucket_good'] = 'Bom';
$string['bucket_regular'] = 'Regular';
$string['bucket_critical'] = 'Crítico';
$string['open_dashboard'] = 'Abrir Painel do Professor';
$string['last_30_days'] = 'Últimos 30 dias';
$string['compare_you'] = 'Você';
$string['compare_top10'] = '10% melhores';
$string['activities_open_close'] = 'Atividades · regra de abertura/fechamento';
$string['goal_label'] = 'Meta';
$string['ws_get_responsiveness'] = 'Retorna os dados de Responsividade de Correção por grupo para um curso.';

// Painel do Professor.
$string['dashboard_greeting'] = 'Olá, {$a->firstname}';
$string['dashboard_subtitle'] = '{$a->pending} submissões aguardando · {$a->overgoal} acima da meta';
$string['dashboard_active_courses'] = 'Cursos ativos';
$string['dashboard_median_wait'] = 'Mediana de espera';
$string['dashboard_critical_total'] = 'Críticos';
$string['dashboard_grade_now'] = 'Avaliar agora · priorizado';
$string['dashboard_open_submission'] = 'Abrir submissão';
$string['dashboard_courses_header'] = 'Seus cursos';
$string['dashboard_col_course'] = 'Curso';
$string['dashboard_col_median'] = 'Mediana';
$string['dashboard_col_trend'] = '30 dias';
$string['dashboard_col_sla'] = 'SLA';
$string['dashboard_open_course'] = 'Abrir';
$string['dashboard_empty'] = 'Você ainda não pertence a nenhum grupo em seus cursos.';
$string['dashboard_sort_label'] = 'Ordenar por';
$string['dashboard_sort_apply'] = 'Aplicar';
$string['dashboard_sort_critical_first'] = 'Críticos primeiro';
$string['dashboard_sort_pending'] = 'Quantidade pendente';
$string['dashboard_sort_median'] = 'Mediana de espera';
$string['dashboard_sort_title'] = 'Título do curso';
$string['dashboard_overdue'] = 'Atrasado';
$string['dashboard_closes_today'] = 'Encerra hoje';
$string['dashboard_closes_soon'] = 'Encerra em breve';

// Relatório Detalhado.
$string['report_title'] = 'Submissões Pendentes · Relatório Detalhado';
$string['report_breadcrumb_back'] = 'Voltar para o painel';
$string['report_filter_all'] = 'Todos';
$string['report_sort_waiting'] = 'Maior espera';
$string['report_sort_activity'] = 'Atividade';
$string['report_sort_student'] = 'Nome do estudante';
$string['report_col_student'] = 'Estudante';
$string['report_col_activity'] = 'Atividade';
$string['report_col_submitted'] = 'Enviado em';
$string['report_col_waiting'] = 'Aguardando';
$string['report_grade_button'] = 'Avaliar →';
$string['report_empty'] = 'Nenhuma submissão corresponde aos filtros atuais.';
$string['report_user_override'] = 'Exceção';
$string['report_user_override_tooltip'] = 'Este estudante possui uma exceção individual nesta tarefa.';
