<?php

use GlpiPlugin\Itemloans\Loan_Item;
use GlpiPlugin\Itemloans\Loans;
use Html;
use Session;
use Entity;

include ('../../../inc/includes.php');

global $DB;

if (!isset($_SESSION['loan_items'])) {
    $_SESSION['loan_items'] = [];
}

Session::checkRight(Loans::$rightname, Loans::CREATE_LOAN);

$loan = new Loan_Item();


if (isset($_POST["search_item"]) || isset($_SESSION['multiple_results'])) { 
    // Handle the search form
    if (isset($_POST['search_item']) && !empty($_POST['item_identifier'])) {
        $identifier = trim($_POST['item_identifier']);
        $found = Loans::searchForItem($identifier);

        if ($found === false) {
            Session::addMessageAfterRedirect(__('Item not found for identifier: %s', 'itemloans'), $identifier, true, ERROR);
        } else if (is_array($found) && isset($found[0]) && is_array($found[0])) { // Multiple items found
            $_SESSION['multiple_results'] = $found;
            $_SESSION['multiple_results_identifier'] = $identifier;
        } else { // Single item found
            $entity = new Entity();
            $allowed_entities = [];
            if ($entity->getFromDB($_SESSION['glpiactive_entity'])) {
                $parent_completename = $entity->fields['completename'];
                $iterator = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_entities',
                    'WHERE'  => ['completename' => ['LIKE', $parent_completename . '%']],
                ]);
                if ($iterator) {
                    foreach($iterator as $row) {
                        $allowed_entities[] = $row['id'];
                    }
                }
            } else {
                $allowed_entities = [$_SESSION['glpiactive_entity']];
            }

            if (isset($found['entities_id']) && !in_array($found['entities_id'], $allowed_entities)) {
                Session::addMessageAfterRedirect(__('Item not in Active or Child Entity', 'itemloans'), true, ERROR);
            } else {
                $loan_table = Loans::getTable();
                $result = $DB->request(
                    $loan_table,
                    [
                        'WHERE' => [
                            'glpi_item_type' => $found['item_type'],
                            'glpi_item_id'   => $found['id'],
                            'loan_returned'  => 0
                        ],
                        'LIMIT' => 1
                    ]
                );

                if ($result) {
                    if (count($result) > 0) {
                        $active_loan = $result->current();
                        $user = new \User();
                        if ($user->getFromDB($active_loan['loan_user_id'])) {
                            $active_loan['loan_user_name'] = $user->getFriendlyName();
                        } else {
                            $active_loan['loan_user_name'] = __('User not found', 'itemloans');
                        }

                        $_SESSION['active_loan_confirmation'] = [
                            'item' => $found,
                            'loan' => $active_loan,
                            'matched_identifier' => $identifier,
                        ];
                    } else {
                        $is_in_cart = false;
                        foreach ($_SESSION['loan_items'] as $cart_item) {
                            if (isset($cart_item['id']) && isset($found['id']) && $cart_item['id'] == $found['id'] && $cart_item['item_type'] == $found['item_type']) {
                                $is_in_cart = true;
                                break;
                            }
                        }
                        if (!$is_in_cart) {
                            $found['matched_identifier'] = $identifier;
                            $_SESSION['loan_items'][] = $found;
                        } else {
                            Session::addMessageAfterRedirect(__('Item already in cart'), true, INFO);
                        }
                    }
                }
            }
        }
        Html::back();
    }

    if (isset($_POST['select_item_from_modal']) && isset($_POST['selected_item_key'])) {
        $selected_key = $_POST['selected_item_key'];
        if (isset($_SESSION['multiple_results'][$selected_key])) {
            $selected_item = $_SESSION['multiple_results'][$selected_key];
            $entity = new Entity();
            $allowed_entities = [];
            if ($entity->getFromDB($_SESSION['glpiactive_entity'])) {
                $parent_completename = $entity->fields['completename'];
                $iterator = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_entities',
                    'WHERE'  => ['completename' => ['LIKE', $parent_completename . '%']],
                ]);
                if ($iterator) {
                    foreach($iterator as $row) {
                        $allowed_entities[] = $row['id'];
                    }
                }
            } else {
                $allowed_entities = [$_SESSION['glpiactive_entity']];
            }

            if (isset($selected_item['entities_id']) && !in_array($selected_item['entities_id'], $allowed_entities)) {
                Session::addMessageAfterRedirect(__('Item not in Active or Child Entity', 'itemloans'), true, ERROR);
            } else {
                $is_in_cart = false;
                foreach ($_SESSION['loan_items'] as $cart_item) {
                    if ($cart_item['id'] == $selected_item['id'] && $cart_item['item_type'] == $selected_item['item_type']) {
                        $is_in_cart = true;
                        break;
                    }
                }
                if (!$is_in_cart) {
                    $selected_item['matched_identifier'] = $_SESSION['multiple_results_identifier'] ?? '';
                    $_SESSION['loan_items'][] = $selected_item;
                } else {
                    Session::addMessageAfterRedirect(__('Item already in cart'), true, INFO);
                }
            }
        }
        unset($_SESSION['multiple_results']);
        unset($_SESSION['multiple_results_identifier']);
        Html::back();
    }

    if (isset($_POST['cancel_modal'])) {
        unset($_SESSION['multiple_results']);
        unset($_SESSION['multiple_results_identifier']);
        Html::back();
    }
    
} else if (isset($_POST['confirm_return_and_loan'])) {
    if (isset($_SESSION['active_loan_confirmation'])) {
        $confirmation_data = $_SESSION['active_loan_confirmation'];
        $loan_to_return_id = $confirmation_data['loan']['id'];
        $item_to_add = $confirmation_data['item'];

        // Return the old loan
        $loan = new Loans();
        $input = [
            'id' => $loan_to_return_id,
            'loan_returned' => 1,
            'loan_returned_by_id' => Session::getLoginUserID(),
            'date_returned' => date('Y-m-d H:i:s'),
        ];
        $loan->update($input);

        // Add the item to the new loan cart
        $item_to_add['matched_identifier'] = $confirmation_data['matched_identifier'];
        $_SESSION['loan_items'][] = $item_to_add;

        unset($_SESSION['active_loan_confirmation']);
        Session::addMessageAfterRedirect(__('Previous loan returned and item added to cart.', 'itemloans'), true, INFO);
    }
    Html::back();
} else if (isset($_POST['cancel_return_and_loan'])) {
    if (isset($_SESSION['active_loan_confirmation'])) {
        unset($_SESSION['active_loan_confirmation']);
    }
    Html::back();
} else if (isset($_POST["loan_items"])) {
    if (isset($_SESSION['loan_items']) && isset($_POST['user_id'])) {
        $loan_items = $_SESSION['loan_items'];
        $user_id = $_POST['user_id'];
        $return_by_date = $_POST['return_by_date'] ?? null;
        $loan_submitted_by_id = Session::getLoginUserID();
        $ask_to_confirm = isset($_POST['ask_to_confirm']);
        $send_reminder = isset($_POST['send_reminder']);

        $locations_id = $_POST['locations_id'] ?? null;
        $states_id = $_POST['states_id'] ?? null;

        foreach ($loan_items as $item) {
            $loan = new Loans();
            $input = [
                'glpi_item_type' => $item['item_type'],
                'glpi_item_id' => $item['id'],
                'glpi_name' => $item['name'],
                'glpi_otherserial' => $item['otherserial'] ?? null,
                'loan_user_id' => $user_id,
                'loan_submitted_by_id' => $loan_submitted_by_id,
                'entities_id' => $item['entities_id'],
                'ask_to_confirm' => $ask_to_confirm,
                'send_reminder' => $send_reminder,
            ];
            if (!empty($return_by_date)) {
                $input['return_by_date'] = $return_by_date;
            }
            $loan->add($input);

            $item_to_update = getItemForItemtype($item['item_type']);
            if ($item_to_update && $item_to_update->getFromDB($item['id'])) {
                $update_input = ['id' => $item['id']];
                if (!empty($user_id)) {
                    $update_input['users_id'] = $user_id;
                }
                if (!empty($locations_id)) {
                    $update_input['locations_id'] = $locations_id;
                }
                if (!empty($states_id)) {
                    $update_input['states_id'] = $states_id;
                }

                if (count($update_input) > 1) {
                    $item_to_update->update($update_input);
                }
            }
        }
        unset($_SESSION['loan_items']);
        Session::addMessageAfterRedirect
(
            __('Items have been successfully loaned.', 'itemloans'),
            true,
            INFO
        );
        Html::back();
    } else {
        Session::addMessageAfterRedirect
(
            __('No items or user selected for loan.', 'itemloans'),
            true,
            ERROR
        );
        Html::back();
    }
} else if (isset($_POST["remove_item_from_cart"])) {
    $key_to_remove = $_POST['remove_item_from_cart'];
    if (isset($_SESSION['loan_items'][$key_to_remove])) {
        unset($_SESSION['loan_items'][$key_to_remove]);
        $_SESSION['loan_items'] = array_values($_SESSION['loan_items']); // Re-index array
    }
    Html::back();
}
