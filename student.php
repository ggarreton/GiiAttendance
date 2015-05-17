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
 * Prints a particular instance of attendance
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_attendance
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Replace attendance with the name of your module and remove this line.
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... attendance instance ID - it should be named as the first character of the module.
if ($id) {
    $cm         = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $attendance  = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $attendance  = $DB->get_record('attendance', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $attendance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
require_login($course, true, $cm);
$event = \mod_attendance\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $attendance);
$event->trigger();
// Print the page header.
$PAGE->set_url('/mod/attendance/student.php', array('id' => $cm->id));
$PAGE->set_title(format_string($attendance->name));
$PAGE->set_heading(format_string($course->fullname));
/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('attendance-'.$somevar);
 */
// Output starts here.
echo $OUTPUT->header();
// Conditions to show the intro can change to look for own settings or whatever.
if ($attendance->intro) {
    echo $OUTPUT->box(format_module_intro('attendance', $attendance, $cm->id), 'generalbox mod_introbox', 'attendanceintro');
}
// Replace the following lines with you own code.
// Get current day, month and year for current user.
// Print formatted date in user time.

$sql_dates="SELECT date
            FROM mdl_attendance_detail
            GROUP BY date
            ORDER BY date"; 
$students = $USER->id;
$dates= $DB->get_records_sql( $sql_dates);
$array= array('Student');
foreach ($dates as $date) {
    array_push($array, usergetdate($date->date)["mday"]."-".usergetdate($date->date)["month"]);
}
echo'<ul class="nav nav-tabs">
<li><a href="#tab1" data-toggle="tab">Mark Attendance</a></li>
<li class="active"><a href="#tab2" data-toggle="tab">My Attendances</a></li>
</ul>
<!-- tab section -->
<div class="tab-content">
<div class="tab-pane" id="tab1">';
echo "aca poner boton para marcar asistencia o decir que no tiene nada que marcar";
echo '</div>
<div class="tab-pane active" id="tab2">';
echo $OUTPUT->heading('My Attendances');
$table = new html_table();
$table->head = $array;
    $name = $DB->get_record_sql("SELECT u.firstname, u.lastname FROM mdl_user u WHERE u.id = $USER->id");
    $data=array($name->firstname." ".$name->lastname);
    foreach($dates as $date){   
    $dateunix=usergetdate($date->date)[0]; 
        array_push($data, $DB->get_record_sql( "SELECT sd.attendancestatus 
                                                FROM mdl_attendance_student_detail sd, mdl_attendance_detail ad 
                                                WHERE sd.attendancedetailid=ad.id 
                                                AND ad.date=$dateunix 
                                                AND sd.userid=$USER->id")->attendancestatus);
    }
    $table->data[] = $data;

echo html_writer::table($table);
echo '</div>
</div>';
// Finish the page.
echo $OUTPUT->footer();