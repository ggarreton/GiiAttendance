<?php
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

// Output starts here.
echo $OUTPUT->header();

// If the user is not a student redirects to view.php
if(!is_a_student($COURSE, $USER))
    die(redirect('view.php?id='.$id));

// Create Tabs buttons to change between views
echo   '<ul class="nav nav-tabs">
            <li><a href="student2.php?id='.$id.'">Mark Attendance</a></li>
            <li class="active"><a href="student.php?id='.$id.'">My Attendances</a></li>
        </ul>';
        
$nabsent        = 0;
$npresent       = 0;
// Adding the table of the historical records of attendance for this student
$table          = new html_table();
$sqlDates       =  "SELECT date, id
                    FROM mdl_attendance_detail
                    WHERE attendanceid = $attendance->id";
$dates          = $DB->get_records_sql( $sqlDates );
$table->head    = array('Date','Status');
// Select the full name of the users that have the rol student in this course
foreach ($dates as $date) {
    // Get the current student status for the given date
    $status     = $DB->get_record_sql(" SELECT attendancestatus 
                                        FROM mdl_attendance_student_detail 
                                        WHERE userid            = $USER->id 
                                        AND attendancedetailid  = $date->id");
    if($status->attendancestatus === 'Absent'){
        $nabsent++;
    }else{
        $npresent++;
    }
    // Transform the unix timestamp in a day-month format and insert it in the table row along the student status
    $table->data[] = array(usergetdate($date->date)['mday'].'-'.usergetdate($date->date)['month'], $status->attendancestatus);
}
// Calculate the mean of the user attendance
$mean = $npresent/($npresent+$nabsent);
$table->data[]  = array('% Attendance', percentage($mean));
$table->data[]  = array('Absents', $nabsent);
echo html_writer::table($table);    
    
// Finish the page.
echo $OUTPUT->footer();