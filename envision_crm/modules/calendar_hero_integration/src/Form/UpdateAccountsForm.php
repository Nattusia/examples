<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\calendar_hero_integration\Common;
use Drupal\calendar_hero_integration\Helper;
use Drupal\envision_crm\DataStorage;

/**
 * Provides a button-form to update calendar hero integration data for all coaches.
 */
class UpdateAccountsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_update_accounts_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Set Up'),
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

    $batch = [
      'title' => $this->t('Setup account processing...'),
      'operations' => [],
      'finished' =>
        '\Drupal\calendar_hero_integration\Form\CoachSetUpForm::batchFinished',
      'init_message' => $this->t('Set up account start'),
      'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => $this->t('Error occurred. Failed to setup the account.'),
    ];

    $params = [
      'roles' => ['egl_consultant'],
      'field_calendar_hero_token' => 'IS NOT NULL'
    ];

    $uids = DataStorage::getEntitiesByParams('user', $params);
    $coaches = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids);

    $webhooks = [
      'meeting_request_success',
      'new_meeting_request',
      'meeting_request_expired',
      'meeting_request_cancelled',
      'meeting_rescheduled',
      'new_contact_added',
      'meeting_completed',
      'meeting_started',
    ];

    foreach ($coaches as $coach) {
      $token = $coach->field_calendar_hero_token->getValue();
      //$batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CoachSetUpForm', 'saveToken'], [$coach, $values]];
      foreach ($webhooks as $webhook) {
        $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CoachSetUpForm', 'registerWebhook'], [$webhook, $coach->id(), $token[0]['value']]];
      }

      $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CoachSetUpForm', 'getMeetings'], [$token[0]['value'], $coach->id()]];
    }

    batch_set($batch);
  }

  public static function registerWebhook($webhook, $coach, $token, &$context) {
    $common = new Common();
    $delited = $common->registerWebhook($webhook, $coach, $token, TRUE);
    if ($delited->getStatusCode() == 200) {
      \Drupal::logger("Webhook $webhook for user $coach has been deleted");
    }
    $registered = $common->registerWebhook($webhook, $coach, $token);
    if ($registered->getStatusCode() == 200) {
      $resp = json_decode($registered->getBody()->getContents());
      if ($resp->id) {
        $context['results']['message'][] = 'The webhook ' . $webhook . ' has been registered';
      }
    }
    else {
      $resp = $response->getReasonPhrase();
      $context['results']['error'][] = 'We can\'t register the webhook ' . $webhook . '. The server response is '
        . $resp . '. Please contact the site administrator.';
    }
  }

  public static function saveToken($coach, $values) {
    $coach->field_calendar_hero_token->setValue($values['ch_token']);
    $coach->field_calendar_hero_id->setValue($values['ch_id']);
    $coach->save();
  }

  public static function getMeetings($token, $coach, &$context) {
    $common = new Common();
    $meetings = $common->getMeetings($token);
    self::process_operation($meetings, $coach, $context);
  }

public static function process_operation($items, $coach, &$context) {
  // Elements per operation.
  $limit = 1;

  // Set default progress values.
  if (empty($context['sandbox']['progress'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] = count($items);
  }

  // Save items to array which will be changed during processing.
  if (empty($context['sandbox']['items'])) {
    $context['sandbox']['items'] = $items;
  }

  $counter = 0;
  if (!empty($context['sandbox']['items'])) {
    // Remove already processed items.
    if ($context['sandbox']['progress'] != 0) {
      array_splice($context['sandbox']['items'], 0, $limit);
    }

    foreach ($context['sandbox']['items'] as $item) {
      if ($counter != $limit) {
        self::process_item($item, $coach, $context);

        $counter++;
        $context['sandbox']['progress']++;

        $context['message'] = t('Now syncronizing :progress of :count', [
          ':progress' => $context['sandbox']['progress'],
          ':count' => $context['sandbox']['max'],
        ]);

        // Increment total processed item values. Will be used in finished
        // callback.
        $context['results']['processed'] = $context['sandbox']['progress'];
      }
    }
  }

  // If not finished all tasks, we count percentage of process. 1 = 100%.
  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

  public static function process_item($item, $coach, &$context) {
    $timezone = new \DateTimeZone('GMT');
    $start_date = new \DateTime($item->dates[0], $timezone);
    $end_date = new \DateTime($item->dates[1], $timezone);
    $fields = [
      'meeting_id' => $item->id,
      'coach' => $coach,
      'start_date' => $start_date,
      'end_date' => $end_date,
      'status' => $item->taskStatus,
    ];
    Helper::updateMeetingsDatabase($fields);
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      if (isset($results['message'])) {
        foreach ($results['message'] as $message) {
\Drupal::messenger()->addMessage($message, 'status', TRUE);
        }
      }
      if(isset($results['count'])) {
        \Drupal::messenger()->addMessage(t('Total messages sent') . ' : ' . $results['count'], 'status', TRUE);
      }
      if(isset($results['error'])) {
        \Drupal::messenger()->addMessage(t('Messages have not been sent to the next mails'), 'warning', TRUE);
        $error_string = '';
        foreach($results['error'] as $key => $result) {
          $error_string .= $result . '; ';
        }

        \Drupal::messenger()->addMessage($error_string, 'warning', TRUE);
      }
    }
    else {
      \Drupal::messenger()->addMessage(t('Finished with an error.'), 'error', TRUE);
        $error_string = t('There are problem sending these mails ');
        foreach($results['error'] as $key => $result) {
          $error_string .= $result . '; ';
        }

        \Drupal::messenger()->addMessage($error_string, 'warning', TRUE);
    }

    $tempstore = \Drupal::service('tempstore.private')->get('chero_integration_invite_multiple');
    $tempstore->delete('invite_multiple');
    $tempstore->delete('coach');
  }
}
