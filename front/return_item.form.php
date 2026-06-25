<?php

use GlpiPlugin\Itemloans\Loans;
use Html;
use Session;
use Dropdown;
use Entity;

include ('../../../inc/includes.php');

global $DB;

Session::checkRight(Loans::$rightname, Loans::RETURN_LOAN);

if (isset($_POST["search_item"])) { // Handle the search form
    if (isset($_POST['item_identifier']) && !empty($_POST['item_identifier'])) {
        $identifier = $_POST['item_identifier'];
        $items = Loans::searchForItem($identifier);

        if ($items) {
            $active_loan_found = false;
            // Ensure $items is an array of items for consistent processing
            if (isset($items['id'])) {
                $items = [$items]; // It was a single item, wrap it in an array
            }

            $loan_table = Loans::getTable();
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

            $filtered_items = [];
            foreach ($items as $item) {
                if (isset($item['entities_id']) && in_array($item['entities_id'], $allowed_entities)) {
                    $filtered_items[] = $item;
                }
            }

            if (empty($filtered_items)) {
                Session::addMessageAfterRedirect(__('Item not in Active or Child Entity', 'itemloans'), true, ERROR);
                Html::back();
            }

            foreach ($filtered_items as $item) {
                if (empty($item['item_type']) || empty($item['id'])) {
                    continue;
                }

                $result = $DB->request(
                    $loan_table,
                    [
                        'WHERE' => [
                            'glpi_item_type' => $item['item_type'],
                            'glpi_item_id'   => $item['id'],
                            'loan_returned'  => 0
                        ],
                        'LIMIT' => 1
                    ]
                );

                if ($result && count($result) > 0) {
                    // Active loan found, add it to the session cart
                    $loan_details = $result->current();

                    // Add item details to the loan details for display
                    $loan_details['item_name'] = $item['name'];
                    $loan_details['item_inventory_number'] = $item['otherserial'];
                    $loan_details['matched_identifier'] = $identifier;
                    $user = new \User();
                    if ($user->getFromDB($loan_details['loan_user_id'])) {
                        $loan_details['loan_user_name'] = $user->getFriendlyName();
                    } else {
                        $loan_details['loan_user_name'] = __('User not found', 'itemloans');
                    }

                    if (!isset($_SESSION['return_items'])) {
                        $_SESSION['return_items'] = [];
                    }
                    $_SESSION['return_items'][$loan_details['id']] = $loan_details;
                    $active_loan_found = true;
                    break; // Found the active loan, no need to check other items
                }
            }

            if (!$active_loan_found) {
                Session::addMessageAfterRedirect(
                    __('Item is currently not loaned out.', 'itemloans'),
                    true,
                    ERROR
                );
            }
        } else {
            // Physical item not found
            Session::addMessageAfterRedirect(
                __('Item not found', 'itemloans'),
                true,
                ERROR
            );
        }
    }
    Html::back();
} else if (isset($_POST["return_items"])) { // Handle the final return submission
    $loan_ids_to_return = $_POST['loan_ids_to_return'] ?? [];

    if (!empty($loan_ids_to_return)) {
        $locations_id = $_POST['locations_id'] ?? null;
        $states_id = $_POST['states_id'] ?? null;
        $returned_count = 0;

        foreach ($loan_ids_to_return as $loan_id_to_return) {
            if (empty($loan_id_to_return)) {
                continue;
            }

            $loan = new Loans();
            $input = [
                'id' => $loan_id_to_return,
                'loan_returned' => 1,
                'loan_returned_by_id' => Session::getLoginUserID(),
                'date_loaned_returned' => date('Y-m-d H:i:s'),
            ];
            if ($loan->update($input)) {
                $returned_count++;

                $returned_item_details = $_SESSION['return_items'][$loan_id_to_return] ?? null;

                if ($returned_item_details) {
                    $item_to_update = getItemForItemtype($returned_item_details['glpi_item_type']);
                    if ($item_to_update && $item_to_update->getFromDB($returned_item_details['glpi_item_id'])) {
                        $update_input = [
                            'id' => $returned_item_details['glpi_item_id'],
                            'users_id' => 0
                        ];
                        
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

                // Remove from cart
                if (isset($_SESSION['return_items'][$loan_id_to_return])) {
                    unset($_SESSION['return_items'][$loan_id_to_return]);
                }
            }
        }

        if ($returned_count > 0) {
            Session::addMessageAfterRedirect(
                sprintf(__('%d item(s) have been successfully returned.', 'itemloans'), $returned_count),
                true,
                INFO
            );
        } else {
            Session::addMessageAfterRedirect(
                __('No items were returned.', 'itemloans'),
                true,
                WARNING
            );
        }
    } else {
        Session::addMessageAfterRedirect(
            __('There are no items in the cart to return.', 'itemloans'),
            true,
            WARNING
        );
    }
    Html::back();
} else if (isset($_POST["remove_item_from_cart"])) {
    $item_id_to_remove = $_POST['remove_item_from_cart'];
    if (isset($_SESSION['return_items'][$item_id_to_remove])) {
        unset($_SESSION['return_items'][$item_id_to_remove]);
    }
    Html::back();
}
