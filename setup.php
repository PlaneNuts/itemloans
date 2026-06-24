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
use GlpiPlugin\Itemloans\Loan_Item;
use GlpiPlugin\Itemloans\Return_Item;
use GlpiPlugin\Itemloans\Loans;
use GlpiPlugin\Itemloans\My_Loans;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Itemloans\Profile as ItemLoans_Profile;
use GlpiPlugin\Itemloans\NotificationTargetLoans;

define('PLUGIN_ITEMLOANS_VERSION', '0.9.3');

// Minimal GLPI version, inclusive
define("PLUGIN_ITEMLOANS_MIN_GLPI_VERSION", "10.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_ITEMLOANS_MAX_GLPI_VERSION", "11.0.99");

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_itemloans()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['itemloans'] = true;

    // add menu hook
    $PLUGIN_HOOKS['menu_toadd']['itemloans'] = [
        'plugins' => [
            Loans::class,
            My_Loans::class,
        ],
    ];

    if (
        isset($_SESSION['glpiactiveprofile']['interface'])
        && $_SESSION['glpiactiveprofile']['interface'] == 'helpdesk'
    ) {
        $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY]['itemloans'] = '/plugins/itemloans/front/my_loans.php';
        $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY_ICON]['itemloans'] = My_Loans::getIcon();
    }

    Plugin::registerClass(ItemLoans_Profile::class, [
        'addtabon' => Profile::class
    ]);
    Plugin::registerClass(My_Loans::class);
    Plugin::registerClass(NotificationTargetLoans::class, ['notificationtemplates_types' => true]);
    Plugin::registerClass(Loans::class, ['notificationtemplates_types' => true]);
    Plugin::registerClass(OverdueReminderCronTask::class);
    Plugin::registerClass(NewLoanSummaryCronTask::class);
    Plugin::registerClass(ConfirmationSummaryCronTask::class);

    $PLUGIN_HOOKS['item_add']['itemloans'] = [
        Loans::class => 'plugin_itemloans_item_add',
    ];
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_itemloans()
{
    return [
        'name'           => __('Item Loan', 'itemloans'),
        'version'        => PLUGIN_ITEMLOANS_VERSION,
        'author'         => 'Terrell Eaton',
        'license'        => 'GPLv3',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_ITEMLOANS_MIN_GLPI_VERSION,
                'max' => PLUGIN_ITEMLOANS_MAX_GLPI_VERSION,
            ]
        ]
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_itemloans_check_prerequisites()
{
    return true;
}

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_itemloans_check_config($verbose = false)
{
    if (true) { // Your configuration check
        return true;
    }

    if ($verbose) {
        echo __('Installed / not configured', 'itemloans');
    }
    return false;
}
