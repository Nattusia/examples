<?php

namespace Drupal\envision_crm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\envision_crm\ReadExcel;
use Drupal\envision_crm\DataStorage;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a Envision CRM form.
 */
class UploadUsersForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'envision_crm_upload_users';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $coach = '') {

    $form = [
      '#attributes' => array('enctype' => 'multipart/form-data'),
    ];

    $form['coach'] = [
      '#type' => 'hidden',
      '#value' => $coach,
    ];

    $xls_path = '/' . drupal_get_path('module', 'envision_crm') . '/files/upload-users.xlsx';
    $url = Url::fromUserInput($xls_path);
    $link = Link::fromTextAndUrl('this template', $url)->toString();
    $upload_directory = empty($coach) ? 'admin' : $coach;

    $form['template']['#markup'] =
     'Please  download and fill out ' . $link . ' to upload new clients';

    $validators = [
      'file_validate_extensions' => ['xlsx'],
    ];
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Excel File'),
      '#size' => 20,
      '#weight' => 3,
      '#required' => TRUE,
      '#description' => $this->t('Upload Excel template file. (xlsx)'),
      '#upload_validators' => $validators,
      '#upload_location' => 'temporary://' . $upload_directory,
    ];

    $form['cohort'] = [
      '#type' => 'select',
      '#title' => $this->t('Cohort'),
      '#options' => DataStorage::getCohorts(),
      '#empty_option' => $this->t('Select Cohort'),
    ];

    $default_company = $form_state->getValue('company');
    $form['company_division'] = [
      '#type' => 'container',
      '#prefix' => '<div id = "company-division-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['company_division']['company'] = [
      '#type' => 'select',
      '#title' => $this->t('Company'),
      '#options' => DataStorage::getOptions('companies_divisions'),
      '#empty_option' => $this->t('Select Company'),
      '#default_value' => $form_state->getValue('company') !== NULL ?
        $form_state->getValue('company') : '',
      '#ajax' => [
        'wrapper' => 'form-wrapper',
        'callback' => '\Drupal\envision_crm\Form\UploadUsersForm::divisionCallback',
      ],
    ];

    $form['company_division']['division'] = [
      '#type' => 'select',
      '#title' => $this->t('Division'),
      '#options' => DataStorage::getOptions('companies_divisions', $form_state->getValue('company')),
      '#empty_option' => $default_company === NULL ?
        $this->t('Select the company first') : $this->t('Select division'),
      '#default_value' => $form_state->getValue('division') !== NULL ?
        $form_state->getValue('division') : '',
      '#validated' => TRUE,
    ];

    $form['description'] = [
      '#markup' => '<div class = "warning">If you set the Cohort, Company and Division then the value in the appropriate xls column will be ignored for all users. Existing users will be updated.</div>',
    ];

    $form['#prefix'] = '<div id = "form-wrapper">';
    $form['#suffix'] = '</div>';


    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  public static function getOptions($parent = 0) {
    if($parent === NULL) {
      return [];
    }
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('companies_divisions', $parent, 1, FALSE);
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
      $trigger = $form_state->getTriggeringElement();
      if ($trigger['#type'] == 'submit') {
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($form_state->getValue('file')[0]);
        $file_name = $file->get('uri')->value;
        $values = $form_state->getValues();
        $results = ReadExcel::readExcelData($file_name);

        $total = count($results['results']);
        $batch = [
          'title' => $this->t('Importing Entity Data...'),
          'operations' => [],
          'finished' => '\Drupal\envision_crm\DataStorage::entities_import_batch_finished',
          'init_message' => $this->t('Import process is starting.'),
          'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
          'error_message' => $this->t('Error occurred. Failed to import.'),
        ];
        foreach ($results['results'] as $key => $result) {
          $row = $key+1;
          $batch['operations'][] = [['\Drupal\envision_crm\DataStorage', 'save'], [$result, $row, $values]];
        }

        batch_set($batch);
      }
  }

  public static function divisionCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
