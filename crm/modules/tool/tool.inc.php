<?php

/*
    Copyright 2009-2026 Edward L. Platt <ed@elplatt.com>
    Copyright 2013-2026 Michael Roach <https://github.com/FlinchyMcFlincherson>
    Copyright 2013-2026 Chris Murray <chris.f.murray@hotmail.co.uk>
    
    This file is part of the Seltzer CRM Project
    tool.inc.php - Tool module
    
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
function tool_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function tool_permissions () {
    return array(
        'tool_view'
        , 'tool_edit'
        , 'tool_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function tool_install($old_revision = 0) {
    global $db_connect;
    if ($old_revision < 1) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `tool` (
                `tool_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT
                , `tool_name` varchar(255) NOT NULL
                , `tool_manufacturer` varchar(255) NOT NULL
                , `tool_modelNum` varchar(255) NOT NULL
                , `tool_serialNum` varchar(255) NOT NULL
                , `tool_class` varchar(255) NOT NULL
                , `tool_inductionRequired` tinyint(1) NOT NULL
                , `tool_acquiredDate` date DEFAULT NULL
                , `tool_releasedDate` date DEFAULT NULL
                , `tool_purchasePrice` decimal(4,2) NOT NULL
                , `tool_deprecSched` varchar(255) NOT NULL
                , `tool_recoveredCost` decimal(4,2) NOT NULL
                , `tool_owner` mediumint(8) NOT NULL
                , `tool_createdBy` mediumint(8) NOT NULL
                , `tool_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                , `tool_notes` text NOT NULL
                , PRIMARY KEY (`tool_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        // Create default permissions
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
            'member' => array('tool_view')
            , 'director' => array('tool_view', 'tool_edit', 'tool_delete')
            , 'webAdmin' => array('tool_view', 'tool_edit', 'tool_delete')
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
 * Return the themed html description for a tool.
 * @param $tool_id The tool_id of the tool.
 * @return The themed html string.
 */
function tool_description ($tool_id) {
    // Get tool data
    if (user_access('tool_view')) {
        $data = crm_get_data('tool', array('tool_id' => $tool_id));
    }
    if (empty($data)) {
        return '';
    }
    $tool = $data[0];
    // Construct description
    $description = 'Tool: ';
    $description .= $tool['tool_name'];
    $description .= ', Manufacturer: ';
    $description .= $tool['tool_manufacturer'];
    $description .= ', Model :';
    $description .= $tool['tool_modelNum'];
    return $description;
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more tools.
 * @param $opts An associative array of options, possible keys are:
 *   'tool_id' If specified, returns a single tool with the matching id,
 *   'filter' An array mapping filter names to filter values
 * @return An array with each element representing a tool.
 */
function tool_data ($opts = array()) {
    global $db_connect;
    // Construct query for tools
    $sql = "
        SELECT *
        FROM `tool`
        WHERE 1
    ";
    if (!empty($opts['tool_id'])) {
        $tool_id = mysqli_real_escape_string($db_connect, $opts['tool_id']);
        $sql .= "
            AND `tool`.`tool_id`='$tool_id'
        ";
    }
    $sql .= "
        ORDER BY `tool_id` ASC
    ";
    $res = mysqli_query($db_connect, $sql);
    if (!$res) { crm_error(mysqli_error($res)); }
    // Store data
    $tools = array();
    $row = mysqli_fetch_assoc($res);
    while (!empty($row)) {
        $tools[] = $row;
        $row = mysqli_fetch_assoc($res);
    }
    return $tools;
}

/**
 * Save a tool structure. If $tool has a 'tool_id' element, an existing tool will
 * be updated, otherwise a new tool will be created.
 * @param $tool The tool structure
 * @return The tool structure with as it now exists in the database.
 */
function tool_save ($tool) {
    global $db_connect;
    // Escape values
    $tool['tool_createdBy'] = user_id();
    $tool['tool_created'] = date("Y-m-d H:i:s");
    if (!($tool['tool_inductionRequired'] == '1')) {
        $tool['tool_inductionRequired'] = '0';
    }
    $fields = array(
        'tool_id', 'tool_name', 'tool_manufacturer', 'tool_modelNum', 'tool_serialNum'
        , 'tool_class', 'tool_inductionRequired', 'tool_acquiredDate', 'tool_releasedDate'
        , 'tool_purchasePrice', 'tool_deprecSched', 'tool_recoveredCost', 'tool_owner'
        , 'tool_createdBy', 'tool_created', 'tool_notes'
    );
    if (isset($tool['tool_id'])) {
        // Update existing tool
        $esc_tool_id = mysqli_real_escape_string($db_connect, $tool['tool_id']);
        $clauses = array();
        foreach ($fields as $t) {
            if (isset($tool[$t]) && $t != 'tool_id' && $t != 'tool_createdBy' && $t != 'tool_created') {
                $clauses[] = "`$t`='" . mysqli_real_escape_string($db_connect, $tool[$t]) . "' ";
            }
        }
        $sql = "
            UPDATE `tool`
            SET " . implode(', ', $clauses) . "
            WHERE `tool_id`='$esc_tool_id'
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        message_register('Tool updated');
    } else {
        // Insert new tool
        $cols = array();
        $values = array();
        foreach ($fields as $t) {
            if (isset($tool[$t])) {
                $cols[] = "`$t`";
                $values[] = "'" . mysqli_real_escape_string($db_connect, $tool[$t]) . "'";
            }
        }
        $sql = "
            INSERT INTO `tool`
            (" . implode(', ', $cols) . ")
            VALUES
            (" . implode(', ', $values) . ")
        ";
        $res = mysqli_query($db_connect, $sql);
        if (!$res) crm_error(mysqli_error($res));
        $esc_tool_id = mysqli_insert_id($db_connect);
        message_register('Tool added');
    }
    return crm_get_one('tool', array('tool_id'=>$esc_tool_id));
}

/**
 * Delete a tool.
 * @param $tool The tool data structure to delete, must have a 'tool_id' element.
 */
function tool_delete ($tool) {
    global $db_connect;
    $esc_tool_id = mysqli_real_escape_string($db_connect, $tool['tool_id']);
    $sql = "
        DELETE FROM `tool`
        WHERE `tool_id`='$esc_tool_id'
    ";
    $res = mysqli_query($db_connect, $sql);
    if (!$res) crm_error(mysqli_error($res));
    if (mysqli_affected_rows($db_connect) > 0) {
        message_register('Tool deleted.');
    }
}

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return an abbreviated table structure representing tools.
 * @param $opts Options to pass to tool_data().
 * @return The table structure.
 */
function tool_table ($opts) {
    // Ensure user is allowed to view tools
    if (!user_access('tool_view')) {
        return null;
    }
    // Get tool data
    $tools = tool_data($opts);
    // Create table structure
    $table = array(
        'id' => ''
        , 'class' => ''
        , 'rows' => array()
    );
    // Add columns
    $table['columns'] = array();
    if (user_access('tool_view')) {
        $table['columns'][] = array("title"=>'Name');
        $table['columns'][] = array("title"=>'Manufacturer');
        $table['columns'][] = array("title"=>'Model Number');
        $table['columns'][] = array("title"=>'Serial Number');
        $table['columns'][] = array("title"=>'Induction Required');
        $table['columns'][] = array("title"=>'Owner');
        $table['columns'][] = array("title"=>'Notes');
        if (user_access('tool_edit')) {
            $table['columns'][] = array("title"=>'Ops');
        }
    }
    // Loop through tool data
    foreach ($tools as $tool) {
        // Add tool data to table
        $row = array();
        // Construct name
        $tool_link = theme('tool_name', $tool, true);
        if (user_access('tool_edit')) {
            // Add cells
            $row[] = $tool_link;
            $row[] = $tool['tool_manufacturer'];
            $row[] = $tool['tool_modelNum'];
            $row[] = $tool['tool_serialNum'];
            $row[] = $tool['tool_inductionRequired'] ? 'Yes' : 'No';
            $row[] = theme('contact_name', $tool['tool_owner'], true);
            $row[] = $tool['tool_notes'];
        }
        // Construct ops array
        $ops = array();
        // Add edit op
        if (user_access('tool_edit')) {
            $ops[] = '<a href=' . crm_url('tool&tool_id=' . $tool['tool_id'] . '&tab=edit') . '>edit</a>';
        }
        // Add delete op
        if (user_access('tool_delete')) {
            $ops[] = '<a href=' . crm_url('delete&type=tool&amp;id=' . $tool['tool_id']) . '>delete</a>';
        }
        // Add ops row
        if (user_access('tool_edit')) {
            $row[] = join(' ', $ops);
        }
        // Add row to table
        $table['rows'][] = $row;
    }
    // Return table
    return $table;
}

/**
 * Return a detailed table structure representing tools.
 * @param $opts Options to pass to tool_data().
 * @return The table structure.
 */
function tool_detail_table ($opts) {
    // Ensure user is allowed to view tools
    if (!user_access('tool_view')) {
        return null;
    }
    // Get tool data
    $tools = tool_data($opts);
    // Create table structure
    $table = array(
        'id' => ''
        , 'class' => ''
        , 'rows' => array()
    );
    // Add columns
    $table['columns'] = array();
    if (user_access('tool_view')) {
        $table['columns'][] = array("title"=>'Name');
        $table['columns'][] = array("title"=>'Manufacturer');
        $table['columns'][] = array("title"=>'Model Number');
        $table['columns'][] = array("title"=>'Serial Number');
        $table['columns'][] = array("title"=>'Class');
        $table['columns'][] = array("title"=>'Induction Required');
        $table['columns'][] = array("title"=>'Acquired');
        $table['columns'][] = array("title"=>'Released');
        $table['columns'][] = array("title"=>'Purchased Price');
        $table['columns'][] = array("title"=>'Deprec Schedule');
        $table['columns'][] = array("title"=>'Recovered Cost');
        $table['columns'][] = array("title"=>'Owner');
        $table['columns'][] = array('title'=>'Created By','class'=>'');
        $table['columns'][] = array('title'=>'Created Timestamp','class'=>'');
        $table['columns'][] = array("title"=>'Notes');
        if (user_access('tool_edit')) {
            $table['columns'][] = array("title"=>'Ops');
        }
    }
    // Loop through tool data
    foreach ($tools as $tool) {
        // Add tool data to table
        $row = array();
        // Construct name
        if (user_access('tool_edit')) {
            // Add cells
            $row[] = $tool['tool_name'];
            $row[] = $tool['tool_manufacturer'];
            $row[] = $tool['tool_modelNum'];
            $row[] = $tool['tool_serialNum'];
            $row[] = $tool['tool_class'];
            $row[] = $tool['tool_inductionRequired'] ? 'Yes' : 'No';
            $row[] = $tool['tool_acquiredDate'];
            $row[] = $tool['tool_releasedDate'];
            $row[] = $tool['tool_purchasePrice'];
            $row[] = $tool['tool_deprecSched'];
            $row[] = $tool['tool_recoveredCost'];
            $row[] = theme('contact_name', $tool['tool_owner'], true);
            $row[] = theme('contact_name', $tool['tool_createdBy'], true);
            $row[] = $tool['tool_created'];
            $row[] = $tool['tool_notes'];
        }
        // Construct ops array
        $ops = array();
        // Add edit op
        if (user_access('tool_edit')) {
            $ops[] = '<a href=' . crm_url('tool&tool_id=' . $tool['tool_id'] . '&tab=edit') . '>edit</a>';
        }
        // Add delete op
        if (user_access('tool_delete')) {
            $ops[] = '<a href=' . crm_url('delete&type=tool&amp;id=' . $tool['tool_id']) . '>delete</a>';
        }
        // Add ops row
        if (user_access('tool_edit')) {
            $row[] = join(' ', $ops);
        }
        // Add row to table
        $table['rows'][] = $row;
    }
    // Return table
    return $table;
}

// Forms ///////////////////////////////////////////////////////////////////////

/**
 * @return The form structure for adding a tool.
 */
function tool_add_form () {
    // Ensure user is allowed to edit tools
    if (!user_access('tool_edit')) {
        error_register('User does not have permission: tool_edit');
        return null;
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'tool_add'
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Add tool'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Tool Name'
                        , 'name' => 'tool_name'
                        , 'class' => 'focus'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Manufacturer'
                        , 'name' => 'tool_manufacturer'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Model Number'
                        , 'name' => 'tool_modelNum'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Serial Number'
                        , 'name' => 'tool_serialNum'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Tool Class'
                        , 'name' => 'tool_class'
                    )
                    , array(
                        'type' => 'checkbox'
                        , 'label' => 'Inductiion Required?'
                        , 'name' => 'tool_inductionRequired'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Acquired Date'
                        , 'name' => 'tool_acquiredDate'
                        , 'value' => date("Y-m-d")
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Released Date'
                        , 'name' => 'tool_releasedDate'
                        , 'value' => date("Y-m-d")
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Purchased Price'
                        , 'name' => 'tool_purchasePrice'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Depreciation Schedule'
                        , 'name' => 'tool_deprecSched'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Recovered Cost'
                        , 'name' => 'tool_recoveredCost'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Owner'
                        , 'name' => 'tool_owner'
                        , 'autocomplete' => 'contact_name'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Notes'
                        , 'name' => 'tool_notes'
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
 * Returns the form structure for editing a tool.
 * @param $tool_id The tool_id of the tool to edit.
 * @return The form structure.
 */
function tool_edit_form ($tool_id) {
    // Ensure user is allowed to edit tools
    if (!user_access('tool_edit')) {
        error_register('User does not have permission: tool_edit');
        return null;
    }
    // Get tool data
    $data = crm_get_data('tool', array('tool_id'=>$tool_id));
    $tool = $data[0];
    if (empty($tool) || count($tool) < 1) {
        return array();
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'tool_edit'
        , 'hidden' => array(
            'tool_id' => $tool_id
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Edit Tool'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Name'
                        , 'name' => 'tool_name'
                        , 'value' => $tool['tool_name']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Manufacturer'
                        , 'name' => 'tool_manufacturer'
                        , 'value' => $tool['tool_manufacturer']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Model Number'
                        , 'name' => 'tool_modelNum'
                        , 'value' => $tool['tool_modelNum']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Serial Number'
                        , 'name' => 'tool_serialNum'
                        , 'value' => $tool['tool_serialNum']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Class'
                        , 'name' => 'tool_class'
                        , 'value' => $tool['tool_class']
                    )
                    , array(
                        'type' => 'checkbox'
                        , 'label' => 'Inductiion Required?'
                        , 'name' => 'tool_inductionRequired'
                        , 'value' => $tool['tool_inductionRequired']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Acquired Date'
                        , 'name' => 'tool_acquiredDate'
                        , 'value' => $tool['tool_acquiredDate']
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Released Date'
                        , 'name' => 'tool_releasedDate'
                        , 'value' => $tool['tool_releasedDate']
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Purchased Price'
                        , 'name' => 'tool_purchasePrice'
                        , 'value' => $tool['tool_purchasePrice']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Depreciation Schedule'
                        , 'name' => 'tool_deprecSched'
                        , 'value' => $tool['tool_deprecSched']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Recovered Cost'
                        , 'name' => 'tool_recoveredCost'
                        , 'value' => $tool['tool_recoveredCost']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Owner'
                        , 'name' => 'tool_owner'
                        , 'value' => $tool['tool_owner']
                        , 'autocomplete' => 'contact_name'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Notes'
                        , 'name' => 'tool_notes'
                        , 'value' => $tool['tool_notes']
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Save'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * Return the delete tool form structure.
 * @param $tool_id The tool_id of the tool to delete.
 * @return The form structure.
 */
function tool_delete_form ($tool_id) {
    // Ensure user is allowed to edit tools
    if (!user_access('tool_delete')) {
        error_register('User does not have permission: tool_delete');
        return null;
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'tool_delete'
        , 'hidden' => array(
            'tool_id' => $tool_id
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Delete Tool'
                , 'fields' => array(
                    array(
                        'type' => 'message'
                        , 'value' => '<p>Are you sure you want to delete ' . tool_description($tool_id) . '? This cannot be undone.</p>'
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
 * @return the form structure for a tool import form.
 */
function tool_import_form () {
    return array(
        'type' => 'form'
        , 'method' => 'post'
        , 'enctype' => 'multipart/form-data'
        , 'command' => 'tool_import'
        , 'fields' => array(
            array(
                'type' => 'message'
                , 'value' => '<p>To import tools, upload a csv.  The csv should have a header row with the following fields:</p>
                <ul>
                <li>Tool Name</li>
                <li>Manufacturer</li>
                <li>Model Number</li>
                <li>Serial Number</li>
                <li>Tool Class</li>
                <li>Induction Required? (Set to 1/0 signalling Y/N)</li>
                <li>Acquired Date in YYYY-MM-DD format</li>
                <li>Released Date in YYYY-MM-DD format</li>
                <li>Purchase Price</li>
                <li>Deprec Schedule</li>
                <li>Recovered Cost</li>
                <li>Owner</li>
                <li>Notes</li>
                </ul>'
            )
            , array(
                'type' => 'file'
                , 'label' => 'CSV File'
                , 'name' => 'tool-file'
            )
            , array(
                'type' => 'submit'
                , 'value' => 'Import'
            )
        )
    );
}

// Request handlers ////////////////////////////////////////////////////////////

/**
 * Handle tool add request.
 * @return The url to display on completion.
 */
function command_tool_add() {
    global $esc_post;
    // Verify permissions
    if (!user_access('tool_edit')) {
        error_register('Permission denied: tool_add');
        return crm_url('tool&tool_id=' . $_POST['tool_id']);
    }
    tool_save($_POST);
    return crm_url('tools');
}

/**
 * Handle tool update request.
 * @return The url to display on completion.
 */
function command_tool_edit() {
    global $esc_post;
    // Verify permissions
    if (!user_access('tool_edit')) {
        error_register('Permission denied: tool_edit');
        return crm_url('tool&tool_id=' . $_POST['tool_id']);
    }
    // Save tool
    tool_save($_POST);
    return crm_url('tool&tool_id=' . $_POST['tool_id']);
}

/**
 * Handle delete tool request.
 * @return The url to display on completion.
 */
function command_tool_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('tool_delete')) {
        error_register('Permission denied: tool_delete');
        return crm_url('tools');
    }
    tool_delete($_POST);
    return crm_url('tools');
}

/**
 * Handle tool import request.
 * @return The url to display on completion.
 */
function command_tool_import () {
    if (!user_access('tool_edit')) {
        error_register('User does not have permission: tool_edit');
        return crm_url('tools');
    }
    if (!array_key_exists('tool-file', $_FILES)) {
        error_register('No tool file uploaded');
        return crm_url('tools&tab=import');
    }
    $csv = file_get_contents($_FILES['tool-file']['tmp_name']);
    $data = csv_parse($csv);
    foreach ($data as $row) {
        // Convert row keys to lowercase and remove spaces
        foreach ($row as $tool => $value) {
            $new_tool = str_replace(' ', '', strtolower($tool));
            unset($row[$tool]);
            $row[$new_tool] = $value;
        }
        // Build tool object
        $tool = array(
            'tool_name' => $row['tool_name']
            , 'tool_manufacturer' => $row['tool_manufacturer']
            , 'tool_modelNum' => $row['tool_modelnum']
            , 'tool_serialNum' => $row['tool_serialnum']
            , 'tool_class' => $row['tool_class']
            , 'tool_acquiredDate' => $row['tool_acquireddate']
            , 'tool_releasedDate' => $row['tool_releaseddate']
            , 'tool_purchasePrice' => $row['tool_purchaseprice']
            , 'tool_deprecSched' => $row['tool_deprecsched']
            , 'tool_recoveredCost' => $row['tool_recoveredcost']
            , 'tool_owner' => $row['tool_owner']
            , 'tool_notes' => $row['tool_notes']
            , 'tool_id' => $row['tool_id']
        );
        // Add tool
        tool_save($tool);
    }
    return crm_url('tools');
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function tool_page_list () {
    $pages = array();
    if (user_access('tool_view')) {
        $pages[] = 'tools';
        $pages[] = 'tool';
    }
    return $pages;
}

/**
 * Page hook. Adds tool module content to a page before it is rendered.
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
 */
function tool_page (&$page_data, $page_name, $options) {
    switch ($page_name) {
        case 'tools':
            // Set page title
            page_set_title($page_data, 'Tool Inventory');
            // Add view, add and import tabs
            if (user_access('tool_view')) {
                page_add_content_top($page_data, theme('table', crm_get_table('tool')), 'View');
            }
            if (user_access('tool_edit')) {
                page_add_content_top($page_data, theme('form', crm_get_form('tool_add')), 'Add');
                page_add_content_top($page_data, theme('form', crm_get_form('tool_import')), 'Import');
            }
            break;
        case 'tool':
            // Capture tool id
            $tool_id = $options['tool_id'];
            if (empty($tool_id)) {
                return;
            }
            // Set page title
            page_set_title($page_data, tool_description($tool_id));
            // Add view tab
            $view_content = '';
            if (user_access('tool_view')) {
                $view_content .= '<h3>Tool Info</h3>';
                $opts = array(
                    'tool_id' => $tool_id
                    , 'ops' => false
                );
                $view_content .= theme('table_vertical', crm_get_table('tool_detail', array('tool_id' => $tool_id)));
            }
            if (!empty($view_content)) {
                page_add_content_top($page_data, $view_content, 'View');
            }
            // Add edit tab
            if (user_access('tool_edit')) {
                page_add_content_top($page_data, theme('form', crm_get_form('tool_edit', $tool_id)), 'Edit');
            }
            break;
    }
}

// Themeing ////////////////////////////////////////////////////////////////////

/**
 * Theme a tool name.
 * @param $tool The tool data structure or tool_id.
 * @param $link True if the name should be a link (default: false).
 * @param $path The path that should be linked to.  The tool_id will always be added
 *   as a parameter.
 * @return the name string.
 */
function theme_tool_name ($tool, $link = false, $path = 'tool') {
    if (!is_array($tool)) {
        $tool = crm_get_one('tool', array('tool_id'=>$tool));
    }
    $tool_name = $tool['tool_name'];
    if ($link) {
        $url_opts = array('query' => array('tool_id' => $tool['tool_id']));
        $tool_name = crm_link($tool_name, $path, $url_opts);
    }
    return $tool_name;
}
