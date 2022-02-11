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
class CalendarHeroExportFormAlternative extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_export_form_alternative';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // $months = [];
    // for ($i = 0; $i < 12; $i++) {
    //   $timestamp = mktime(0, 0, 0, date('n') - $i, 1);
    //   $months[date('n', $timestamp)] = date('F', $timestamp);
    // }

    // $form['month'] = [
    //   '#type' => 'select',
    //   '#options' => $months,
    //   '#empty_option' => 'Select',
    //   '#required' => TRUE,
    //   '#title' => $this->t('Select the report period'),
    // ];
    //$yesterday = date('Y-m-d',strtotime("-1 days"));
    //$tomorrow = date('Y-m-d',strtotime("+1 days"));

    $request = \Drupal::request();
    $query = $request->query->all();
    $values = [
      'field_cohort' => '',
      'field_company_division' => '',
      'from' => '',
      'to' => '',
      'coach' => '',
    ];

    foreach ($values as $ind => &$value) {
      $value = empty($form_state->getValue($ind)) ? $value : $form_state->getValue($ind);
      $value = empty($query[$ind]) ? $value : $query[$ind];
    }

    $form['period'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Period'),
    ];
    $form['period']['from'] = [
      '#type' => 'date',
      '#title' => $this->t('From'),
      '#default_value' => $values['from'],
    ];

    $form['period']['to'] = [
      '#type' => 'date',
      '#title' => $this->t('To'),
      '#default_value' => $values['to'],
    ];

    $form['coach'] = [
      '#type' => 'select',
      '#title' => $this->t('Coach'),
      '#default_value' => $values['coach'],
      '#empty_option' => $this->t('Coach'),
      '#options' => $this->getCoaches(),
    ];

    $form['field_cohort'] = [
      '#type' => 'select',
      '#options' => $this->getCohorts(),
      '#empty_option' => 'Cohort',
      '#title' => $this->t('Cohort'),
      '#default_value' => $values['field_cohort'],
    ];


    $form['field_company_division'] = [
      '#type' => 'select',
      '#options' => $this->getOptions('companies_divisions'),
      '#empty_option' => 'Company',
      '#title' => $this->t('Company'),
      '#default_value' => $values['field_company_division'],
    ];

    $form['actions'] = [
      '#type' => 'container',
    ];

    $form['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply filters'),
      '#op' => 'filter',
    ];

    $form['actions']['reset'] = [
      '#type' => 'link',
      '#url' => Url::fromRoute('calendar_hero_integration.calendar_hero_report'),
      '#title' => $this->t('Reset'),
      '#attributes' => [
        'class' => ['button', 'form-submit'],
      ],
    ];

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
          '\Drupal\calendar_hero_integration\Form\CalendarHeroExportForm::batchFinished',
        'init_message' => $this->t('Export start'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to setup the account.'),
      ];
      $params = [
        'roles' => ['egl_consultant'],
        'field_calendar_hero_token' => 'IS NOT NULL'
      ];

      $records = $this->exportData($values);
      $filename = "945" . date('Y_m_d_H_i');
      foreach ($records as $record) {
        $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroExportFormAlternative', 'processRecord'], [$record, $filename]];
      }

      batch_set($batch);
    }
    else {

      $options['query'] = $values;
      $params = [];
      $redirect_url = Url::fromRoute('calendar_hero_integration.calendar_hero_report', $params, $options);
      $form_state->setRedirect('calendar_hero_integration.calendar_hero_report', $params, $options);
    }
  }

  public function exportData($month) {

    $timezone = Helper::getTimeZone();
    $fromTime = new \DateTime('01-09-2021', new \DateTimezone($timezone));
    $timestamp_from = $fromTime->getTimestamp();
    $from = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp_from);

    $toTime = new \DateTime('30-09-2021', new \DateTimezone($timezone));
    $timestamp_to = $toTime->getTimestamp();
    $to = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp_to);


    $connection = \Drupal::database();
    $query = $connection->select('meetings_report_data_alter', 'alter');
    //$query->leftjoin('meetings_report_data', 'first', 'alter.meeting_id = first.meeting_id');
    //$query->condition('first.meeting_id', NULL, 'IS NOT NULL');
    //$query->addExpression("TIMESTAMPDIFF(MINUTE, alter.date_daterange_value, alter.date_daterange_end_value)", 'meeting_time');
    $query->addExpression('alter.duration/60', 'meeting_time');
    $query->condition('alter.coach', 881);
    $query->condition('alter.date_daterange_value', $from, '>=');
    $query->condition('alter.date_daterange_end_value', $to, '<=');
    $query->fields('alter');

    $results = $query->execute()->fetchAll();

    return $results;
  }

  public function getCohorts() {
    return DataStorage::getCohorts();
  }

  public function getOptions($vid) {
    return DataStorage::getOptions($vid);
  }

  public function getCoaches() {
    $params = [
      'roles' => ['egl_consultant'],
      //'field_calendar_hero_token' => 'IS NOT NULL'
    ];

    $uids = DataStorage::getEntitiesByParams('user', $params);
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids);
    $coaches = [];
    foreach ($users as $user) {
      $coaches[$user->id()] = $user->getDisplayName();
    }
    asort($coaches);

    return $coaches;
  }

  public static function processRecord($record, $filename, &$context) {

    $indexes = [
       //'year' => 'Billing Year',
       //'month' => 'Billing Period',
       'client_name' => 'Client Name',
       'client_email' => 'Client Email',
       'date' => 'Meeting date',
       'date_daterange_value' => 'Start Time',
       'date_daterange_end_value' => 'Endt Time',
       //'field_first_name_value' => 'Coach First Name',
       //'field_last_name_value' => 'Coach Last Name',
       'coach' => "Coach ID",
       'mail' => 'Coach Email',
       'description' => 'Meeting title',
       //'type' => "Meeting type",
       'duration' => "Session Hours",
       //'status' => "Meeting status",
       'meeting_id' => "Meeting ID",
       'scheduled' => "Scheduled",
       'updated' => "Updated",

    ];

    $data = [];

    foreach ($indexes as $index => $header) {

      //$data[$index] = '"' . $record->{$index} . '"';
      $data[$index] = $record->$index;
      $convert = [
        'date_daterange_value',
        'date_daterange_end_value',
        'scheduled',
        'updated',
      ];
      if (in_array($index, $convert)) {
        $gmt = new \DateTimezone('GMT');
        $f_index = $index == 'date' ? 'date_daterange_end_value' : $index;
        $date = new \DateTime($record->{$f_index}, $gmt);
        $timezone = Helper::getTimeZone();
        $date->setTimeZone(new \DateTimezone($timezone));
        $format = ($index == 'date')  ?
          'm/d/Y' : 'm/d/Y g:i a';
        //$data[$index] = '" ' . $date->format($format) . '"';
          $data[$index] = $date->format($format);
      }


      if ($index == 'duration') {
        $data[$index] = (int)$record->{$index}/60;
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
