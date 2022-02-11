<?php

namespace Drupal\coaching_package\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Query\Condition;

/**
 * Provides a coaching package assigning form.
 */
class CoachingPackageAssignMultipleForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'coaching_package_assign_multiple_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tempstore = \Drupal::service('tempstore.private')->get('coaching_package_assign_multiple');
    $assign_users = $tempstore->get('assign_multiple');
    $coach_id = $tempstore->get('coach');
    //$type = $tempstore->get('type') ? $tempstore->get('type') : 'clients';

    if ((!$assign_users) || (!$coach_id)) {
      $form['message'] = [
        '#markup' => $this->t('Insufficient data to assign the package.')
      ];
      return $form;
    }
    $coach = \Drupal::entityTypeManager()->getStorage('user')->load($coach_id);

    $form = [];
    $roles = ['editor', 'publisher', 'superhero'];
    $form['#user'] = $coach;
    $form['#assign_users'] = $assign_users;

    $assign_string = '<h3>Next users will be linked with the Coaching Package.</h3>';
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

    $form['package'] = [
      '#title' => $this->t('Coaching Package to assign'),
      '#type' => 'select',
      '#options' => $this->getPackages($coach),
      '#default_value' => $form_state->getValue('package'),
      '#empty_value' => 'Select the meeting type',
      '#required' => TRUE,
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $clients = $form['#assign_users'];


      $batch = [
        'title' => $this->t('Assigning packages...'),
        'operations' => [],
        'finished' =>
          '\Drupal\coaching_package\Form\CoachingPackageAssignMultipleForm::batchFinished',
        'init_message' => $this->t('Process is starting.'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to assign.'),
      ];

      foreach ($clients as $client) {
        //$form_state->setValue('client', $client->id());
        $batch['operations'][] = [['\Drupal\coaching_package\Form\CoachingPackageAssignMultipleForm', 'assignPackage'], [$values['package'], $client]];
      }

      batch_set($batch);
  }

  /**
   * Gets the available coaching packages.
   *
   * @return array
   *   The array with types ids as keys and packages titles as values.
   */
  public function getPackages($user) {

    $packages = [];
    $entities = [];
    if ($user->hasPermission('administer users')) {
      $query = \Drupal::entityTypeManager()->getStorage('coaching_package');
      $query_result = $query->getQuery();
      $entity_ids = $query_result->execute();

      $entities = $query->loadMultiple($entity_ids);
    }
    else {
      if ($user->hasField('field_coaching_package')) {
        $entities = $user->field_coaching_package->referencedEntities();
      }
    }

    foreach ($entities as $entity) {
      if (coaching_package_is_active($entity)) {
        $packages[$entity->id()] = $entity->getTitle();
      }
    }

    return $packages;
  }

  public static function assignPackage($package, $client, &$context) {
    //$package = \Drupal::entityTypeManager()->getStorage('coaching_package')->load($package);
    //$clients = $package->field_client->getValue();
    $packages = $client->field_coaching_package->getValue();
    if (!$packages) {
      $packages = [];
    }
    array_push($packages, ['target_id' => $package]);

    $client->field_coaching_package->setValue($packages);
    $client->save();
    //$package->field_client->setValue($clients);
    //$package->save();
    if(!isset($context['results']['count'])) {
      $context['results']['count'] = 0;
    }
    $context['results']['count'] = $context['results']['count'] + 1;
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      if(isset($results['count'])) {
        \Drupal::messenger()->addMessage(t('Total clients assigned') . ' : ' . $results['count'], 'status', TRUE);
      }
    }
    else {
      \Drupal::messenger()->addMessage(t('Finished with an error.'), 'error', TRUE);
    }

    $tempstore = \Drupal::service('tempstore.private')->get('coaching_package_assign_multiple');
    $tempstore->delete('assign_multiple');
    $tempstore->delete('coach');
    $tempstore->delete('type');
  }
}
