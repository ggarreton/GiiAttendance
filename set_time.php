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
$cssfilename = '/mod/attendance/set_time.css';
if (file_exists($CFG->dirroot.$cssfilename)) {
    $PAGE->requires->css($cssfilename);
}
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... attendance instance ID - it should be named as the first character of the module.
if ($id) {
    $courseModule             = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $courseModule->course), '*', MUST_EXIST);
    $attendance     = $DB->get_record('attendance', array('id' => $courseModule->instance), '*', MUST_EXIST);
} else if ($n) {
    $attendance     = $DB->get_record('attendance', array('id' => $n), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $attendance->course), '*', MUST_EXIST);
    $courseModule             = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
require_login($course, true, $courseModule);
$event = \mod_attendance\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $attendance);
$event->trigger();
// Print the page header.
$PAGE->set_url('/mod/attendance/set_time.php', array('id' => $courseModule->id));
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

// If the user is not a teacher redirects to view.php
if(!VerifyRole('teacher')){
    die(redirect('view.php?id='.$id));
}
$sqlLastDate=  "SELECT  date 
                FROM mdl_attendance_detail
                WHERE attendanceid = $attendance->id
                ORDER BY date 
                DESC 
                limit 1";
// Get last attendance incerted in the DB
$lastDate   = $DB->get_record_sql($sqlLastDate);
//Transform the date fetched from the DB to day-month-year format
$dbLastDate = usergetdate($lastDate->date)['mday'].'-'.usergetdate($lastDate->date)['month'].'-'.usergetdate($lastDate->date)['year'];
// Transform current date Timestamp to the day-month-year format
$now        = usergetdate(time())['mday'].'-'.usergetdate(time())['month'].'-'.usergetdate(time())['year'];
// Verify if the text are equals
if($dbLastDate===$now){
    //  Give the error message and a return button
    echo get_string('already_passed_attendance', 'mod_attendance');
    echo   '<ul class="nav nav-tabs">
            <li><a href="teacher.php?id='.$id.'">'.get_string('return', 'mod_attendance').'</a></li>
        </ul>';
}else{
    $sqlStudents=  "SELECT DISTINCT u.id AS userid
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

    $sqlDates   =  "SELECT date, id
                    FROM mdl_attendance_detail
                    WHERE attendanceid = $attendance->id
                    ORDER BY date"; 

    $students   = $DB->get_records_sql( $sqlStudents);
    $dates      = $DB->get_records_sql( $sqlDates);


    $mform      = new simplehtml_form($PAGE->url);
    if($mform->get_data()) {
        
        // Recognice data from the form
        $formdata                       = $mform->get_data();
        // Creating one record to insert in the DB with their attributes
        $records                        = new stdClass();
        $records->attendanceid          = $attendance->id;
        $records->attendancetipe        = 'by_students';
        // The date is the actual time in UNIX
        $records->date                  = time();
        // Obteining times in UNIX from the form
        $records->starttime             = $formdata->startTime;
        $records->endtime               = $formdata->endTime;
        $DB->insert_record('attendance_detail', $records);
        
        $currentAttendanceId            = $DB->get_record_sql( "SELECT id 
                                                                FROM mdl_attendance_detail 
                                                                WHERE attendanceid = $attendance->id
                                                                ORDER BY id DESC
                                                                LIMIT 1");
        foreach ($students as $student) {
            // Until the Student mark their attendance, by default they are listed as absent
            $recordAbsent->attendancedetailid      = $currentAttendanceId->id;
            $recordAbsent->userid                  = $student->userid;
            $recordAbsent->attendancestatus        = 'Absent';
            // Insert the status in the current student
            $DB->insert_record('attendance_student_detail', $recordAbsent);
        }
        // Once finish inserting in the DB redirect to view.php
        redirect('view.php?id='.$id);
        
    } else {
        $mform->display();
    }
}
// Finish the page.
echo $OUTPUT->footer();

