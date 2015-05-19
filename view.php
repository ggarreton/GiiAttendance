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

$PAGE->set_url('/mod/attendance/view.php', array('id' => $cm->id));
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
// Look the list of joins to know what are the expression u, c, e, ue, ra and ct
$sql=  "SELECT DISTINCT u.id AS userid, c.id AS courseid
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

$students = $DB->get_records_sql( $sql);
echo $OUTPUT->heading('Yay! It works!');

// This function shows me my rol in this course
echo my_role($COURSE, $USER);

if(is_a_teacher($COURSE, $USER)){
    $mform = new simplehtml_form($PAGE->url);
    // array('start_time' => $start_time, 'end_time' => $end_time)


    if($mform->get_data()) {
        
        // Recognice data from the form
        $formdata = $mform->get_data();
        
        // Obteining times in UNIX from the form
        $start_time         = $formdata->start_of_time;
        $end_time           = $formdata->end_of_time;
        

        // The table structure is:
        // __________________________________________________________________
        // | id | attendanceid | attendancetipe | date | starttime | endtime |

        // Creating one file to insert in the DB with their attributes
        $records                        = new stdClass();
        $records->attendanceid          = $attendance->id;
        $records->attendancetipe        = 'by_students';
        // The date is not the timestamp of the day at 00:00, the date is the actual time in UNIX
        $records->date                  = time();
        $records->starttime             = $start_time;
        $records->endtime               = $end_time;

        // insert_record('name_of_the_table', 'values_to_insert')
        // You must ommit 'mdl_', because by default is added
        $lastinsertid = $DB->insert_record('attendance_detail', $records);        //echo $id_attendance;
        
        $id_current_attendance = $DB->get_record_sql("SELECT id 
            FROM mdl_attendance_detail 
            WHERE attendanceid = $attendance->id
            ORDER BY id DESC
            LIMIT 1");

        // The table structure is:
        // _______________________________________________________
        // | id | attendancedetailid | userid | attendancestatus |

        foreach ($students as $student) {
            $record_absent->attendancedetailid      = $id_current_attendance->id;
            $record_absent->userid                  = $student->userid;
            $record_absent->attendancestatus        = 'Absent';
            
            $make_all_students_absent = $DB->insert_record('attendance_student_detail', $record_absent);        //echo $id_attendance;
        }

        
    } else {

        $mform->display();
    }
}



// Making a table with the list of the users
$table = new html_table();
// After those names must to be in the lang/en
$table->head = array('First Name','Last Name');

// Select the full name of the users that have the rol student in this course
foreach ($students as $student) {
    $name = $DB->get_record_sql("SELECT u.firstname, u.lastname FROM mdl_user u WHERE u.id = $student->userid");
    $table->data[] = array($name->firstname,$name->lastname);
       
}

echo html_writer::table($table);

// If a student go inside this page, he/she is going to redirct to student.php, because he/she wants to mark him/her attendance
if(is_a_student($COURSE, $USER)){
    redirect('student.php?id='.$id);
}

// Finish the page.
echo $OUTPUT->footer();



