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
 * Library of interface functions and constants for module attendance
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the attendance specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_attendance
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Example constant, you probably want to remove this :-)
 */
define('attendance_ULTIMATE_ANSWER', 42);

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function attendance_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the attendance into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $attendance Submitted data from the form in mod_form.php
 * @param mod_attendance_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted attendance record
 */
function attendance_add_instance(stdClass $attendance, mod_attendance_mod_form $mform = null) {
    global $DB;

    $attendance->timecreated = time();

    // You may have to add extra stuff in here.

    $attendance->id = $DB->insert_record('attendance', $attendance);

    attendance_grade_item_update($attendance);

    return $attendance->id;
}

/**
 * Updates an instance of the attendance in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $attendance An object from the form in mod_form.php
 * @param mod_attendance_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function attendance_update_instance(stdClass $attendance, mod_attendance_mod_form $mform = null) {
    global $DB;

    $attendance->timemodified = time();
    $attendance->id = $attendance->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('attendance', $attendance);

    attendance_grade_item_update($attendance);

    return $result;
}

/**
 * Removes an instance of the attendance from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function attendance_delete_instance($id) {
    global $DB;

    if (! $attendance = $DB->get_record('attendance', array('id' => $id))) {
        return false;
    }

    // Delete any dependent records here.

    $DB->delete_records('attendance', array('id' => $attendance->id));

    attendance_grade_item_delete($attendance);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $attendance The attendance instance record
 * @return stdClass|null
 */
function attendance_user_outline($course, $user, $mod, $attendance) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $attendance the module instance record
 */
function attendance_user_complete($course, $user, $mod, $attendance) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in attendance activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function attendance_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link attendance_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function attendance_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link attendance_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function attendance_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function attendance_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function attendance_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of attendance?
 *
 * This function returns if a scale is being used by one attendance
 * if it has support for grading and scales.
 *
 * @param int $attendanceid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given attendance instance
 */
function attendance_scale_used($attendanceid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('attendance', array('id' => $attendanceid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of attendance.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any attendance instance
 */
function attendance_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('attendance', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given attendance instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $attendance instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function attendance_grade_item_update(stdClass $attendance, $reset=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($attendance->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($attendance->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $attendance->grade;
        $item['grademin']  = 0;
    } else if ($attendance->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$attendance->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('mod/attendance', $attendance->course, 'mod', 'attendance',
            $attendance->id, 0, null, $item);
}

/**
 * Delete grade item for given attendance instance
 *
 * @param stdClass $attendance instance object
 * @return grade_item
 */
function attendance_grade_item_delete($attendance) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/attendance', $attendance->course, 'mod', 'attendance',
            $attendance->id, 0, null, array('deleted' => 1));
}

/**
 * Update attendance grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $attendance instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function attendance_update_grades(stdClass $attendance, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();

    grade_update('mod/attendance', $attendance->course, 'mod', 'attendance', $attendance->id, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function attendance_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for attendance file areas
 *
 * @package mod_attendance
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function attendance_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the attendance file areas
 *
 * @package mod_attendance
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the attendance's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function attendance_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding attendance nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the attendance module instance
 * @param stdClass $course current course record
 * @param stdClass $module current attendance instance record
 * @param cm_info $cm course module information
 */
function attendance_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * Extends the settings navigation with the attendance settings
 *
 * This function is called when the context for the page is a attendance module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $attendancenode attendance administration node
 */
function attendance_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $attendancenode=null) {
    // TODO Delete this function and its docblock, or implement it.
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//                               HERE START WHATEVER WE WANT TO WRITE ABOUT FUNCTIONS                                 //
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * This function is called when there is a need to verify the current user role
 *
 * @param $role string corresponding to the role to be verified
 */
function VerifyRole($role){
    global $COURSE,$USER;
    // Gets the course context
    $contextCourse = context_course::instance($COURSE->id);
    // look in the context for the current user's role shortname
    $roleShortname = current(get_user_roles($contextCourse, $USER->id))->shortname;
    // if the current user's role shortname contains the string in the param $role returns true, if not, false
    // ej si buscamos como
    if (strpos($roleShortname,$role) !== false) {
        return true;
    }
    else{
        return false;
    }
}



require_once("$CFG->libdir/formslib.php");
 
class simplehtml_form extends moodleform {
 
    function definition() {
        global $CFG;
 
        $mform =& $this->_form; // Don't forget the underscore! 
 
        $mform->addElement('date_time_selector', 'startTime', get_string('start_of_time', 'mod_attendance') );
        $mform->addElement('date_time_selector', 'endTime', get_string('end_of_time', 'mod_attendance') );
        

        $this->add_action_buttons( null );
        $mform->closeHeaderBefore('save');

    }                           // Close the function
} 

class present_form extends moodleform {
 
    function definition() {
        global $CFG;
 
        $mform =& $this->_form; // Don't forget the underscore!

        $this->add_action_buttons( $cancel = false, $submitlabel=get_string('present', 'mod_attendance') );
        $mform->closeHeaderBefore( get_string('present', 'mod_attendance') );

    }                           // Close the function
} 



function recording_information($name1, $value1){
    $record = new stdClass();
    $record->name           = $name1;
    $record->displayorder   = $value1;
    return $record;
}

function percentage($number){
    $percentage = (int)(100*$number).'%';
    return $percentage;
}

function is_a_teacher($COURSE, $USER){
    // I get the context of the course: Where I am?
    $cContext = context_course::instance($COURSE->id); // global $COURSE
    // Second, it's gets the id role of the actual user
    $id_role = current(get_user_roles($cContext, $USER->id))->roleid;
    
    // Third, review if the id role is only one or they are more
    // After I need to change the place of the strings and move to lang/en
    if(is_array($id_role)){
        foreach ($id_role as $key => $value) {
            if($value == 3){
                $is_a_teacher=true;
            }else{
                $is_a_teacher=false;
            }        
        }
    }else{
        if($id_role == 3){
            $is_a_teacher=true;
        }else{
            $is_a_teacher=false;
        }
    }

    return $is_a_teacher;    
}

require_once("$CFG->libdir/formslib.php");
 
class simplehtml_form extends moodleform {
 
    function definition() {
        global $CFG;
 
        $mform =& $this->_form; // Don't forget the underscore! 
 
        $mform->addElement('date_time_selector', 'start_of_time', get_string('start_of_time', 'mod_attendance') );
        $mform->addElement('date_time_selector', 'end_of_time', get_string('end_of_time', 'mod_attendance') );
        

        $this->add_action_buttons( null );
        $mform->closeHeaderBefore('save');

    }                           // Close the function
} 


function recording_information($name1, $value1){
    $record = new stdClass();
    $record->name           = $name1;
    $record->displayorder   = $value1;
    return $record;
}

class attendacehtml_form extends moodleform {
 
    function definition() {
        global $CFG;
        global $DB;
 
        $mform =& $this->_form; // Don't forget the underscore! 
        // Making a table with the list of the users and the actual status of attendance
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
            AND c.id = 2";

        $students = $DB->get_records_sql($sql);
        



        foreach ($students as $student) {


            $mform->addElement('checkbox', $name->id);
        }

$this->add_action_buttons( null );
$mform->closeHeaderBefore('save');

    }                           // Close the function
}       



