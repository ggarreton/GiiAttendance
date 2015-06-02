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
    $courseModule         = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $courseModule->course), '*', MUST_EXIST);
    $attendance  = $DB->get_record('attendance', array('id' => $courseModule->instance), '*', MUST_EXIST);
} else if ($n) {
    $attendance  = $DB->get_record('attendance', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $attendance->course), '*', MUST_EXIST);
    $courseModule         = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
} else {
    error(get_string('errorSpecifyInstanceId', 'mod_attendance'));
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
$PAGE->set_url('/mod/attendance/edit_attendance.php', array('id' => $courseModule->id));
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

$sqlStudents=  "SELECT DISTINCT u.id AS userid, u.firstname, u.lastname
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
                ORDER BY date"; 

// Get the user id of all the students of the course
$students   = $DB->get_records_sql($sqlStudents);

// Get the attendances Dates
$dates      = $DB->get_records_sql( $sqlDates);

// Verify if the hidden input was send
if(optional_param('enviado',null, PARAM_INTEGER)!=null){
    foreach ($students as $student) {
        foreach($dates as $date){
            $sqlAttendanceDetail                =  "SELECT sd.id
                                                    FROM mdl_attendance_student_detail sd, mdl_attendance_detail ad 
                                                    WHERE sd.attendancedetailid=ad.id 
                                                    AND ad.date=$date->date 
                                                    AND sd.userid=$student->userid";
            // Get student attendance id to edit the status
            $detailId                           = $DB->get_record_sql( $sqlAttendanceDetail );
            // Verify if the user have the attendance to that seasson created
            if($detailId !=null){
                $studentRecords                     = new stdClass();
                $studentRecords->id                 = $detailId->id;

                // Verify if there is integer key where the user is saved in the param requested, if so, Saves a Present "attendancestatus" otherwise a Absent
                $studentRecords->attendancestatus   = (is_int(array_search($student->userid,optional_param_array($date->id,null, PARAM_INTEGER))))? "Present" : "Absent";
                // Update the DB whit the Record
                $DB->update_record('attendance_student_detail', $studentRecords);
            }else{
                // in the case a user matriculate later in the course $detailId returns null, and so the record should be added instad of edited 
                $studentRecords                        = new stdClass();
                $studentRecords->attendancedetailid    = $date->id;
                $studentRecords->userid                = $student->userid;
                // Verify if there is integer key where the user is saved in the param requested, if so, Saves a Present "attendancestatus" otherwise a Absent
                $studentRecords->attendancestatus      = (is_int(array_search($student->userid,optional_param_array($date->id,null, PARAM_INTEGER))))? "Present" : "Absent";
                $DB->insert_record('attendance_student_detail', $studentRecords);
            }

        }
    }
    redirect('teacher.php?id='.$id);
}
else{
    echo $OUTPUT->heading(get_string('editAttendance', 'mod_attendance'));
    $table = new html_table();
    $tableHead=array(get_string('firstName', 'mod_attendance'),get_string('lastName', 'mod_attendance'));
    foreach ($dates as $date) {
        array_push($tableHead, usergetdate($date->date)["mday"]."-".usergetdate($date->date)["month"]);
    }
    $table->head =$tableHead;
    foreach ($students as $student) {
        $row = array($student->firstname,$student->lastname);
        foreach($dates as $date){
            $sqlStatus  =  "SELECT attendancestatus 
                            FROM mdl_attendance_student_detail 
                            WHERE attendancedetailid=$date->id
                            AND  userid=$student->userid";
            // Get student attendance status for the given date
            $attendanceStatus   = $DB->get_record_sql( $sqlStatus )->attendancestatus;
            if($attendanceStatus == "Present")
                array_push($row,  html_writer::empty_tag('input', array('type' => 'checkbox', 'name'=>$date->id."[]" ,'value' => $student->userid ,'checked'=>"")));  
            else
                array_push($row, html_writer::empty_tag('input', array('type' => 'checkbox', 'name'=>$date->id."[]" ,'value' => $student->userid)));                
        }
        // insert a row for the given student telling the student name, lastname and a checkbox
        $table->data[] = $row;
    }
    echo html_writer::start_tag('form', array('action' => $PAGE->url, 'method' => 'post'));
    echo html_writer::table($table);
    // Input send to verify if there is data to insert in the DB
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'enviado', 'value' => '1'));
    // The send button and the end of the form
    echo html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'enviar'));
    echo html_writer::end_tag('form');
}
// Finish the page.
echo $OUTPUT->footer();