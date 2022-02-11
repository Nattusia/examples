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

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class CalendarHeroExportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_export_form';
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
      'zero' => '',
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
      '#states' => [
        'disabled' => [
          'input[name="zero"]' => ['checked' => TRUE],
        ],
        'visible' => [
          'input[name="zero"]' => ['checked' => FALSE],
        ],
      ],
    ];


    $form['field_company_division'] = [
      '#type' => 'select',
      '#options' => $this->getOptions('companies_divisions'),
      '#empty_option' => 'Company',
      '#title' => $this->t('Company'),
      '#default_value' => $values['field_company_division'],
      '#states' => [
        'disabled' => [
          'input[name="zero"]' => ['checked' => TRUE],
        ],
        'visible' => [
          'input[name="zero"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['zero'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show only entries with uid 0'),
      '#default_value' => $values['zero'],
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
      $records1 = Helper::exportDataAlter($values);
      $filename = "EGL_report_" . date('Y_m_d_H_i');
      foreach ($records as $record) {
        $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroExportForm', 'processRecord'], [$record, $filename]];
      }
      foreach ($records1 as $record1) {
        $batch['operations'][] = [['\Drupal\calendar_hero_integration\AlternativeExport', 'processRecord'], [$record1, $filename]];
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
    return Helper::exportData($values);
  }

  public function getCohorts() {
    return DataStorage::getCohorts();
  }

  public function getOptions($vid) {
    return DataStorage::getOptions($vid);
  }

  public function getCoaches() {
    return Helper::getCoaches();
  }

  public static function processRecord($record, $filename, &$context) {

    $indexes = [
       'year' => 'Billing Year',
       'month' => 'Billing Period',
       'client_name' => 'Client Name',
       'client_email' => 'Client Email',
       'uid' => 'Client UID',
       'field_request_id_value' => 'Request ID',
       'date_daterange_end_value' => 'Session Date',
       'field_first_name_value' => 'Coach First Name',
       'field_last_name_value' => 'Coach Last Name',
       'mail' => 'Coach Email',
       'title' => 'Meeting title',
       'type' => "Meeting type",
       'duration' => "Session Hours",
       'status' => "Meeting status",
       'meeting_id' => "Meeting ID",
       'scheduled' => "Scheduled",
       'updated' => "Updated",

    ];

    $data = [];

    foreach ($indexes as $index => $header) {

      //$data[$index] = '"' . $record->{$index} . '"';
      $data[$index] = $record->$index;
      $convert = [
        'date_daterange_end_value',
        'scheduled',
        'updated',
      ];
      if (in_array($index, $convert)) {
        //$data[$index] = '" ' . $date->format($format) . '"';
          $data[$index] = Helper::convertDateRow($record, $index, $index);
      }
      // if ($index == 'duration') {
      //   $data[$index] = '" ' . $record->{$index} . '"';
      // }

    }

    //ReadExcel::writeToCSV($filename, $data);
    ReadExcel::writeToExcel($filename, $data);

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
