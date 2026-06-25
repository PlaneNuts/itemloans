<?php

use GlpiPlugin\Itemloans\Profile as ItemloansProfile;
use GlpiPlugin\Itemloans\Loans;

include ('../../../inc/includes.php');

global $CFG_GLPI;

Session::checkLoginAndRedirect();

if (!Session::haveRight('profile', UPDATE)) {
    Html::displayNotFoundError();
}

// Use core GLPI Profile class for update
$profile = new \Profile();

if (isset($_POST['update'])) {
    $profile_id = $_POST['id'];

    // Get the right group field name from the plugin's Profile class
    $all_rights_definitions = ItemloansProfile::getAllRights();
    $loan_rights_field = '';
    foreach ($all_rights_definitions as $right_def) {
        if ($right_def['itemtype'] === Loans::class) {
            $loan_rights_field = $right_def['field'];
            break;
        }
    }

    if (empty($loan_rights_field)) {
        Html::displayErrorAndRedirect(__('Error: Loan rights field not found.', 'itemloans'), true);
    }

    $new_rights_value = 0;
    if (isset($_POST['plugin_itemloans_rights']) && is_array($_POST['plugin_itemloans_rights'])) {
        foreach ($_POST['plugin_itemloans_rights'] as $right_value) {
            $new_rights_value |= (int)$right_value;
        }
    } else {
        // No rights checked, ensure the value is 0
        $new_rights_value = 0;
    }

    $input = [
        'id' => $profile_id,
        $loan_rights_field => $new_rights_value,
    ];

    // Update the core profile
    if ($profile->update($input)) {
        Html::redirect("{$CFG_GLPI['root_doc']}/front/profile.form.php?id={$profile_id}");
    } else {
        Html::displayErrorAndRedirect(__('Error updating profile rights.', 'itemloans'), true);
    }
} else {
    Html::displayNotFoundError();
}
