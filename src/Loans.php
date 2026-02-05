<?php
namespace GlpiPlugin\Itemloans;

use GlpiPlugin\Itemloans\Search_Loans;
use GlpiPlugin\Itemloans\Return_Item;
use GlpiPlugin\Itemloans\Loan_Item;
use CommonDBTM;
use Session;
use Search;
use Glpi\Application\View\TemplateRenderer;
use Html;

class Loans extends CommonDBTM
{
    static $rightname = 'plugin_itemloans';

    const CREATE_LOAN = 128;
    const UPDATE_LOAN = 256;
    const VIEW_LOANS  = 512;
    const RETURN_LOAN = 1024;

    public function getRights($interface = 'central') {
        $rights = [
            self::CREATE_LOAN => __('Create Loan', 'plugin_itemloans'),
            self::UPDATE_LOAN => __('Update Loan', 'plugin_itemloans'),
            self::VIEW_LOANS  => __('View Loans', 'plugin_itemloans'),
            self::RETURN_LOAN => __('Return Loan', 'plugin_itemloans'),
        ];
        return $rights;
    }
    
    /**
     *  Name of the itemtype
     */
    static function getTypeName($nb=0)
    {
        return _n('Item Loan', 'Item Loan', $nb);
    }

    static function getEntityIdField()
    {
        return 'entities_id';
    }

     static function getMenuName($nb = 0)
    {
        // call class label
        return self::getTypeName($nb);
    }

    static function getIcon() {
      return "fas fa-arrow-right-arrow-left";
    }

    public function getUsersToNotify()
    {
        $users = [];
        if (isset($this->fields['loan_user_id'])) {
            $users[] = ['type' => 'user', 'users_id' => $this->fields['loan_user_id']];
        }
        return $users;
    }

    static function getMenuContent()
    {
        $menu = [];
        if (Session::haveRight(self::$rightname, self::VIEW_LOANS)) {
            $menu['title'] = self::getTypeName(2);
            // The page property sets the default page when clicking the menu title
            $menu['page'] = Loans::getSearchURL(false);

            // The 'search' link will show the list of items.
            $menu['links']['search'] = self::getSearchURL(false);
            $menu['links']['lists']  = '';
            $menu['lists_itemtype'] = str_replace('\\', '_', self::class);
        }
        $menu['icon'] = self::getIcon();

        if (Session::haveRight(self::$rightname, self::CREATE_LOAN)) {
            if (empty($menu['title'])) {
                $menu['title'] = 'Loan Items';
                // If only create right, default page is the form
                $menu['page'] = '/plugins/itemloans/front/loan_item.php';
            }
            // The 'add' link for creating a new loan.
            $loan_text = "<i class='fas fa-sign-out-alt'></i> " . __('Loan Item', 'itemloans');
            $menu['links'][$loan_text] = '/plugins/itemloans/front/loan_item.php';
        }

         if (Session::haveRight(self::$rightname, self::RETURN_LOAN)) {
            if (empty($menu['title'])) {
                $menu['title'] = 'Return Items';
                // If only create right, default page is the form
                $menu['page'] = '/plugins/itemloans/front/return_item.php';
            }
            // The 'add' link for creating a new loan.
            $return_text = "<i class='fas fa-sign-in-alt'></i> " . __('Return Item', 'itemloans');
            $menu['links'][$return_text] = '/plugins/itemloans/front/return_item.php';
        }

        if (empty($menu)) {
            return null;
        }

        return $menu;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        // This function seems to be unused in search, the logic has been moved to displayItem
        // and is triggered by display_callback in rawSearchOptions.
        // Leaving the function here in case it's used elsewhere.
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        if ($field === 'glpi_name' || $field === 'glpi_item_id') {
            return self::displayItem($values);
        }

        // Fallback to parent
        if (is_callable('parent::getSpecificValueToDisplay')) {
            return parent::getSpecificValueToDisplay($field, $values, $options);
        }
        return $values[$field] ?? '';
    }

    public static function displayItem($values)
    {
        if (
            isset($values['glpi_item_type']) && !empty($values['glpi_item_type'])
            && isset($values['glpi_item_id']) && $values['glpi_item_id'] > 0
        ) {
            $itemtype = $values['glpi_item_type'];
            $id       = $values['glpi_item_id'];
            $name     = '';
            $item     = getItemForItemtype($itemtype);
            if ($item && $item->getFromDB($id)) {
                $name = $item->getName();
            }
            if (empty($name)) {
                // Failsafe if item has been deleted
                return $id;
            }
            $link = \CommonGLPI::getLinkForItem($itemtype, $id);
            return "<a href='$link'>$name</a>";
        }
        return ''; // No item
    }

    function rawSearchOptions()
    {
        $options = [];

        $options[] = [
            'id'   => 'common',
            'name' => __('Loan Details'),
        ];

        $options[] = [
            'id'       => 401,
            'table'    => self::getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
            'datatype' => 'numeric',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 402,
            'table'    => self::getTable(),
            'field'    => 'glpi_item_type',
            'name'     => __('Item Type'),
            'datatype' => 'itemtype',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'                => 403,
            'table'             => self::getTable(),
            'field'             => 'glpi_name',
            'name'              => __('Item'),
            'datatype'          => 'specific',
            'itemtype'          => self::class,
        ];

        $options[] = [
            'id'       => 404,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'linkfield'=> 'loan_user_id',
            'name'     => __('Loaned to User'),
            'datatype' => 'dropdown',
            'itemtype' => 'User',
        ];

        $options[] = [
            'id'       => 408,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'linkfield'=> 'loan_submitted_by_id',
            'name'     => __('Loan Submitted by'),
            'datatype' => 'dropdown',
            'itemtype' => 'User',
        ];

        $options[] = [
            'id'   => 'status',
            'name' => __('Loan Status'),
        ];

        $options[] = [
            'id'       => 405,
            'table'    => self::getTable(),
            'field'    => 'date_loaned_created',
            'name'     => __('Loan Date'),
            'datatype' => 'datetime',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 416,
            'table'    => self::getTable(),
            'field'    => 'date_loaned_returned',
            'name'     => __('Returned Date'),
            'datatype' => 'datetime',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 406,
            'table'    => self::getTable(),
            'field'    => 'return_by_date',
            'name'     => __('Loan Expiration Date'),
            'datatype' => 'datetime',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 407,
            'table'    => self::getTable(),
            'field'    => 'confirmed_by_user',
            'name'     => __('Confirmed by User'),
            'datatype' => 'bool',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 409,
            'table'    => self::getTable(),
            'field'    => 'loan_returned',
            'name'     => __('Returned'),
            'datatype' => 'bool',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 80,
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'linkfield'=> 'entities_id',
            'name'     => __('Entity'),
            'datatype' => 'dropdown',
            'itemtype' => 'Entity',
        ];

        $options[] = [
            'id'       => 411,
            'table'    => self::getTable(),
            'field'    => 'send_reminder',
            'name'     => __('Reminder'),
            'datatype' => 'bool',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 412,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'linkfield'=> 'loan_returned_by_id',
            'name'     => __('Loan Returned by'),
            'datatype' => 'dropdown',
            'itemtype' => 'User',
        ];

        $options[] = [
            'id'       => 414,
            'table'    => self::getTable(),
            'field'    => 'glpi_otherserial',
            'name'     => __('Inventory Number'),
            'datatype' => 'text',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 415,
            'table'    => self::getTable(),
            'field'    => 'ask_to_confirm',
            'name'     => __('Ask to Confirm'),
            'datatype' => 'bool',
            'itemtype' => self::class,
        ];

        $options[] = [
            'id'       => 410,
            'table'    => self::getTable(),
            'field'    => 'glpi_item_id',
            'name'     => __('Item ID'),
            'datatype' => 'numeric',
            'itemtype' => self::class,
        ];

        return $options;
    }

    static function searchForItem($identifier)
    {
        global $DB;
        $found_items = [];

        // A list of asset types that can be loaned.
        $loanable_item_types = [
            'Computer', 'Monitor', 'Phone', 'NetworkEquipment', 'Peripheral', 'Software', 'Printer'
        ];

        if (class_exists('PluginGenericobjectType')) {
            $generic_object_types = array_keys(\PluginGenericobjectType::getTypes());
            $loanable_item_types = array_merge($loanable_item_types, $generic_object_types);
        }

        foreach ($loanable_item_types as $item_type) {
            if (!class_exists($item_type)) {
                continue;
            }
            $item = new $item_type();
            $table = $item->getTable();

            $search_options = Search::getOptions($item_type);
            $fields_to_search = [];
            $fields_to_search_names = ['serial', 'otherserial'];

            foreach ($search_options as $id => $options) {
                if (isset($options['field']) && in_array($options['field'], $fields_to_search_names) && isset($options['table']) && $options['table'] == $table) {
                    $fields_to_search[] = $options['field'];
                }
            }

            if (empty($fields_to_search)) {
                continue;
            }

            $or_criteria = [];
            foreach ($fields_to_search as $field) {
                $or_criteria[] = [$field => $identifier];
            }

            $iterator = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => $table,
                'WHERE'  => [
                    'OR' => $or_criteria,
                ],
            ]);

            if ($iterator) {
                foreach ($iterator as $data) {
                    $item_id = $data['id'];
                    if ($item->getFromDB($item_id)) {
                        $result_item = $item->fields;
                        $result_item['item_type'] = $item_type;
                        $found_items[$item_type . '_' . $item_id] = $result_item;
                    }
                }
            }
        }

        // Fallback search on the 'fields' plugin table
        foreach ($loanable_item_types as $item_type) {
            $fields_table_name = 'glpi_plugin_fields_' . strtolower($item_type) . 'legacyinventoryids';
            if ($DB->tableExists($fields_table_name)) {
                $iterator = $DB->request([
                    'SELECT' => ['items_id'],
                    'FROM'   => $fields_table_name,
                    'WHERE'  => ['legacyidfield' => $identifier],
                ]);

                if ($iterator) {
                    foreach ($iterator as $data) {
                        $item_id = $data['items_id'];
                        if (!empty($item_id)) {
                            $item = getItemForItemtype($item_type);
                            if ($item && $item->getFromDB($item_id)) {
                                $result_item = $item->fields;
                                $result_item['item_type'] = $item_type;
                                $found_items[$item_type . '_' . $item_id] = $result_item;
                            }
                        }
                    }
                }
            }
        }

        // Fallback search on immo_number
        $iterator = $DB->request([
            'SELECT' => ['items_id', 'itemtype'],
            'FROM'   => 'glpi_infocoms',
            'WHERE'  => ['immo_number' => $identifier],
        ]);

        if ($iterator) {
            foreach ($iterator as $data) {
                $item_id = $data['items_id'];
                $item_type = $data['itemtype'];

                if (!empty($item_type) && !empty($item_id)) {
                    $item = getItemForItemtype($item_type);
                    if ($item && $item->getFromDB($item_id)) {
                        $result_item = $item->fields;
                        $result_item['item_type'] = $item_type;
                        $found_items[$item_type . '_' . $item_id] = $result_item;
                    }
                }
            }
        }

        $found_items = array_values($found_items);

        if (count($found_items) === 0) {
            return false;
        }

        if (count($found_items) === 1) {
            return $found_items[0];
        }

        return $found_items;
    }
}
