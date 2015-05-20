<?php

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
echo'<ul class="nav nav-tabs">
<li><a href="student2.php?id='.$id.'">Mark Attendance</a></li>
<li class="active"><a href="student.php?id='.$id.'">My Attendances</a></li>
</ul>';

if ($attendance->intro) {
    echo $OUTPUT->box(format_module_intro('attendance', $attendance, $cm->id), 'generalbox mod_introbox', 'attendanceintro');
}

// Replace the following lines with you own code.
// Get current day, month and year for current user.

//Only the student can mark as a present

        
    $nabsent = 0;
    $npresent = 0;
    // Adding the table of the historical records of attendance for this student
    $table = new html_table();
    $sql_dates = "SELECT date, id
    FROM mdl_attendance_detail
    WHERE attendanceid = $attendance->id";

    $dates = $DB->get_records_sql( $sql_dates );

    $table->head = array('Date','Status');

    // Select the full name of the users that have the rol student in this course
    foreach ($dates as $date) {
        $status = $DB->get_record_sql("SELECT attendancestatus FROM mdl_attendance_student_detail 
                                        WHERE userid = $USER->id AND attendancedetailid = $date->id");
        if($student->attendancestatus == 'Absent'){
            $nabsent++;
        }else{
            $npresent++;
        }
        $table->data[] = array(usergetdate($date->date)['mday'].'-'.usergetdate($date->date)['month'], $status->attendancestatus);
    }

    $mean = $npresent/($npresent+$nabsent);
    $table->data[] = array('% Attendance', percentage($mean));
    $table->data[] = array('Absents', $nabsent);
    echo html_writer::table($table);    
    


// Finish the page.
echo $OUTPUT->footer();
