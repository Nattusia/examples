<?php

namespace Drupal\envision_crm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\Element\EntityAutocomplete;

/**
 * Provides a coach bulk assigning form
 */
class CoachToMultipleClientsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'coach_to_multiple_clients_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tempstore = \Drupal::service('tempstore.private')->get('envision_crm.coach_to_user.assign_multiple');
    $assign_users = $tempstore->get('assign_multiple');

    if (!$assign_users) {
      $form['message'] = [
        '#markup' => $this->t('Insufficient data to assign the package.')
      ];
      return $form;
    }

    $form = [];
    $roles = ['editor', 'publisher', 'superhero'];
    $form['#assign_users'] = $assign_users;

    $assign_string = '<h3>You are going to assign the coach to the next users:</h3>';
    $assign_string .= '<ul>';
    foreach($assign_users as $in_user) {

      $name_vals = [
        'field_first_name' => '',
        'field_last_name' => '',
      ];
      foreach ($name_vals as $fname => $fval) {
        if ($in_user->hasField($fname)) {
          $name_value = $in_user->{$fname}->getValue();
          if ($name_value) {
            $name_vals[$fname] = $name_value[0]['value'];
          }
        }
      }
      $assign_string .= '<li>' . $in_user->getEmail() . ' ' . implode(' ', $name_vals) . '</li>';
    }
    $assign_string .= '</ul>';

    $form['client-list'] = [
      '#markup' => $assign_string,
    ];

    $form['coach-type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Assign the coach of type'),
      '#options' => [
        'field_primary_coach' => 'Primary Coach',
        'field_backup_coach' => 'Backup Coach',
      ],
      '#default_value' => 'field_primary_coach',
      '#required' => TRUE,
    ];

    $form['coach'] = [
      '#title' => $this->t('Select the coach to assign'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('coach'),
      //'#autocomplete_path' => '/ajax/' . $coach . '/clients-autocomplete',
      '#autocomplete_route_name' => 'envision_crm.coach_autocomplete',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Assign the package'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $values['coach'] = EntityAutocomplete::extractEntityIdFromAutocompleteInput($values['coach']);
    $form_state->setValue('coach', $values['coach']);

    if ((!$values['coach']) ||
      (!$coachObj = \Drupal::entityTypeManager()->getStorage('user')->load($values['coach']))) {
      $form_state->setErrorByName('coach', $this->t('There is no coach with an id given'));
    }
    else {
      $form_state->set('coach_obj', $coachObj);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $clients = $form['#assign_users'];


      $batch = [
        'title' => $this->t('Assigning coach...'),
        'operations' => [],
        'finished' =>
          '\Drupal\envision_crm\Form\CoachToMultipleClientsForm::batchFinished',
        'init_message' => $this->t('Process is starting.'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to assign.'),
      ];

      foreach ($clients as $client) {
        //$form_state->setValue('client', $client->id());
        $batch['operations'][] = [['\Drupal\envision_crm\Form\CoachToMultipleClientsForm', 'assignCoach'], [$values['coach-type'], $values['coach'], $client]];
      }

      batch_set($batch);
  }

  public static function assignCoach($field, $coach, $client, &$context) {
    //$package = \Drupal::entityTypeManager()->getStorage('coaching_package')->load($package);
    //$clients = $package->field_client->getValue();
    $coaches = $client->{$field}->getValue();
    if (!$coaches) {
      $coaches = [];
    }

    $cleaned = envision_crm_clean_field_value($coaches, 'target_id');

    if (!in_array($coach, $cleaned)) {
      array_push($coaches, ['target_id' => $coach]);

      $client->{$field}->setValue($coaches);
      $client->save();
      //$package->field_client->setValue($clients);
      //$package->save();
      if(!isset($context['results']['count'])) {
        $context['results']['count'] = 0;
      }
      $context['results']['count'] = $context['results']['count'] + 1;
    }
    else {
      $context['results']['error'][] = $client->getDisplayName();
    }
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      if(isset($results['count'])) {
        \Drupal::messenger()->addMessage(t('Total clients assigned') . ' : ' . $results['count'], 'status', TRUE);
      }
      if (isset($results['error'])) {
        $error_text = '<ul>';
        foreach ($results['error'] as $client_id) {
          $error_text .= '<li>The client ' . $client_id . ' already have this coach assigned. </li>';
        }
        $error_text .= '</ul>';
        \Drupal::messenger()->addMessage(['#markup' => $error_text], 'warning', TRUE);
      }
    }
    else {
      \Drupal::messenger()->addMessage(t('Finished with an error.'), 'error', TRUE);
    }

    $tempstore = \Drupal::service('tempstore.private')->get('envision_crm.coach_to_user.assign_multiple');
    $tempstore->delete('assign_multiple');
  }
}
