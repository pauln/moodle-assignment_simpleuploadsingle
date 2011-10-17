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
 * Extend the base assignment class for assignments where you upload a single file
 *
 * @package   mod-assignment
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot.'/mod/assignment/lib.php');
require_once(dirname(__FILE__).'/upload_form.php');
require_once(dirname(__FILE__).'/simpleupload_form.php');

class assignment_simpleuploadsingle extends assignment_base {


    function print_student_answer($userid, $return=false){
        global $CFG, $USER, $OUTPUT;

        $fs = get_file_storage();
        $browser = get_file_browser();

        $output = '';

        if ($submission = $this->get_submission($userid)) {
            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false)) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $found = true;
                    $mimetype = $file->get_mimetype();
                    $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/submission/'.$submission->id.'/'.$filename);
                    $output .= '<a href="'.$path.'" ><img class="icon" src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" alt="'.$mimetype.'" />'.s($filename).'</a><br />';
                    $output .= plagiarism_get_links(array('userid'=>$userid, 'file'=>$file, 'cmid'=>$this->cm->id, 'course'=>$this->course, 'assignment'=>$this->assignment));
                    $output .='<br/>';
                }
            }
        }

        $output = '<div class="files">'.$output.'</div>';
        return $output;
    }

    function assignment_simpleuploadsingle($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'simpleuploadsingle';
    }

    function view() {

        global $USER, $OUTPUT;

        $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
        require_capability('mod/assignment:view', $context);

        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();

        $this->view_intro();

        $this->view_dates();

        $filecount = false;

        $canupload = false;
        if ($submission = $this->get_submission($USER->id)) {
            $filecount = $this->count_user_files($submission->id);

            if (is_enrolled($this->context, $USER, 'mod/assignment:submit') && $this->isopen() && (!$filecount || $this->assignment->resubmit || !$submission->timemarked)) {
                $canupload = true;
            }
            if ($submission->timemarked) {
                $this->view_feedback();
            }
            if ($filecount) {
                echo $OUTPUT->box($this->print_user_files($USER->id, true, $canupload), 'generalbox boxaligncenter');
            }
        } else {
            if (is_enrolled($this->context, $USER, 'mod/assignment:submit') && $this->isopen()) {
                $canupload = true;
            }
        }

        if($canupload) {
            $this->view_upload_form();
        }

        $this->view_footer();
    }


    function process_feedback() {
        if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
            return false;
        }
        $userid = required_param('userid', PARAM_INT);
        $offset = required_param('offset', PARAM_INT);
        $mform = $this->display_submission($offset, $userid, false);
        parent::process_feedback($mform);
        }

    function print_responsefiles($userid, $return=false) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        $output = $OUTPUT->box_start('responsefiles');

        $candelete = $this->can_manage_responsefiles();
        $strdelete   = get_string('delete');

        $fs = get_file_storage();
        $browser = get_file_browser();

        if ($submission = $this->get_submission($userid)) {
            $renderer = $PAGE->get_renderer('mod_assignment');
            $output .= $renderer->assignment_files($this->context, $submission->id, 'response');
            $output .= $OUTPUT->box_end();
        }

        if ($return) {
            return $output;
        }
        echo $output;
    }

    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false, $candelete=false) {
        global $CFG, $USER, $OUTPUT;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = '';

        $submission = $this->get_submission($userid);
        if (!$submission) {
            return $output;
        }

        $strdelete   = get_string('delete');

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false);
        if (!empty($files)) {
            require_once($CFG->dirroot . '/mod/assignment/locallib.php');
            if ($CFG->enableportfolios) {
                require_once($CFG->libdir.'/portfoliolib.php');
                $button = new portfolio_add_button();
            }
            foreach ($files as $file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/submission/'.$submission->id.'/'.$filename);
                $output .= '<a href="'.$path.'" ><img src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" class="icon" alt="'.$mimetype.'" />'.s($filename).'</a>';
                if ($candelete) {
                    $delurl  = "$CFG->wwwroot/mod/assignment/delete.php?id={$this->cm->id}&amp;file=$filename&amp;userid={$submission->userid}&amp;mode=$mode&amp;offset=$offset";

                    $output .= '<a href="'.$delurl.'">&nbsp;'
                              .'<img title="'.$strdelete.'" src="'.$OUTPUT->pix_url('/t/delete').'" class="iconsmall" alt="" /></a> ';
                }
                if ($CFG->enableportfolios && $this->portfolio_exportable() && has_capability('mod/assignment:exportownsubmission', $this->context)) {
                    $button->set_callback_options('assignment_portfolio_caller', array('id' => $this->cm->id, 'submissionid' => $submission->id, 'fileid' => $file->get_id()), '/mod/assignment/locallib.php');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= plagiarism_get_links(array('userid'=>$userid, 'file'=>$file, 'cmid'=>$this->cm->id, 'course'=>$this->course, 'assignment'=>$this->assignment));
                $output .= '<br />';
            }
            if ($CFG->enableportfolios && count($files) > 1  && $this->portfolio_exportable() && has_capability('mod/assignment:exportownsubmission', $this->context)) {
                $button->set_callback_options('assignment_portfolio_caller', array('id' => $this->cm->id, 'submissionid' => $submission->id), '/mod/assignment/locallib.php');
                $output .= '<br />'  . $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
            }
        }

        $output = '<div class="files">'.$output.'</div>';

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function can_delete_files($submission) {
        global $USER;

        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;
        }

        if (is_enrolled($this->context, $USER, 'mod/assignment:submit')
          and $this->isopen()                                      // assignment not closed yet
          and $this->assignment->resubmit                          // deleting allowed
          and $USER->id == $submission->userid                     // his/her own submission
          and !$this->is_finalized($submission)) {                 // no deleting after final submission
            return true;
        } else {
            return false;
        }
    }

    function delete() {
        global $CFG, $OUTPUT, $DB;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);
        $mode     = optional_param('mode', '', PARAM_ALPHA);
        $offset   = optional_param('offset', 0, PARAM_INT);

        require_login($this->course->id, false, $this->cm);

        if (empty($mode)) {
            $urlreturn = 'view.php';
            $optionsreturn = array('id'=>$this->cm->id);
            $returnurl = 'view.php?id='.$this->cm->id;
        } else {
            $urlreturn = 'submissions.php';
            $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);
            $returnurl = "submissions.php?id={$this->cm->id}&amp;offset=$offset&amp;mode=$mode&amp;userid=$userid";
        }

        if (!$submission = $this->get_submission($userid) // incorrect submission
          or !$this->can_delete_files($submission)) {     // can not delete
            $this->view_header(get_string('delete'));
            notify(get_string('cannotdeletefiles', 'assignment'));
            print_continue($returnurl);
            $this->view_footer();
            die;
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false);

        if (!data_submitted('nomatch') or !$confirm or !confirm_sesskey()) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'sesskey'=>sesskey(), 'mode'=>$mode, 'offset'=>$offset, 'sesskey'=>sesskey());
            if (empty($mode)) {
                $this->view_header(get_string('delete'));
            } else {
                print_header(get_string('delete'));
            }

            echo $OUTPUT->heading(get_string('delete'));
            $yesbtn = new single_button(new moodle_url("delete.php", $optionsyes), get_string('yes'), "POST");
            $nobtn = new single_button(new moodle_url($urlreturn, $optionsreturn), get_string('no'), "GET");
            echo $OUTPUT->confirm(get_string('confirmdeletefile', 'assignment', $file), $yesbtn, $nobtn);

            if (empty($mode)) {
                $this->view_footer();
            } else {
                print_footer('none');
            }
            die;
        }

        if($fs->delete_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id)===true) {
            $updated = new object();
            $updated->id = $submission->id;
            $updated->timemodified = time();
            if ($DB->update_record('assignment_submissions', $updated)) {
                add_to_log($this->course->id, 'assignment', 'upload', //TODO: add delete action to log
                        'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                $submission = $this->get_submission($userid);
                $this->update_grade($submission);
            }
            redirect($returnurl, '', 0);
        }

        // print delete error
        if (empty($mode)) {
            $this->view_header(get_string('delete'));
        } else {
            print_header(get_string('delete'));
        }
        notify(get_string('deletefilefailed', 'assignment'));
        print_continue($returnurl);
        if (empty($mode)) {
            $this->view_footer();
        } else {
            print_footer('none');
        }
        die;
    }

    function can_manage_responsefiles() {
        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;
        } else {
            return false;
        }
    }

    function view_upload_form() {
        global $CFG, $OUTPUT, $USER;
        require_once(dirname(__FILE__).'/simplefile.php');
        echo $OUTPUT->box_start('boxaligncenter uploadbox', 'simpleuploadbox');
        $fs = get_file_storage();
        // edit files in another page
        if ($submission = $this->get_submission($USER->id)) {
            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false)) {
                $str = get_string('editthisfile', 'assignment');
            } else {
                $str = get_string('uploadafile', 'assignment');
            }
        } else {
            $str = get_string('uploadafile', 'assignment');
        }
        $advlink = $OUTPUT->box_start();
        $advlink .= $OUTPUT->action_link(new moodle_url('/mod/assignment/type/simpleuploadsingle/upload.php', array('contextid'=>$this->context->id, 'userid'=>$USER->id)), get_string('advanceduploadafile', 'assignment_simpleuploadsingle'));
        $advlink .= $OUTPUT->box_end();

        $options = array('maxbytes'=>get_max_upload_file_size($CFG->maxbytes, $this->course->maxbytes, $this->assignment->maxbytes), 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        $mform = new mod_assignment_simpleuploadsinglesimple_form(new moodle_url('/mod/assignment/type/simpleuploadsingle/simpleupload.php'), array('caption'=>$str, 'cmid'=>$this->cm->id, 'contextid'=>$this->context->id, 'userid'=>$USER->id, 'options'=>$options, 'advancedlink'=>$advlink));
        if ($mform->is_cancelled()) {
            redirect(new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id)));
        } else if ($mform->get_data()) {
            $this->upload($mform);
            die();
        }
        $mform->display();
        echo $OUTPUT->box_end();
    }


    function upload($mform) {
        $action = required_param('action', PARAM_ALPHA);
        switch ($action) {
            case 'uploadresponse':
                $this->upload_responsefile($mform);
                break;
            case 'uploadfile':
                $this->upload_file($mform);
        }
    }

    function upload_file($mform) {
        global $CFG, $USER, $DB, $OUTPUT;
        $viewurl = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
        if (!is_enrolled($this->context, $USER, 'mod/assignment:submit')) {
            redirect($viewurl);
        }

        $submission = $this->get_submission($USER->id);
        $filecount = 0;
        if ($submission) {
            $filecount = $this->count_user_files($submission->id);
        }
        if ($this->isopen() && (!$filecount || $this->assignment->resubmit || !$submission->timemarked)) {
            if ($submission = $this->get_submission($USER->id)) {
                //TODO: change later to ">= 0", to prevent resubmission when graded 0
                if (($submission->grade > 0) and !$this->assignment->resubmit) {
                    redirect($viewurl, get_string('alreadygraded', 'assignment'));
                }
            }

            if ($formdata = $mform->get_data()) {
                $fs = get_file_storage();
                $submission = $this->get_submission($USER->id, true); //create new submission if needed
                $fs->delete_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id);

                if ($newfilename = $mform->get_new_filename('assignment_file')) {
                    $file = $mform->save_stored_file('assignment_file', $this->context->id, 'mod_assignment', 'submission',
                        $submission->id, '/', $newfilename);

                    $updates = new stdClass(); //just enough data for updating the submission
                    $updates->timemodified = time();
                    $updates->numfiles     = 1;
                    $updates->id     = $submission->id;
                    $DB->update_record('assignment_submissions', $updates);
                    add_to_log($this->course->id, 'assignment', 'upload', 'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                    $this->update_grade($submission);
                    $this->email_teachers($submission);

                    // Let Moodle know that an assessable file was uploaded (eg for plagiarism detection)
                    $eventdata = new stdClass();
                    $eventdata->modulename   = 'assignment';
                    $eventdata->cmid         = $this->cm->id;
                    $eventdata->itemid       = $submission->id;
                    $eventdata->courseid     = $this->course->id;
                    $eventdata->userid       = $USER->id;
                    $eventdata->file         = $file;
                    events_trigger('assessable_file_uploaded', $eventdata);
                }

                redirect($viewurl, '', 0);
            } else {
                // Add any error messages (i.e. file too big - lang/en/moodle.php, 'uploadformlimit') to the redirect screen
                $errorStr = get_string('uploaderror', 'assignment');
                if(sizeof($mform->simpleupload_get_errors())) {
                    $errorStr .= '<br />';
                    foreach($mform->simpleupload_get_errors() as $error) {
                        $errorStr .= '<br />'.$error;
                    }
                }
                redirect($viewurl, $errorStr, 10);  //submitting not allowed!
            }
        }

        redirect($viewurl);
    }

    function upload_responsefile($mform) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        $returnurl = new moodle_url("/mod/assignment/submissions.php", array('id'=>$this->cm->id,'userid'=>$userid,'mode'=>$mode,'offset'=>$offset)); //not xhtml, just url.

        if ($formdata = $mform->get_data() and $this->can_manage_responsefiles()) {
            $fs = get_file_storage();
            $submission = $this->get_submission($userid, true); //create new submission if needed
            $fs->delete_area_files($this->context->id, 'mod_assignment', 'response', $submission->id);

            if ($newfilename = $mform->get_new_filename('assignment_file')) {
                $file = $mform->save_stored_file('assignment_file', $this->context->id,
                        'mod_assignment', 'response',$submission->id, '/', $newfilename);
            }
            redirect($returnurl, get_string('uploadedfile'));
        } else {
            redirect($returnurl, get_string('uploaderror', 'assignment'));  //submitting not allowed!
        }
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'resubmit', get_string('allowresubmit', 'assignment'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowresubmit', 'assignment');
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'assignment'), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'assignment');
        $mform->setDefault('emailteachers', 0);

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[0] = get_string('courseuploadlimit') . ' ('.display_size($COURSE->maxbytes).')';
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'assignment'), $choices);
        $mform->setDefault('maxbytes', $CFG->assignment_maxbytes);

        $course_context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        plagiarism_get_form_elements_module($mform, $course_context);
    }

    function portfolio_exportable() {
        return true;
    }

    function send_file($filearea, $args) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/filelib.php');

        require_login($this->course, false, $this->cm);

        if ($filearea !== 'submission' && $filearea !== 'response') {
            return false;
        }

        $submissionid = (int)array_shift($args);

        if (!$submission = $DB->get_record('assignment_submissions', array('assignment'=>$this->assignment->id, 'id'=>$submissionid))) {
            return false;
        }

        if ($USER->id != $submission->userid and !has_capability('mod/assignment:grade', $this->context)) {
            return false;
        }

        $relativepath = implode('/', $args);
        $fullpath = '/'.$this->context->id.'/mod_assignment/'.$filearea.'/'.$submissionid.'/'.$relativepath;

        $fs = get_file_storage();

        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    }

    function extend_settings_navigation($node) {
        global $CFG, $USER, $OUTPUT;

        // get users submission if there is one
        $submission = $this->get_submission();
        if (is_enrolled($this->context, $USER, 'mod/assignment:submit')) {
            $editable = $this->isopen() && (!$submission || $this->assignment->resubmit || !$submission->timemarked);
        } else {
            $editable = false;
        }

        // If the user has submitted something add a bit more stuff
        if ($submission) {
            // Add a view link to the settings nav
            $link = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
            $node->add(get_string('viewmysubmission', 'assignment'), $link, navigation_node::TYPE_SETTING);
            if (!empty($submission->timemodified)) {
                $submissionnode = $node->add(get_string('submitted', 'assignment') . ' ' . userdate($submission->timemodified));
                $submissionnode->text = preg_replace('#([^,])\s#', '$1&nbsp;', $submissionnode->text);
                $submissionnode->add_class('note');
                if ($submission->timemodified <= $this->assignment->timedue || empty($this->assignment->timedue)) {
                    $submissionnode->add_class('early');
                } else {
                    $submissionnode->add_class('late');
                }
            }
        }

        // Check if the user has uploaded any files, if so we can add some more stuff to the settings nav
        if ($submission && is_enrolled($this->context, $USER, 'mod/assignment:submit') && $this->count_user_files($submission->id)) {
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false)) {
                $filenode = $node->add(get_string('submission', 'assignment'));
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $mimetype = $file->get_mimetype();
                    $link = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment', 'submission/'.$submission->id.'/'.$filename);
                    $filenode->add($filename, $link, navigation_node::TYPE_SETTING, null, null, new pix_icon(file_mimetype_icon($mimetype), ''));
                }
            }
        }
    }

    /**
     * creates a zip of all assignment submissions and sends a zip to the browser
     */
    function download_submissions() {
        global $CFG,$DB;
        require_once($CFG->libdir.'/filelib.php');

        $submissions = $this->get_submissions('','');
        if (empty($submissions)) {
            print_error('errornosubmissions', 'assignment');
        }
        $filesforzipping = array();
        $fs = get_file_storage();

        $groupmode = groups_get_activity_groupmode($this->cm);
        $groupid = 0;   // All users
        $groupname = '';
        if ($groupmode) {
            $groupid = groups_get_activity_group($this->cm, true);
            $groupname = groups_get_group_name($groupid).'-';
        }
        $filename = str_replace(' ', '_', clean_filename($this->course->shortname.'-'.$this->assignment->name.'-'.$groupname.$this->assignment->id.".zip")); //name of new zip file.
        foreach ($submissions as $submission) {
            $a_userid = $submission->userid; //get userid
            if ((groups_is_member($groupid,$a_userid)or !$groupmode or !$groupid)) {
                $a_assignid = $submission->assignment; //get name of this assignment for use in the file names.
                $a_user = $DB->get_record("user", array("id"=>$a_userid),'id,username,firstname,lastname'); //get user firstname/lastname

                $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false);
                foreach ($files as $file) {
                    //get files new name.
                    $fileext = strstr($file->get_filename(), '.');
                    $fileoriginal = str_replace($fileext, '', $file->get_filename());
                    $fileforzipname =  clean_filename(fullname($a_user) . "_" . $fileoriginal."_".$a_userid.$fileext);
                    //save file name to array for zipping.
                    $filesforzipping[$fileforzipname] = $file;
                }
            }
        } // End of foreach
        if ($zipfile = assignment_pack_files($filesforzipping)) {
            send_temp_file($zipfile, $filename); //send file and delete after sending.
        }
    }
}

class mod_assignment_simpleuploadsingle_response_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;

        // visible elements
        $mform->addElement('filepicker', 'assignment_file', get_string('uploadafile'), null, $instance->options);

        // hidden params
        $mform->addElement('hidden', 'id', $instance->cm->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid', $instance->contextid);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'uploadresponse');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'mode', $instance->mode);
        $mform->setType('mode', PARAM_ALPHA);
        $mform->addElement('hidden', 'offset', $instance->offset);
        $mform->setType('offset', PARAM_INT);
        $mform->addElement('hidden', 'forcerefresh' , $instance->forcerefresh);
        $mform->setType('forcerefresh', PARAM_INT);
        $mform->addElement('hidden', 'userid', $instance->userid);
        $mform->setType('userid', PARAM_INT);

        // buttons
        $this->add_action_buttons(false, get_string('uploadthisfile'));
    }
}