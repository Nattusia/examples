<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\calendar_hero_integration\Common;
use Drupal\calendar_hero_integration\Helper;
use Drupal\opigno_calendar_event\CalendarEventInterface;
use Drupal\envision_crm\DataStorage;
use Drupal\envision_crm\ReadExcel;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class PersonalReportExportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ch_personal_report_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $coach = 0, $user = 0) {

    $request = \Drupal::request();
    $query = $request->query->all();
    $values = [
      'from' => '',
      'to' => '',
    ];

    $form['coach'] = [
      '#type' => 'hidden',
      '#value' => $coach,
    ];

    $form['uid'] = [
      '#type' => 'hidden',
      '#value' => $user
    ];

    foreach ($values as $ind => &$value) {
      $value = empty($form_state->getValue($ind)) ? $value : $form_state->getValue($ind);
      $value = empty($query[$ind]) ? $value : $query[$ind];
    }

    // $form['period'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Period'),
    // ];
    // $form['period']['from'] = [
    //   '#type' => 'date',
    //   '#title' => $this->t('From'),
    //   '#default_value' => $values['from'],
    // ];

    // $form['period']['to'] = [
    //   '#type' => 'date',
    //   '#title' => $this->t('To'),
    //   '#default_value' => $values['to'],
    // ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export Data'),
      '#op' => 'export'
    ];

    return $form;
  }

public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $form_state->cleanValues();
    $values = $form_state->getValues();

    if ($trigger['#op'] == 'export') {
      $batch = [
        'title' => $this->t('Exporting...'),
        'operations' => [],
        'finished' =>
          '\Drupal\calendar_hero_integration\Form\PersonalReportExportForm::batchFinished',
        'init_message' => $this->t('Export start'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to setup the account.'),
      ];
      $params = [
        'roles' => ['egl_consultant'],
        'field_calendar_hero_token' => 'IS NOT NULL'
      ];

      $records = $this->exportData($values);
      $filename = "Personal_report_coach" . $values['coach'] . '_client' . $values['uid'] . '_'  . date('Y_m_d_H_i');
      foreach ($records as $record) {
        $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\PersonalReportExportForm', 'processRecord'], [$record, $filename, [], $values]];
      }

      batch_set($batch);
    }
    else {

      $options['query'] = $values;
      $params = [];
      $rname = \Drupal::routeMatch()->getRouteName();
      $redirect_url = Url::fromRoute($rname, $params, $options);
      $form_state->setRedirect($rname, $params, $options);
    }
  }

  public function exportData($values) {
    return Helper::getUserMeetings($values);
  }


  public static function processRecord($record, $filename, $indexes = [], $values = [], &$context) {

    if (empty($indexes)) {
      $indexes = [
         'meeting_id' => "Meeting ID",
         'client_name' => 'Client Name',
         'client_email' => 'Client Email',
         'date' => 'Session Date',
         'time' => 'Session Time',
         'description' => 'Meeting title',
         'duration' => "Session Hours",
         'remained' => "Hours Remaining",
      ];
    }

    $data = [];

    foreach ($indexes as $index => $header) {

      //$data[$index] = '"' . $record->{$index} . '"';
      $data[$index] = isset($record->$index) ? $record->$index : '';
      $convert = [
        'date',
        'time'
      ];
      if (in_array($index, $convert)) {
          $data[$index] = Helper::convertDateRow($record, $index, $index);
      }
      if ($index == 'duration') {
        $data[$index] = $record->duration/60;
      }
      if ($index == 'remained') {
        if (!empty($record->date_daterange_value)) {
          $daterange = $record->date_daterange_value;
        }
        else {

          $timestamp = time();
          $daterange = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
          if (!empty($values['to'])) {
            $daterange = Helper::convertFromScreenToGmdate($values['to']);
          }
          if (!empty($values['from'])) {
            $package_info['start'] = Helper::convertFromScreenToGmdate($values['from']);
            $package_info['end'] = $daterange;
          }
        }

        $total_info = Helper::getUserTotalHours($record->uid, $daterange);
        if (!empty($package_info)) {
          $total_info = $package_info;
        }
        $summ = Helper::getSubSumm($record->uid, $record->coach, $daterange, $total_info);
        $total = !empty($total_info['total_hours']) ? $total_info['total_hours'] : 0;
        $data[$index] = ($total - $summ)/60;

      }
      if ($index == 'coach_name') {
        $coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($record->coach);
        $data['coach_name'] = $coach_obj->getDisplayName();
      }
    }

    //ReadExcel::writeToCSV($filename, $data);
    ReadExcel::writeToExcel($filename, $data, $indexes);

    $context['results']['filename'] = $filename;

    $count = isset($context['results']['count']) ? $context['results']['count'] : 0;
    $count++;
    $context['results']['count'] = $count;
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
