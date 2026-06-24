<?php

/**
 * -------------------------------------------------------------------------
 * itemloans plugin for GLPI
 * Copyright (C) 2025 by the itemloans Development Team.
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * --------------------------------------------------------------------------
 */
use GlpiPlugin\Itemloans\Loans;
use GlpiPlugin\Itemloans\Profile as ItemLoans_Profile;
use GlpiPlugin\Itemloans\NotificationTargetLoans;
use GlpiPlugin\Itemloans\ConfirmationSummaryCronTask;
use GlpiPlugin\Itemloans\NewLoanSummaryCronTask;
use GlpiPlugin\Itemloans\OverdueReminderCronTask;

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_itemloans_install()
{
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();

    // instantiate migration with version
    $migration = new Migration(PLUGIN_ITEMLOANS_VERSION);

    // create table only if it does not exist yet!
    $table = Loans::getTable();
    if (!$DB->tableExists($table)) {
        //table creation query
        $query = "CREATE TABLE `$table` ( 
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
                    `entities_id` INT(11) UNSIGNED NOT NULL ,
                    `glpi_item_type` VARCHAR(100) NOT NULL ,
                    `glpi_otherserial` VARCHAR(100) NOT NULL ,
                    `glpi_name` VARCHAR(100) NOT NULL ,
                    `glpi_item_id` INT(11) UNSIGNED NOT NULL ,
                    `date_loaned_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                    `return_by_date` TIMESTAMP NULL DEFAULT NULL ,
                    `send_reminder` BOOLEAN NOT NULL DEFAULT FALSE ,
                    `ask_to_confirm` BOOLEAN NOT NULL DEFAULT FALSE ,
                    `confirmation_sent` BOOLEAN NOT NULL DEFAULT FALSE ,
                    `confirmed_by_user` BOOLEAN NOT NULL DEFAULT FALSE ,
                    `loan_user_id` INT(11) UNSIGNED NOT NULL ,
                    `loan_submitted_by_id` INT(11) UNSIGNED NOT NULL ,
                    `loan_returned_by_id` INT(11) UNSIGNED ,
                    `date_loaned_returned` TIMESTAMP NULL DEFAULT NULL ,
                    `loan_returned` BOOLEAN NOT NULL DEFAULT FALSE ,
                PRIMARY KEY (`id`)) ENGINE = InnoDB
                DEFAULT CHARSET={$default_charset}
                COLLATE={$default_collation}";
        $DB->doQueryOrDie($query, $DB->error());
    }

    //execute the whole migration
    $migration->executeMigration();

    // Add confirmation_sent column if it does not exist
    $table = Loans::getTable();
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'confirmation_sent')) {
        $query_add_col = "ALTER TABLE `$table` ADD `confirmation_sent` BOOLEAN NOT NULL DEFAULT FALSE AFTER `confirmed_by_user`;";
        $DB->doQueryOrDie($query_add_col, $DB->error());
    }

    //add rights
    foreach (ItemLoans_Profile::getAllRights() as $right) {
        ProfileRight::deleteProfileRights([$right['field']]);
        ProfileRight::addProfileRights([$right['field']]);
    }

    $itemtype = 'Item Loan';
    $notification_definitions = [
        'overdue_loan' => [
            'name'      => 'Item Loan Overdue',
            'event'     => 'overdue_loan',
            'subject'   => 'Overdue Loan Reminder: ##loan.item_name##',
            'body'      => '<p>Hello ##loan.user_name##,</p><p>Your loan for the following device has expired. Please return it to the support department at your earliest convienence.</p><ul><li><strong>Item:</strong> ##loan.item_name##</li><li><strong>Inventory Number:</strong> ##loan.item_inventory_number##</li><li><strong>Return by Date:</strong> ##loan.return_by_date##</li></ul><p>Please return the item as soon as possible.</p><p>Thank you.</p>',
        ],
        'item_confirmation_summary' => [
            'name'      => 'Item Loan Confirmation Summary',
            'event'     => 'item_confirmation_summary',
            'subject'   => 'GLPI Loan Confirmation Summary',
            'body'      => '<p>Hello ##loan.user_name##,</p><p>The following devices have been checked out to you and are waiting for your confirmation of reception. Please follow the link below to confirm your items.</p>##loans.confirmation_table##<p>Please click the link below to confirm the loans:</p><p><a href="##loan.confirmation_link##">Confirm Loans</a></p><p>Thank you.</p>',
        ],
        'new_loan_summary' => [
            'name'      => 'Item Loan Confirm New Item',
            'event'     => 'new_loan_summary',
            'subject'   => 'New Items Loaned to You',
            'body'      => '<p>Hello ##loan.user_name##,</p><p>The following items have just been checked out to you:</p>##loans.tableOfDevices##<p>Please confirm your reception of these items from the link below:</p><p><a href="##loan.confirmation_link##">Confirm Loans</a></p><p>Thank you.</p>',
        ],
    ];

    foreach ($notification_definitions as $def) {
        $tpl_id = null;
        $tpl_iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_notificationtemplates',
            'WHERE'  => ['name' => $def['name']],
        ]);
        if (count($tpl_iterator) > 0) {
            $tpl_id = $tpl_iterator->current()['id'];
        } else {
            $DB->insert('glpi_notificationtemplates', [
                'name'     => $def['name'],
                'itemtype' => $itemtype,
                'date_mod' => new \QueryExpression('NOW()'),
            ]);
            $tpl_id = $DB->insertId();

            if ($tpl_id) {
                $DB->insert('glpi_notificationtemplatetranslations', [
                    'notificationtemplates_id' => $tpl_id,
                    'subject'                  => $def['subject'],
                    'content_text'             => '',
                    'content_html'             => $def['body'],
                ]);
            }
        }

        if ($tpl_id) {
            $notif_id = null;
            $notif_iterator = $DB->request([
                'SELECT' => 'id',
                'FROM'   => 'glpi_notifications',
                'WHERE'  => ['name' => $def['name']],
            ]);
            if (count($notif_iterator) > 0) {
                $notif_id = $notif_iterator->current()['id'];
            } else {
                $DB->insert('glpi_notifications', [
                    'name'      => $def['name'],
                    'itemtype'  => $itemtype,
                    'event'     => $def['event'],
                    'is_active' => 1,
                ]);
                $notif_id = $DB->insertId();

                if ($notif_id) {
                    $DB->insert('glpi_notifications_notificationtemplates', [
                        'notifications_id'         => $notif_id,
                        'notificationtemplates_id' => $tpl_id,
                        'mode'                     => 'mailing',
                    ]);

                    $DB->insert('glpi_notificationtargets', [
                        'notifications_id' => $notif_id,
                        'type'             => \Notification::USER_TYPE,
                        'items_id'         => \GlpiPlugin\Itemloans\NotificationTargetLoans::LOAN_USER_RECIPIENT,
                    ]);
                }
            }
        }
    }

    
    CronTask::register(ConfirmationSummaryCronTask::class, 'LoanConfirmSummary', DAY_TIMESTAMP);
    CronTask::register(NewLoanSummaryCronTask::class, 'LoanConfirmNew', DAY_TIMESTAMP);
    CronTask::register(OverdueReminderCronTask::class, 'LoanConfirmOverdue', DAY_TIMESTAMP);
    
    return true;
}


/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_itemloans_uninstall()
{
    global $DB;

    $tables = [
        Loans::getTable(),
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQueryOrDie(
                "DROP TABLE `$table`",
                $DB->error()
            );
        }
    }

    $itemtype = 'GlpiPlugin\Itemloans\Loans';

    $tpl_iterator = $DB->request([
        'SELECT' => 'id',
        'FROM'   => 'glpi_notificationtemplates',
        'WHERE'  => ['itemtype' => $itemtype],
    ]);
    foreach ($tpl_iterator as $row) {
        $tpl_id = $row['id'];
        $DB->delete('glpi_notificationtemplatetranslations', ['notificationtemplates_id' => $tpl_id]);
        $DB->delete('glpi_notifications_notificationtemplates', ['notificationtemplates_id' => $tpl_id]);
    }
    $DB->delete('glpi_notificationtemplates', ['itemtype' => $itemtype]);

    $notif_iterator = $DB->request([
        'SELECT' => 'id',
        'FROM'   => 'glpi_notifications',
        'WHERE'  => ['itemtype' => $itemtype],
    ]);
    foreach ($notif_iterator as $row) {
        $notif_id = $row['id'];
        $DB->delete('glpi_notificationtargets', ['notifications_id' => $notif_id]);
        $DB->delete('glpi_notifications_notificationtemplates', ['notifications_id' => $notif_id]);
    }
    $DB->delete('glpi_notifications', ['itemtype' => $itemtype]);

    //remove rights
    foreach (ItemLoans_Profile::getAllRights() as $right) {
        ProfileRight::deleteProfileRights([$right['field']]);
    }

    CronTask::unregister(ConfirmationSummaryCronTask::class, 'LoanConfirmSummary');
    CronTask::unregister(NewLoanSummaryCronTask::class, 'LoanConfirmNew');
    CronTask::unregister(OverdueReminderCronTask::class, 'LoanConfirmOverdue');

    return true;
}


function plugin_itemloans_addDefaultWhere($itemtype)
{
    if ($itemtype == 'GlpiPlugin\Itemloans\Loans') {
        return getEntitiesRestrictRequest('', \GlpiPlugin\Itemloans\Loans::getTable());
    }
    return '';
}

function plugin_itemloans_item_add($item)
{
    if ($item instanceof Loans && $item->fields['ask_to_confirm']) {
        $notification = new NotificationEvent();
        $notification->raiseEvent('item_confirmation', $item, ['loan_id' => $item->getID()]);
    }
}