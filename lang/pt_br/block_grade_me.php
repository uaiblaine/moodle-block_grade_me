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
