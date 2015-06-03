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
    $attendance = $DB->get_record('attendance', array('id' => $courseModule->instance), '*', MUST_EXIST);
} else if ($n) {
    $attendance = $DB->get_record('attendance', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $attendance->course), '*', MUST_EXIST);
    $courseModule         = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
} else {
    error(get_string('errorSpecifyInstanceId', 'mod_attendance')); //'You must specify a course_module ID or an instance ID'
}
require_login($course, true, $courseModule);
$event = \mod_attendance\event\course_module_viewed::create(array(
    'objectid'  => $PAGE->cm->instance,
    'context'   => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $attendance);
$event->trigger();
// Print the page header.
$PAGE->set_url('/mod/attendance/teacher.php', array('id' => $courseModule->id));
$PAGE->set_title(format_string($attendance->name));
$PAGE->set_heading(format_string($course->fullname));

// Output starts here.
echo $OUTPUT->header();

// If the user is not a teacher redirects to view.php
if(!VerifyRole('teacher')){
    die(redirect('view.php?id='.$id));
}

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
$sqlDates   =  "SELECT date, id
                FROM mdl_attendance_detail
                WHERE attendanceid = $attendance->id
                ORDER BY date"; 
$students   = $DB->get_records_sql( $sqlUsers);
$dates      = $DB->get_records_sql( $sqlDates);
// Create Tabs buttons to change between views
echo   '<ul class="nav nav-tabs">
            <li><a href="teacher2.php?id='.$id.'">'.get_string('takeAttendance', 'mod_attendance').'</a></li>
            <li class="active"><a href="teacher.php?id='.$id.'">'.get_string('attendanceReview', 'mod_attendance').'</a></li>
        </ul>';
echo $OUTPUT->heading(get_string('StudentsAttendances', 'mod_attendance'));
// Verify if there is attendances to display
if(count($dates)!=0){
    // Sets the start point to the variables used to measure the atetndances
    $numberOfPresents       = 0;
    $numberOfAbsents        = 0;
    $dateCount              = 0;
    $numberOfPresentsDay    = array();
    $numberOfAbsentsDay     = array();
    $meanDay                = array();

    $tableHead              = array(get_string('student', 'mod_attendance'));
    // Transform the unix date from the database into a "day-month" format
    foreach ($dates as $date) {
        array_push($tableHead, usergetdate($date->date)["mday"]."-".usergetdate($date->date)["month"]);
    }
    array_push($tableHead, '% Attendance');

    // Insert in an array the headers to be used in the table
    $table->head = $tableHead;



    $table         = new html_table();
    $table->head   = $tableHead;
    // in the SQL the if is made so it returns directly the url for the image to use (from the standerd image library in moodle)
    $sqlStatus     =  " SELECT sd.id,sd.userid,ad.date ,if((sd.attendancestatus!= 'present'),'i/grade_correct','i/grade_incorrect') status
                        FROM mdl_attendance_student_detail sd, mdl_attendance_detail ad 
                        WHERE sd.attendancedetailid=ad.id
                        AND ad.attendanceid= $attendance->id
                        order by ad.date";
    // Get student attendance status for the given date
    $attendanceStatus   = $DB->get_records_sql( $sqlStatus );
    foreach($attendanceStatus as $oneStatus)
    {   
        // Transform the attendanceStatus object array in a more friendly and easy to use bidimensional array, whit the first key as the user id, and the second as the date
        // as a value the image input is inserted using the directory given by the query
        $StatusArray[$oneStatus->userid][$oneStatus->date]=html_writer::empty_tag('input', array('type' => 'image', 'src'=>$OUTPUT->pix_url($oneStatus->status), 'alt'=>""));
    }

    foreach($students as $student)
    {
        //Create a row  whit de values of the current user status from all dates (pre sorted in the querry)
        $row =array_values($StatusArray[$student->userid]);
        // Get the number of inputs representing a present in the row (url='i/grade_correct')
        $numberOfPresents= array_count_values($row)[html_writer::empty_tag('input', array('type' => 'image', 'src'=>$OUTPUT->pix_url('i/grade_correct'), 'alt'=>""))];      
        // Get the number of inputs representing a absent in the row (url='i/grade_incorrect')
        $numberOfAbsents= array_count_values($row)[html_writer::empty_tag('input', array('type' => 'image', 'src'=>$OUTPUT->pix_url('i/grade_incorrect'), 'alt'=>""))];
        // Calculate the row attendance % and push it at the end of the row array
        $mean           = $numberOfPresents/($numberOfPresents+$numberOfAbsents);
        array_push($row, percentage($mean));
        // Add at the begining of the row array the name-lastname of the student that the info belongs to
        array_unshift($row, $student->firstname." ".$student->lastname);
        // Add the row to the tableData array
       $tableData[]=$row;
    }
    // assing the tableData array as the data of the array and print the table
    $table->data=$tableData;
    echo html_writer::table($table);

    echo '<ul class="nav nav-pills nav-stacked">
      <li role="presentation"><a href="edit_attendance.php?id='.$id.'">'.get_string('button_edit', 'mod_attendance').'</a></li>
    </ul>';
}else
echo get_string('noAttendances', 'mod_attendance');
// Finish the page.
echo $OUTPUT->footer();
