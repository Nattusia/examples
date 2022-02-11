<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\calendar_hero_integration\Helper;
use Drupal\envision_crm\ReadExcel;


/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class CoachReportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_coach_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export data'),
    ];
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array&$form, FormStateInterface $form_state) {
    $records = Helper::getCoachesData();
    $batch = [
      'title' => $this->t('Exporting...'),
      'operations' => [],
      'finished' =>
        '\Drupal\calendar_hero_integration\Form\CoachReportForm::batchFinished',
      'init_message' => $this->t('Export start'),
      'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => $this->t('Error occurred. Failed to setup the account.'),
    ];

    $filename = "EGL_coaches_report_" . date('Y_m_d_H_i');
    foreach ($records as $record) {
      $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CoachReportForm', 'processRecord'], [$record, $filename]];
    }
    batch_set($batch);
  }

  public static function processRecord($record, $filename, &$context) {

    $headers = [
      'uid' => 'Coach id',
      'name' => 'Coach Name',
      'capacity' => 'Capacity',
      'availability' => 'Availability',
      'activation' => 'Activation',
      'velocity' => 'Velocity',
     ];

    ReadExcel::writeToExcel($filename, $record, $headers);
    if (!isset($context['results']['count'])) {
      $context['results']['count'] = 0;
    }
    $context['results']['count']++;
    $context['results']['filename'] = $filename;
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      if (!empty($results['count'])) {
        \Drupal::messenger()->addStatus($results['count'] . ' records have been added to the file.');
        $dir_path = '/sites/default/files/reports';
        $check_dir_path = 'public://reports/';

        $file_path = $dir_path . '/' . $results['filename'] . '.xlsx';
        if (file_exists($check_dir_path . $results['filename'] . '.xlsx')) {
    //$xls_path = '/' . drupal_get_path('module', 'envision_crm') . '/files/upload-users.xlsx';
          $url = Url::fromUserInput($file_path);
          $link = Link::fromTextAndUrl('Download', $url)->toString();
          $message['#markup'] = 'The report is ready. ' . $link;

          \Drupal::messenger()->addStatus($message);

        }
      }
    }
  }
}
