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
 * @copyright  2015 GIA
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
    $attendance = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $attendance = $DB->get_record('attendance', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $attendance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
require_login($course, true, $cm);
$event = \mod_attendance\event\course_module_viewed::create(array(
    'objectid'  => $PAGE->cm->instance,
    'context'   => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $attendance);
$event->trigger();
// Print the page header.
$PAGE->set_url('/mod/attendance/teacher.php', array('id' => $cm->id));
$PAGE->set_title(format_string($attendance->name));
$PAGE->set_heading(format_string($course->fullname));

// Output starts here.
echo $OUTPUT->header();

// If the user is not a teacher redirects to view.php
if(!is_a_teacher($COURSE, $USER))
    die(redirect('view.php?id='.$id));

// The sql gets the list of students enroled in the current course and realice all the verifications so the user is active, not deleted, etc
$sqlUsers= "SELECT DISTINCT u.id AS userid, u.firstname, u.lastname
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
// The sql gets the list of dates where a attendance where recorded
$sqlDates= "SELECT date
            FROM mdl_attendance_detail
            GROUP BY date
            ORDER BY date"; 
$students   = $DB->get_records_sql( $sqlUsers);
$dates      = $DB->get_records_sql( $sqlDates);
// Create Tabs buttons to change between views
echo   '<ul class="nav nav-tabs">
            <li><a href="teacher2.php?id='.$id.'">Take Attendance</a></li>
            <li class="active"><a href="teacher.php?id='.$id.'">Attendance Review</a></li>
        </ul>';

echo $OUTPUT->heading('Students Attendances');

// Defining variables for debugging
$npresent       = 0;
$nabsent        = 0;
$dateCount      = 0;
$npresentDay    = array();
$nabsentDay     = array();
$meanDay        = array();
$cont           = 0;
$table          = new html_table();
$tableHead      = array('Student');
// Transform the unix date from the database into a "day-month" format
foreach ($dates as $date) {
    array_push($tableHead, usergetdate($date->date)["mday"]."-".usergetdate($date->date)["month"]);
}
array_push($tableHead, '% Attendance');

// Insert in an array the headers to be used in the table
$table->head = $tableHead;

foreach ($students as $student) {
    // Create a row whit the user name and lastname in the first column
    $row    = array($student->firstname." ".$student->lastname);
    foreach($dates as $date){   
        $sqlStatus  =  "SELECT sd.attendancestatus 
                        FROM mdl_attendance_student_detail sd, mdl_attendance_detail ad 
                        WHERE sd.attendancedetailid=ad.id 
                        AND ad.date=$date->date 
                        AND sd.userid=$student->userid";
        // Get student attendance status for the given date
        $attendanceStatus   = $DB->get_record_sql( $sqlStatus )->attendancestatus;
        // Inssert in the row, the user status for the given day
        array_push($row, $attendanceStatus);
        // Increase de number of absents, or present for each student and for the given date
        if($attendanceStatus == "Absent"){
            $nabsent++;
            $nabsentDay[$dateCount]++;
        }else{
            $npresent++;
            $npresentDay[$dateCount]++;
        }
        $dateCount++;
    }
    // Calculate the % of attendance for the current student and insert it to the row
    $mean           = $npresent/($npresent+$nabsent);
    array_push($row, percentage($mean));
    // Add row to de table
    $table->data[]  = $row;
    // Reset Counts
    $dateCount      = 0;    
    $npresent       = 0;
    $nabsent        = 0;
}
// Create an extra row to summarize the attendance of the course
$row=array('Class Attendance');
foreach($dates as $date){ 
    // Calcuate the mean for the given date and add it to the row
    $meanDay[$dateCount] = $npresentDay[$dateCount]/($npresentDay[$dateCount]+$nabsentDay[$dateCount]);
    array_push($row, percentage($meanDay[$dateCount]));
    $dateCount++;
}
// Calculate the total attendance and add it to the row
array_push($row,  percentage(array_sum($meanDay)/count($meanDay)));
$table->data[] = $row;
echo html_writer::table($table);
// Finish the page.
echo $OUTPUT->footer();