<?php

/*
    Copyright 2009-2026 Edward L. Platt <ed@elplatt.com>
    Copyright 2013-2026 Michael Roach <https://github.com/FlinchyMcFlincherson>
    Copyright 2013-2026 Chris Murray <chris.f.murray@hotmail.co.uk>
    
    This file is part of the Seltzer CRM Project
    training.inc.php - training tracking module
    
    Seltzer is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.
    
    Seltzer is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    
    You should have received a copy of the GNU General Public License
    along with Seltzer.  If not, see <http://www.gnu.org/licenses/>.
*/

// Installation functions //////////////////////////////////////////////////////

/**
 * @return This module's revision number. Each new release should increment
 * this number.
 */
function training_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function training_permissions () {
    return array(
        'training_view'
        , 'training_edit'
        , 'training_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function training_install($old_revision = 0) {
    global $db_connect;
    if ($old_revision < 1) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `training_course` (
                `course_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT
                , `course_name` varchar(255) NOT NULL
                , `course_description` varchar(255) NOT NULL
                , `course_owner` mediumint(8) NOT NULL
                , `course_createdBy` mediumint(8) NOT NULL
                , `course_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                , PRIMARY KEY (`course_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        // Create lookup table for member training history records
        $sql = "
            CREATE TABLE IF NOT EXISTS `training_history` (
                `thid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT
                , `course_id` mediumint(8) unsigned NOT NULL
                , `cid` mediumint(8) unsigned NOT NULL
                , `completed` date DEFAULT NULL
                , `instructor` mediumint(8) unsigned NOT NULL
                , PRIMARY KEY (`thid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        // Set default permissions
        $roles = array(
            '1' => 'authenticated'
            , '2' => 'member'
            , '3' => 'director'
            , '4' => 'president'
            , '5' => 'vp'
            , '6' => 'secretary'
            , '7' => 'treasurer'
            , '8' => 'webAdmin'
        );
        $default_perms = array(
            'director' => array('training_view', 'training_edit', 'training_delete')
            , 'webAdmin' => array('training_view', 'training_edit', 'training_delete')
        );
        foreach ($roles as $rid => $role) {
            $esc_rid = mysqli_real_escape_string($db_connect, $rid);
            if (array_key_exists($role, $default_perms)) {
                foreach ($default_perms[$role] as $perm) {
                    $esc_perm = mysqli_real_escape_string($db_connect, $perm);
                    $sql = "
                        INSERT INTO `role_permission`
                        (`rid`, `permission`)
                        VALUES
                        ('$esc_rid', '$esc_perm')
                    ";
                    $res = mysqli_query($db_connect, $sql);
                    if (!$res) crm_error(mysqli_error($res));
                }
            }
        }
    }
}

// Utility functions ///////////////////////////////////////////////////////////

/**
 * Generate a descriptive string for a single training course.
 * @param $course_id The course_id of the training course to describe.
 * @return The description string.
 */
function training_course_description ($course_id) {
    // Get training data
    if (user_access('training_view')) {
        $data = crm_get_data('training_course', array('course_id' => $course_id));
    }
    if (empty($data)) {
        return '';
    }
    $training_course = $data[0];
    // Construct description
    $description = 'Training Course: ';
    $description .= $training_course['course_name'];
    $description .= ' - ';
    $description .= $training_course['course_description'];
    return $description;
}

/**
 * Generate a descriptive string for a single training record.
 * @param $course_id The course_id of the training record to describe.
 * @return The description string.
 */
function training_history_description ($thid) {
    // Get training data
    if (user_access('training_view')) {
        $data = crm_get_data('training_history', array('thid' => $thid));
    }
    if (empty($data)) {
        return '';
    }
    $training_record = $data[0];
    // Construct description
    $description = 'Training Record: ';
    $description .= $training_record['cid'];
    $description .= ' - ';
    $description .= $training_record['instructor'];
    $description .= ' - ';
    $description .= $training_record['completed'];
    return $description;
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more traning courses.
 * @param $opts An associative array of options, possible keys are:
 *   'course_id' If specified, returns a single training course with the matching id,
 *   'filter' An array mapping filter names to filter values
 * @return An array with each element representing a training course.
 */
function training_course_data ($opts = array()) {
    global $db_connect;
    // Construct query for training_courses
    $sql = "
        SELECT *
        FROM `training_course`
        WHERE 1
    ";
    if (!empty($opts['course_id'])) {
        $course_id = mysqli_real_escape_string($opts['course_id']);
        $sql .= "
            AND `training_course`.`course_id`='$course_id'
        ";
    }
    $sql .= "
        ORDER BY `course_id` ASC
    ";
    $res = mysqli_query($db_connect, $sql);
    if (!$res) { crm_error(mysqli_error($res)); }
    // Store data
    $training_courses = array();
    $row = mysqli_fetch_assoc($res);
    while ($row) {
        $training_courses[] = $row;
        $row = mysqli_fetch_assoc($res);
    }
    return $training_courses;
}

/**
 * Return data for one or more training records.
 * @param $opts An associative array of options, possible keys are:
 *   'thid' If specified, returns a single memeber with the matching training id;
 *   'cid' If specified, returns all trainings completed by the contact with specified id;
 *   'filter' An array mapping filter names to filter values;
 *   'join' A list of tables to join to the training table.
 * @return An array with each element representing a single training record.
 */
function training_history_data ($opts = array()) {
    global $db_connect;
    // Query database
    $sql = "
        SELECT
        `thid`
        , `course_id`
        , `cid`
        , `completed`
        , `instructor`
        FROM `training_history`
        WHERE 1
    ";
    if (!empty($opts['thid'])) {
        $esc_thid = mysqli_real_escape_string($db_connect, $opts['thid']);
        $sql .= "
            AND `thid`='$esc_thid'
        ";
    }
    if (!empty($opts['cid'])) {
        if (is_array($opts['cid'])) {
            $terms = array();
            foreach ($opts['cid'] as $cid) {
                $esc_cid = mysqli_real_escape_string($db_connect, $cid);
                $terms[] = "'$cid'";
            }
            $sql .= "
                AND `cid` IN (" . implode(', ', $terms) . ")
            ";
        } else {
            $esc_cid = mysqli_real_escape_string($db_connect, $opts['cid']);
            $sql .= "
                AND `cid`='$esc_cid'
            ";
        }
    }
    $sql .= "
        ORDER BY `thid` ASC
    ";
    $res = mysqli_query($db_connect, $sql);
    if (!$res) crm_error(mysqli_error($res));
    // Store data
    $trainings = array();
    $row = mysqli_fetch_assoc($res);
    while (!empty($row)) {
        $trainings[] = $row;
        $row = mysqli_fetch_assoc($res);
    }
    // Return data
    return $trainings;
}

/**
 * Implementation of hook_data_alter().
 * @param $type The type of the data being altered.
 * @param $data An array of structures of the given $type.
 * @param $opts An associative array of options.
 * @return An array of modified structures.
 */
function training_data_alter ($type, $data = array(), $opts = array()) {
    switch ($type) {
        case 'contact':
            // Get cids of all contacts passed into $data
            $cids = array();
            foreach ($data as $contact) {
                $cids[] = $contact['cid'];
            }
            // Add the cids to the options
            $training_opts = $opts;
            $training_opts['cid'] = $cids;
            // Get an array of training structures for each cid
            $training_data = crm_get_data('training_history', $training_opts);
            // Create a map from cid to an array of training structures
            $cid_to_trainings = array();
            foreach ($training_data as $training) {
                $cid_to_trainings[$training_history['cid']][] = $training;
            }
            // Add training structures to the contact structures
            foreach ($data as $i => $contact) {
                if (array_key_exists($contact['cid'], $cid_to_trainings)) {
                    $trainings = $cid_to_trainings[$contact['cid']];
                    $data[$i]['trainings'] = $trainings;
                }
            }
            break;
    }
    return $data;
}

/**
 * Save a training course structure. If $training_course has a 'course_id' element, an existing course will
 * be updated, otherwise a new course will be created.
 * @param $thid The training structure
 * @return The training structure as it now exists in the database.
 */
function training_course_save ($training_course) {
    global $db_connect;
    // Escape values
    $training_course['course_createdBy'] = user_id();
    $training_course['course_created'] = date("Y-m-d H:i:s");
    $fields = array('course_id', 'course_name', 'course_description', 'course_owner', 'course_createdBy', 'course_created');
    if (isset($training_course['course_id'])) {
        // Update existing training course
        $esc_course_id = mysqli_real_escape_string($course_id);
        $clauses = array();
        foreach ($fields as $tc) {
            if (isset($training_course[$tc]) && $tc != 'course_id') {
                $clauses[] = "`$tc`='" . mysqli_real_escape_string($db_connect, $training_course[$tc]) . "' ";
            }
        }
        $sql = "
            UPDATE `training_course`
            SET " . implode(', ', $clauses) . "
            WHERE `course_id`='$esc_course_id'
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        message_register('Training course updated');
    } else {
        // Insert new training course
        $cols = array();
        $values = array();
        foreach ($fields as $tc) {
            if (isset($training_course[$tc])) {
                $cols[] = "`$tc`";
                $values[] = "'" . mysqli_real_escape_string($db_connect, $training_course[$tc]) . "'";
            }
        }
        $sql = "
            INSERT INTO `training_course`
            (" . implode(', ', $cols) . ")
            VALUES
            (" . implode(', ', $values) . ")
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        $course_id = mysqli_insert_id($db_connect);
        message_register('Training course added');
    }
    return crm_get_one('training_course', array('course_id'=>$course_id));
}

/**
 * Delete a training course.
 * @param $training The training course data structure to delete, must have a 'course_id' element.
 */
function training_course_delete ($training_course) {
    global $db_connect;
    $esc_course_id = mysqli_real_escape_string($db_connect, $training_course['course_id']);
    $sql = "
        DELETE FROM `training_course`
        WHERE `course_id`='$esc_course_id'
    ";
    $res = mysqli_query($db_connect, $sql);
    if (!$res) crm_error(mysqli_error($res));
    if (mysqli_affected_rows($db_connect) > 0) {
        message_register('Training course deleted.');
    }
}

/**
 * Save a training structure. If $training has a 'thid' element, an existing training will
 * be updated, otherwise a new training will be created.
 * @param $thid The training structure
 * @return The training structure as it now exists in the database.
 */
function training_save ($training) {
    global $db_connect;
    // Escape values
    $fields = array('thid', 'course_id', 'cid', 'completed', 'instructor');
    if (isset($training_history['thid'])) {
        // Update existing training
        $thid = $training_history['thid'];
        $esc_thid = mysqli_real_escape_string($thid);
        $clauses = array();
        foreach ($fields as $th) {
            if (isset($training_history[$th]) && $th != 'thid') {
                $clauses[] = "`$th`='" . mysqli_real_escape_string($db_connect, $training_history[$th]) . "' ";
            }
        }
        $sql = "
            UPDATE `training_history`
            SET " . implode(', ', $clauses) . "
            WHERE `thid`='$esc_thid'
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        message_register('Training record updated');
    } else {
        // Insert new training
        $cols = array();
        $values = array();
        foreach ($fields as $th) {
            if (isset($training_history[$th])) {
                $cols[] = "`$th`";
                $values[] = "'" . mysqli_real_escape_string($db_connect, $training_history[$th]) . "'";
            }
        }
        $sql = "
            INSERT INTO `training_history`
            (" . implode(', ', $cols) . ")
            VALUES
            (" . implode(', ', $values) . ")
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        $thid = mysqli_insert_id($db_connect);
        message_register('Training record added');
    }
    return crm_get_one('training_history', array('thid'=>$thid));
}

/**
 * Delete a training record.
 * @param $training The training data structure to delete, must have a 'thid' element.
 */
function training_delete ($training) {
    global $db_connect;
    $esc_thid = mysqli_real_escape_string($db_connect, $training_history['thid']);
    $sql = "
        DELETE FROM `training_history`
        WHERE `thid`='$esc_thid'
    ";
    $res = mysqli_query($db_connect, $sql);
    if (!$res) crm_error(mysqli_error($res));
    if (mysqli_affected_rows($db_connect) > 0) {
        message_register('Training record deleted.');
    }
}

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return a table structure for a table of training courses.
 * @param $opts The options to pass to training_course_data().
 * @return The table structure.
 */
function training_course_table ($opts) {
    // Determine settings
    $export = false;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get training data
    $data = crm_get_data('training_course', $opts);
    if (count($data) < 1) {
        return array();
    }
    // Initialize table
    $table = array(
        "id" => ''
        , "class" => ''
        , "rows" => array()
        , "columns" => array()
    );
    // Add columns
    if (user_access('training_view')) {
        $table['columns'][] = array("title"=>'Name', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Description', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Owner', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Created By', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Created', 'class'=>'', 'id'=>'');
    }
    // Add ops column
    if (!$export && (user_access('training_edit'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $training_course) {
        // Add training data
        $row = array();
        if (user_access('training_view')) {
            // Add cells
            $row[] = $training_course['course_name'];
            $row[] = $training_course['course_description'];
            $row[] = theme('contact_name', $training_course['course_owner'], true);
            $row[] = theme('contact_name', $training_course['course_createdBy'], true);
            $row[] = $training_course['course_created'];
        }
        if (!$export && (user_access('training_edit'))) {
            // Construct ops array
            $ops = array();
            // Add edit op
            if (user_access('training_edit')) {
                $ops[] = '<a href=' . crm_url('training_course&course_id=' . $training_course['course_id'] . '#tab-edit') . '>edit</a> ';
            }
            // Add delete op
            if (user_access('training_delete')) {
                $ops[] = '<a href=' . crm_url('delete&type=training_course&id=' . $training_course['course_id']) . '>delete</a>';
            }
            // Add ops row
            $row[] = join(' ', $ops);
        }
        $table['rows'][] = $row;
    }
    return $table;
}

/**
 * Return a table structure for a table of training records.
 * @param $opts The options to pass to training_history_data().
 * @return The table structure.
 */
function training_history_table ($opts) {
    // Determine settings
    $export = false;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get training data
    $data = crm_get_data('training_history', $opts);
    if (count($data) < 1) {
        return array();
    }
    // Get contact info
    $contact_opts = array();
    foreach ($data as $row) {
        $contact_opts['cid'][] = $row['cid'];
    }
    $contact_data = crm_get_data('contact', $contact_opts);
    $cid_to_contact = crm_map($contact_data, 'cid');
    // Initialize table
    $table = array(
        "id" => ''
        , "class" => ''
        , "rows" => array()
        , "columns" => array()
    );
    // Add columns
    if (user_access('training_view') || $opts['cid'] == user_id()) {
        $table['columns'][] = array("title"=>'Name', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Course', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Completed', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Instructor', 'class'=>'', 'id'=>'');
    }
    // Add ops column
    if (!$export && (user_access('training_edit'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $training) {
        // Add training data
        $row = array();
        if (user_access('training_view') || $opts['cid'] == user_id()) {
            // Add cells
            $row[] = theme('contact_name', $cid_to_contact[$training_history['cid']], true);
            $row[] = $training_history['course_id'];
            $row[] = $training_history['completed'];
            $row[] = theme('contact_name', $cid_to_contact[$training_history['instructor']], true);
        }
        if (!$export && (user_access('training_edit'))) {
            // Construct ops array
            $ops = array();
            // Add edit op
            if (user_access('training_edit')) {
                $ops[] = '<a href=' . crm_url('training&thid=' . $training_history['thid'] . '#tab-edit') . '>edit</a> ';
            }
            // Add delete op
            if (user_access('training_delete')) {
                $ops[] = '<a href=' . crm_url('delete&type=training&id=' . $training_history['thid']) . '>delete</a>';
            }
            // Add ops row
            $row[] = join(' ', $ops);
        }
        $table['rows'][] = $row;
    }
    return $table;
}

// Forms ///////////////////////////////////////////////////////////////////////

/**
 * Return The form structure for adding a training course.
 */
function training_course_add_form () {
    // Ensure user is allowed to edit training courses
    if (!user_access('training_edit')) {
        error_register('User does not have permission: training_edit');
        return null;
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'training_course_add'
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Add Training Course'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Name'
                        , 'name' => 'course_name'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Description'
                        , 'name' => 'course_description'
                    )
                    ,array(
                        'type' => 'text'
                        , 'label' => 'Owner'
                        , 'name' => 'course_owner'
                        , 'autocomplete' => 'contact_name'
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Add'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * Returns the form structure for editing a training course.
 * @param $course_id The course_id of the course to edit.
 * @return The form structure.
 */
function training_course_edit_form ($course_id) {
    // Ensure user is allowed to edit training courses
    if (!user_access('training_edit')) {
        error_register('User does not have permission: training_edit');
        return array();
    }
    // Get training data
    $data = crm_get_data('training_course', array('course_id'=>$course_id));
    $training_course = $data[0];
    if (empty($training_course) || count($training_course) < 1) {
        return array();
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'training_course_edit'
        , 'hidden' => array(
            'course_id' => $course_id
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Edit Training Course'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Name'
                        , 'name' => 'course_name'
                        , 'value' => $training_course['course_name']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Description'
                        , 'name' => 'course_description'
                        , 'value' => $training_course['course_description']
                    )
                    ,array(
                        'type' => 'text'
                        , 'label' => 'Owner'
                        , 'name' => 'course_owner'
                        , 'value' => $training_course['course_owner']
                        , 'autocomplete' => 'contact_name'
                    ),
                    array(
                        'type' => 'submit',
                        'value' => 'Update'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * Return the delete course form structure.
 * @param $course_id The course_id of the course to delete.
 * @return The form structure.
 */
function training_course_delete_form ($course_id) {
    // Ensure user is allowed to delete training courses
    if (!user_access('training_delete')) {
        error_register('User does not have permission: training_delete');
        return null;
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'training_course_delete'
        , 'hidden' => array(
            'course_id' => $course_id
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Delete Training Course'
                , 'fields' => array(
                    array(
                        'type' => 'message'
                        , 'value' => '<p>Are you sure you want to delete the training course "' . training_course_description($course_id) . '"? This cannot be undone.',
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Delete'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * @return the form structure for a course import form.
 */
function training_course_import_form () {
    return array(
        'type' => 'form'
        , 'method' => 'post'
        , 'enctype' => 'multipart/form-data'
        , 'command' => 'training_course_import'
        , 'fields' => array(
            array(
                'type' => 'message'
                , 'value' => '<p>To import courses, upload a csv.  The csv should have a header row with the following fields:</p>
                <ul>
                <li>Course Name</li>
                <li>Course Description</li>
                <li>Course Owner</li>
                </ul>'
            )
            , array(
                'type' => 'file'
                , 'label' => 'CSV File'
                , 'name' => 'training-course-file'
            )
            , array(
                'type' => 'submit'
                , 'value' => 'Import'
            )
        )
    );
}

/**
 * Return the form structure for the add training record form.
 * @param The cid of the contact to add a training record for.
 * @return The form structure.
 */
function training_history_add_form () {
    // Ensure user is allowed to edit training records
    if (!user_access('training_edit')) {
        error_register('User does not have permission: training_edit');
        return null;
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'training_history_add'
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Add Training Record'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Member'
                        , 'name' => 'cid'
                        , 'autocomplete' => 'contact_name'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Course'
                        , 'name' => 'course_id'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Completed'
                        , 'name' => 'completed'
                        , 'value' => date("Y-m-d")
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Instructor'
                        , 'name' => 'instructor'
                        , 'autocomplete' => 'contact_name'
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Add'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * Return the form structure for an edit training record form.
 * @param $thid The thid of the training record to edit.
 * @return The form structure.
 */
function training_history_edit_form ($thid) {
    // Ensure user is allowed to edit training
    if (!user_access('training_edit')) {
        error_register('User does not have permission: training_edit');
        return array();
    }
    // Get training data
    $data = crm_get_data('training_history', array('thid'=>$thid));
    $training_history = $data[0];
    if (empty($training_history) || count($training_history) < 1) {
        return array();
    }
    // Get corresponding contact data
    $contact = crm_get_one('contact', array('cid'=>$training_history['cid']));
    // Construct member name
    $name = theme('contact_name', $contact, true);
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'training_history_edit'
        , 'hidden' => array(
            'thid' => $thid
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Edit Training Record'
                , 'fields' => array(
                    array(
                        'type' => 'readonly'
                        , 'label' => 'Name'
                        , 'value' => $name
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Completed'
                        , 'name' => 'completed'
                        , 'value' => $training_history['completed']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Instructor'
                        , 'name' => 'instructor'
                        , 'value' => $training_history['instructor']
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Update'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * Return the delete training record form structure.
 * @param $thid The thid of the training record to delete.
 * @return The form structure.
 */
function training_history_delete_form ($thid) {
    // Ensure user is allowed to delete training records
    if (!user_access('training_delete')) {
        error_register('User does not have permission: training_delete');
        return null;
    }
    // Get training data
    $data = crm_get_data('training_history', array('thid'=>$thid));
    $training_history = $data[0];
    // Construct training_history name
    $training_name = "training:$training_history[thid] course:$training_history[cid] $training_history[course_id] $training_history[instructor]";
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'training_history_delete'
        , 'hidden' => array(
            'thid' => $training_history['thid']
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Delete Training Record'
                , 'fields' => array(
                    array(
                        'type' => 'message'
                        , 'value' => '<p>Are you sure you want to delete the training record "' . $training_name . '"? This cannot be undone.</p>'
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Delete'
                    )
                )
            )
        )
    );
    return $form;
}

// Request Handlers ////////////////////////////////////////////////////////////

/**
 * Handle training course add request.
 * @return The url to display on completion.
 */
function command_training_course_add() {
    // Verify permissions
    if (!user_access('training_edit')) {
        error_register('Permission denied: training_edit');
        return crm_url('training_course&course_id=' . $_POST['course_id']);
    }
    training_course_save($_POST);
    return crm_url('trainings');
}

/**
 * Handle training course update request.
 * @return The url to display on completion.
 */
function command_training_course_edit() {
    // Verify permissions
    if (!user_access('training_edit')) {
        error_register('Permission denied: training_edit');
        return crm_url('training_course&course_id=' . $_POST['course_id']);
    }
    // Save training course
    training_course_save($_POST);
    return crm_url('training_course&course_id=' . $_POST['course_id'] . '&tab=edit');
}

/**
 * Handle training course delete request.
 * @return The url to display on completion.
 */
function command_training_course_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('training_delete')) {
        error_register('Permission denied: training_delete');
        return crm_url('training_course&course_id=' . $esc_post['course_id']);
    }
    training_course_delete($_POST);
    return crm_url('trainings');
}

/**
 * Handle training add request.
 * @return The url to display on completion.
 */
function command_training_history_add() {
    // Verify permissions
    if (!user_access('training_edit')) {
        error_register('Permission denied: training_edit');
        return crm_url('training&thid=' . $_POST['thid']);
    }
    training_save($_POST);
    return crm_url('contact&cid=' . $_POST['cid'] . '&tab=trainings');
}

/**
 * Handle training record update request.
 * @return The url to display on completion.
 */
function command_training_history_edit() {
    // Verify permissions
    if (!user_access('training_edit')) {
        error_register('Permission denied: training_edit');
        return crm_url('training&thid=' . $_POST['thid']);
    }
    // Save training record
    training_save($_POST);
    return crm_url('training&thid=' . $_POST['thid'] . '&tab=edit');
}

/**
 * Handle training record delete request.
 * @return The url to display on completion.
 */
function command_training_history_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('training_delete')) {
        error_register('Permission denied: training_delete');
        return crm_url('training&thid=' . $esc_post['thid']);
    }
    training_delete($_POST);
    return crm_url('members');
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function training_page_list () {
    $pages = array();
    if (user_access('training_view')) {
        $pages[] = 'trainings';
        $pages[] = 'training_course';
    }
    return $pages;
}

/**
 * Page hook. Adds module content to a page before it is rendered.
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
 */
function training_page (&$page_data, $page_name, $options) {
    switch ($page_name) {
        case 'contact':
            // Capture contact cid
            $cid = $options['cid'];
            if (empty($cid)) {
                return;
            }
            // Add trainings tab
            if (user_access('training_edit') || $cid == user_id()) {
                $trainings = theme('table', crm_get_table('training_history', array('cid' => $cid)));
                if (user_access('training_edit')) {
                    $trainings .= theme('form', crm_get_form('training_history_add', $cid));
                }
                page_add_content_bottom($page_data, $trainings, 'Training');
            }
            break;
        case 'trainings':
            page_set_title($page_data, 'Training');
            if (user_access('training_view')) {
                $training_courses = theme('table', crm_get_table('training_course'));
                $training_courses .= theme('form', crm_get_form('training_course_add'));
                page_add_content_top($page_data, $training_courses, 'Training Courses');
                $training_history = theme('table', crm_get_table('training_history', array('join'=>array('contact', 'member'), 'show_export'=>true)));
                $training_history .= theme('form', crm_get_form('training_history_add', $cid));
                page_add_content_top($page_data, $training_history, 'Training History');
            }
            break;
        case 'training_history':
            // Capture training id
            $thid = $options['thid'];
            if (empty($thid)) {
                return;
            }
            // Set page title
            page_set_title($page_data, training_description($thid));
            // Add edit tab
            if (user_access('training_view') || user_access('training_edit')) {
                page_add_content_top($page_data, theme('training_history_edit_form', $thid), 'Edit');
            }
            break;
    }
}
