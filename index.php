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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_attendance
 * @copyright  2015 GIA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace attendance with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT); // Course.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_attendance\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strName = get_string('modulenameplural', 'mod_attendance');
$PAGE->set_url('/mod/attendance/index.php', array('id' => $id));
$PAGE->navbar->add($strName);
$PAGE->set_title("$course->shortname: $strName");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strName);

if (! $attendances = get_all_instances_in_course('attendance', $course)) {
    notice(get_string('noattendances', 'attendance'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$useSections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($useSections) {
    $strSectionName = get_string('sectionname', 'format_'.$course->format);
    $table->head    = array ($strSectionName, $strName);
    $table->align   = array ('center', 'left');
} else {
    $table->head    = array ($strName);
    $table->align   = array ('left');
}

$modInfo = get_fast_modinfo($course);
$currentSection = '';
foreach ($modInfo->instances['attendance'] as $courseModule) {
    $row = array();
    if ($useSections) {
        if ($courseModule->sectionnum !== $currentSection) {
            if ($courseModule->sectionnum) {
                $row[] = get_section_name($course, $courseModule->sectionnum);
            }
            if ($currentSection !== '') {
                $table->data[] = 'hr';
            }
            $currentSection = $courseModule->sectionnum;
        }
    }

    $class = $courseModule->visible ? null : array('class' => 'dimmed');

    $row[] = html_writer::link(new moodle_url('view.php', array('id' => $courseModule->id)),
                $courseModule->get_formatted_name(), $class);
    $table->data[] = $row;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
