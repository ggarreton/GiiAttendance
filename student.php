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

//Only the student can mark as a present
if(is_a_student($COURSE, $USER)){
	$sql = "SELECT starttime, endtime, id
	FROM mdl_attendance_detail
	WHERE attendanceid = $attendance->id
	AND starttime < UNIX_TIMESTAMP(NOW( ))
	AND endtime > UNIX_TIMESTAMP(NOW( )) ";

	$range_of_time = $DB->get_record_sql( $sql );

	$start_of_time = $range_of_time->sTime;
	$end_of_time = $range_of_time->eTime;

	// If the student is on time to mark the attendance
	if( $sql!=null || $sql!='' || $sql!=array('', '') || $sql!=array(null, null) && $end_of_time>time() ){
		$pform = new present_form($PAGE->url);

		if($pform->get_data()) {
        
            $id_current_attendance = $DB->get_record_sql("SELECT id 
            FROM mdl_attendance_detail 
            WHERE attendanceid = $attendance->id
            ORDER BY id DESC
            LIMIT 1");

            // The table structure is:
            // _______________________________________________________
            // | id | attendancedetailid | userid | attendancestatus |

            $sql2 = "SELECT id 
            FROM mdl_attendance_student_detail 
            WHERE attendancedetailid = $id_current_attendance->id
            AND userid = $USER->id";

            $attendance_user_id = $DB->get_record_sql( $sql2 );

        	// Creating one file to insert in the DB with their attributes
        	$records                        	= new stdClass();
        	$records->id            	    	= $attendance_user_id->id;
        	$records->attendancestatus  	    = 'Present';
        	// The date is not the timestamp of the day at 00:00, the date is the actual time in UNIX
        	
	        // insert_record('name_of_the_table', 'values_to_insert')
        	// You must ommit 'mdl_', because by default is added
    	    $lastinsertid = $DB->update_record('attendance_student_detail', $records);        //echo $id_attendance;
        
        
	    } else {

        	$pform->display();
    	}
		

	}









}

// Finish the page.
echo $OUTPUT->footer();

?>