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
$PAGE->set_url('/mod/attendance/teacher.php', array('id' => $cm->id));
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
$sql=      "SELECT DISTINCT u.id AS userid, c.id AS courseid
            FROM mdl_user u
            JOIN mdl_user_enrolments ue ON ue.userid = u.id
            JOIN mdl_enrol e ON e.id = ue.enrolid
            JOIN mdl_role_assignments ra ON ra.userid = u.id
            JOIN mdl_context ct ON ct.id = ra.contextid AND ct.contextlevel = 50
            JOIN mdl_course c ON c.id = ct.instanceid AND e.courseid = c.id
            JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student'
            WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
            AND (ue.timeend = 0 OR ue.timeend > NOW()) AND ue.status = 0
            AND c.id = $attendance->course";
$sql_dates="SELECT date
            FROM mdl_attendance_detail
            GROUP BY date
            ORDER BY date"; 
$students = $DB->get_records_sql( $sql);
$dates= $DB->get_records_sql( $sql_dates);
$array= array('Student');
foreach ($dates as $date) {
    array_push($array, usergetdate($date->date)["mday"]."-".usergetdate($date->date)["month"]);
}
array_push($array, '% Attendance');
echo'<ul class="nav nav-tabs">
<li><a href="teacher2.php?id='.$id.'">Take Attendance</a></li>
<li class="active"><a href="teacher.php?id='.$id.'">Attendance Review</a></li>
</ul>';
echo $OUTPUT->heading('Students Attendances');
$npresent = 0;
$nabsent = 0;
$npresent_day = array();
$nabsent_day = array();
$cont = 0;
foreach ($students as $student) {
    $npresent_day[$cont] = 0;
    $nabsent_day[$cont] = 0;
    $cont++;
}
$cont = 0;
$table = new html_table();
$table->head = $array;
foreach ($students as $student) {
    $name = $DB->get_record_sql("SELECT u.firstname, u.lastname FROM mdl_user u WHERE u.id = $student->userid");
    $data=array($name->firstname." ".$name->lastname);
    foreach($dates as $date){   
        $dateunix=usergetdate($date->date)[0]; 
        $sql_status =  "SELECT sd.attendancestatus 
                        FROM mdl_attendance_student_detail sd, mdl_attendance_detail ad 
                        WHERE sd.attendancedetailid=ad.id 
                        AND ad.date=$dateunix 
                        AND sd.userid=$student->userid";
        $attendancestatus = $DB->get_record_sql( $sql_status )->attendancestatus;
        array_push($data, $attendancestatus);
        if($attendancestatus == "Absent"){
            $nabsent++;
            $nabsent_day[$cont]++;
        }else{
            $npresent++;
            $npresent_day[$cont]++;
        }
        $cont++;
    }
    $cont = 0;
    $mean = $npresent/($npresent+$nabsent);
    array_push($data, percentage($mean));
    $table->data[] = $data;
    $npresent = 0;
    $nabsent = 0;
}
$data=array('');
foreach($dates as $date){ 
    $mean_day[$cont] = $npresent_day[$cont]/($npresent_day[$cont]+$nabsent_day[$cont]);
    array_push($data, percentage($mean_day[$cont]));
    $cont++;
}
array_push($data, '');
$table->data[] = $data;
echo html_writer::table($table);
// Finish the page.
echo $OUTPUT->footer();