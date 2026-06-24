<?php
namespace GlpiPlugin\Itemloans;

use CronTask as GlpiCronTask;
use GlpiPlugin\Itemloans\Loans;
use NotificationEvent;

class OverdueReminderCronTask extends GlpiCronTask
{
    public static function getTypeName($nb = 0)
    {
        return __('Item Loan', 'itemloans');
    }

    static function cronInfo($name) {

      switch ($name) {
         case 'LoanConfirmOverdue':
            return [
               'description' => __('Overdue Loans Reminder', 'itemloans')];   // Optional
            break;
      }
      return [];
   }

    public static function cronLoanConfirmOverdue($task)
    {
        global $DB;
        $cron_status = 1;

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => Loans::getTable(),
            'WHERE'  => [
                'loan_returned'  => 0,
                'return_by_date' => ['<', new \QueryExpression('NOW()')],
                'send_reminder'  => 1,
            ],
        ]);
        $num_overdue = count($iterator);

        if ($num_overdue > 0) {
            $template_name = 'Item Loan Overdue';
            $notification_template = new \NotificationTemplate();
            if (!$notification_template->getFromDBByCrit(['name' => $template_name])) {
                $task->log("Could not find notification template: $template_name");
                return GlpiCronTask::STATUS_ERROR;
            }
            $template_id = $notification_template->getID();

            foreach ($iterator as $data) {
                $loan = new Loans();
                if ($loan->getFromDB($data['id'])) {
                    $check_iterator = $DB->request([
                        'COUNT' => 'count',
                        'FROM'  => 'glpi_queuednotifications',
                        'WHERE' => [
                            'itemtype'                 => Loans::class,
                            'items_id'                 => $loan->getID(),
                            'notificationtemplates_id' => $template_id,
                        ],
                    ]);
                    if ($check_iterator->current()['count'] == 0) {
                        NotificationEvent::raiseEvent('overdue_loan', $loan, ['loan_id' => $loan->getID()]);
                    }
                }
            }
        }

        $task->setVolume($num_overdue);

        return $cron_status;
    }
}
