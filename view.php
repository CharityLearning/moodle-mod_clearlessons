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
 * Clear Lesson module main user interface
 *
 * @package    mod_clearlesson
 * @copyright  2017 Josh Willcock  {@link http://josh.cloud}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/mod/clearlesson/lib.php");
require_once("$CFG->dirroot/mod/clearlesson/locallib.php");
require_once($CFG->libdir . '/completionlib.php');
$pluginconfig = get_config("clearlesson");

$id = optional_param('id', 0, PARAM_INT);        // Course module ID.
$u = optional_param('u', 0, PARAM_INT);         // URL instance id.
$redirect = optional_param('redirect', 0, PARAM_BOOL);
if ($u) {  // Two ways to specify the module.
    $url = $DB->get_record('clearlesson', array('id' => $u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('clearlesson', $url->id, $url->course, false, MUST_EXIST);
} else {
    $cm = get_coursemodule_from_id('clearlesson', $id, 0, false, MUST_EXIST);
    $url = $DB->get_record('clearlesson', array('id' => $cm->instance), '*', MUST_EXIST);
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/clearlesson:view', $context);
// Completion and trigger events.
clearlesson_view($url, $course, $cm, $context);
$PAGE->set_url('/mod/clearlesson/view.php', array('id' => $cm->id));
// Make sure URL exists before generating output - some older sites may contain empty urls
// Do not use PARAM_URL here, it is too strict and does not support general URIs!
$exturl = trim($url->externalref);
if (empty($exturl) or $exturl === 'http://') {
    clearlesson_print_header($url, $cm, $course);
    clearlesson_print_heading($url, $cm, $course);
    clearlesson_print_intro($url, $cm, $course);
    notice(get_string('invalidstoredurl', 'url'), new moodle_url('/course/view.php', array('id' => $cm->course)));
    die;
}
unset($exturl);
$displaytype = clearlesson_get_final_display_type($url);
if ($displaytype == RESOURCELIB_DISPLAY_OPEN) {
    // For 'open' links, we always redirect to the content - except if the user
    // just chose 'save and display' from the form then that would be confusing.
    if (strpos(get_local_referer(false), 'modedit.php') === false) {
        $redirect = true;
    }
}
if ($redirect) {
    // Coming from course page or url index page.
    // The redirection is needed for completion tracking and logging.
    if (empty($config)) {
        $config = get_config('clearlesson');
    }
    $fullclearlesson = new moodle_url("/mod/clearlesson/senduser.php", array('id' => $url->id));
    $fullurl = str_replace('&amp;', '&', $fullclearlesson);

    if (!course_get_format($course)->has_view_page()) {
        // If course format does not have a view page, add redirection delay with a link to the edit page.
        // Otherwise teacher is redirected to the external URL without any possibility to edit activity or course settings.
        $editurl = null;
        if (has_capability('moodle/course:manageactivities', $context)) {
            $editurl = new moodle_url('/course/modedit.php', array('update' => $cm->id));
            $edittext = get_string('editthisactivity');
        } else if (has_capability('moodle/course:update', $context->get_course_context())) {
            $editurl = new moodle_url('/course/edit.php', array('id' => $course->id));
            $edittext = get_string('editcoursesettings');
        }
        if ($editurl) {
            redirect($fullurl, html_writer::link($editurl, $edittext)."<br/>".
            get_string('pageshouldredirect'), 10);
        }
    }
    redirect($fullurl);
}
switch ($displaytype) {
    case RESOURCELIB_DISPLAY_EMBED:
        clearlesson_display_embed($url, $cm, $course);
    break;
    case RESOURCELIB_DISPLAY_FRAME:
        clearlesson_display_frame($url, $cm, $course);
    break;
    default:
        clearlesson_print_workaround($url, $cm, $course);
    break;
}
