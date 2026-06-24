<?php
namespace GlpiPlugin\Itemloans;

use CronTask as GlpiCronTask;
use GlpiPlugin\Itemloans\Loans;
use NotificationEvent;

class ConfirmationSummaryCronTask extends GlpiCronTask
{
    public static function getTypeName($nb = 0)
    {
        return __('Item Loan', 'itemloans');
    }

    static function cronInfo($name) {

      switch ($name) {
         case 'LoanConfirmSummary':
            return [
               'description' => __('Pending Item Loan Confirmation Summary', 'itemloans')];   // Optional
            break;
      }
      return [];
   }

    public static function cronLoanConfirmSummary($task)
    {
        global $DB;
        $cron_status = 1;

        // Get all users with items pending confirmation
        $iterator = $DB->request([
            'SELECT'   => 'loan_user_id',
            'DISTINCT' => true,
            'FROM'     => Loans::getTable(),
            'WHERE'    => [
                'loan_returned'     => 0,
                'ask_to_confirm'    => 1,
                'confirmed_by_user' => 0,
            ],
        ]);

        foreach ($iterator as $data) {
            $user_id = $data['loan_user_id'];

            // Get all pending loans for this user
            $loans = new Loans();
            $pending_loans = $loans->find([
                'loan_user_id'      => $user_id,
                'loan_returned'     => 0,
                'ask_to_confirm'    => 1,
                'confirmed_by_user' => 0,
            ]);

            if (count($pending_loans) > 0) {
                $loan_ids = array_keys($pending_loans);
                $last_loan_id = end($loan_ids);
                $last_loan = new Loans();
                if ($last_loan->getFromDB($last_loan_id)) {
                    NotificationEvent::raiseEvent('item_confirmation_summary', $last_loan, ['loan_ids' => $loan_ids]);
                }
            }
        }

        $task->setVolume(count($iterator));

        return $cron_status;
    }
}
