<?php
include ('../../../inc/includes.php');

use GlpiPlugin\Itemloans\Loans;
use Html;
use Session;

global $CFG_GLPI;

// Authenticated users only
Session::checkLoginUser();

if (isset($_POST['confirm_selected']) && isset($_POST['loan_ids'])) {
    $loan_ids = $_POST['loan_ids'];
    $user_id = Session::getLoginUserID();
    $success_count = 0;
    $error_count = 0;

    foreach ($loan_ids as $loan_id) {
        $loan = new Loans();
        if ($loan->getFromDB($loan_id) && $loan->fields['loan_user_id'] == $user_id) {
            $input = [
                'id' => $loan_id,
                'confirmed_by_user' => 1
            ];
            if ($loan->update($input)) {
                $success_count++;
            } else {
                $error_count++;
            }
        } else {
            $error_count++;
        }
    }

    if ($success_count > 0) {
        Session::addMessageAfterRedirect(sprintf(__('%d item(s) confirmed.'), $success_count), false, INFO);
    }
    if ($error_count > 0) {
        Session::addMessageAfterRedirect(sprintf(__('%d item(s) could not be confirmed.'), $error_count), false, ERROR);
    }
}

Html::redirect("{$CFG_GLPI['root_doc']}/plugins/itemloans/front/my_loans.php");