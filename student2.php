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
$PAGE->set_url('/mod/attendance/student2.php', array('id' => $cm->id));
$PAGE->set_title(format_string($attendance->name));
$PAGE->set_heading(format_string($course->fullname));

// Output starts here.
echo $OUTPUT->header();

// If the user is not a student redirects to view.php
if(!is_a_student($COURSE, $USER))
    die(redirect('view.php?id='.$id));

// Create Tabs buttons to change between views
echo   '<ul class="nav nav-tabs">
            <li class="active"><a href="student2.php?id='.$id.'">Mark Attendance</a></li>
            <li><a href="student.php?id='.$id.'">My Attendances</a></li>
        </ul>';


$sqlTimeRange   = " SELECT starttime, endtime, id
                    FROM mdl_attendance_detail
                    WHERE attendanceid = $attendance->id
                    AND starttime < UNIX_TIMESTAMP(NOW( ))
                    AND endtime > UNIX_TIMESTAMP(NOW( )) 
                    LIMIT 1";
$timeRange      = $DB->get_record_sql( $sqlTimeRange );
// The isset is for debugging
if(isset($timeRange->starttime)){
    $startTime  = $timeRange->starttime;
}
if(isset($timeRange->endtime)){
    $endTime    = $timeRange->endtime;
}
$status         = $DB->get_record_sql( "SELECT attendancestatus 
                                        FROM mdl_attendance_student_detail 
                                        WHERE userid = $USER->id 
                                        ORDER BY id DESC LIMIT 1");    

// If the student is on time to mark the attendance
if( $timeRange != null && $endTime>time() && $status->attendancestatus != "Present"){
    $pform = new present_form($PAGE->url);
    if($pform->get_data()) {
    
        $currentAttendanceId    = $DB->get_record_sql( "SELECT id 
                                                        FROM mdl_attendance_detail 
                                                        WHERE attendanceid = $attendance->id
                                                        ORDER BY id DESC
                                                        LIMIT 1");
        $attendanceUserId       = $DB->get_record_sql( "SELECT id 
                                                        FROM mdl_attendance_student_detail 
                                                        WHERE attendancedetailid = $currentAttendanceId->id
                                                        AND userid = $USER->id
                                                        LIMIT 1");
        // Create a record to insert in the DB
        $records                            = new stdClass();
        // Insert in the record the id of a alredy created entry to overwrite it
        $records->id                        = $attendanceUserId->id;
        // Insert the new attendance status to overwrite the entry
        $records->attendancestatus          = 'Present';
        $DB->update_record('attendance_student_detail', $records);
        redirect('student.php?id='.$id);
    } else {
        $pform->display();
    }
}else if( $status->attendancestatus == "Present" ){
    echo "You are already Present";
}else{
    echo "There is no attendance to mark";
}
    

// Finish the page.
echo $OUTPUT->footer();
