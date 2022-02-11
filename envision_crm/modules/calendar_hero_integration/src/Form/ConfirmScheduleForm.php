<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\calendar_hero_integration\Common;

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class ConfirmScheduleForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_confirm_schedule_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $events = [], $uids = NULL) {

    $form = [];
    $form['#events'] = $events;
    $members = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids);
    $form['#coach'] = $members[$uids['host_uid']];
    $form['#user'] = $members[$uids['guest_uid']];
    $form['confirm'] = [
      '#type' => 'submit',
      '#value' => 'Confirm the schedule',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {}

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $form['#user'];
    $common = new Common();

    if (!$common->contactExists($user->getEmail(), $form['#coach'])) {
      $new_contact = $common->createContact($user, $form['#coach']);
    }

      $batch = [
        'title' => $this->t('Scheduling...'),
        'operations' => [],
        'finished' =>
          '\Drupal\calendar_hero_integration\Form\ConfirmScheduleForm::batchFinished',
        'init_message' => $this->t('Scheduling is starting.'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to schedule.'),
        //'batch_redirect' => '/schedule/multiple/' . $values['template'],
      ];
      foreach ($form['#events'] as $event_id) {
        $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\ConfirmScheduleForm', 'schedule'], [$event_id, $form['#coach'], $form['#user']]];
      }

      batch_set($batch);

  }

  public static function schedule($event_id, $coach, $user, &$context) {
    $event = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event')->load($event_id);
    $context['results']['count'] = isset($context['results']['count']) ?
      $context['results']['count'] : 0;
    if ((!$event->hasField('field_calendar_hero_id')) ||
       (!$event->hasField('field_calendar_hero_type'))) {
      $context['results']['error'][] = 'The event ' . $event_id . 'was not updated.';
      return;
    }
    else {
      $ch_id_val = $event->field_calendar_hero_id->getValue();
      if ((!empty($ch_id_val)) && (!empty($ch_id_val[0]['value']))) {
        $context['results']['error'][] = 'The event ' . $event_id . ' the field is not empty';
        $context['results']['count']++;
      }
      else {
    $common = new Common();
    $type_val = $event->field_calendar_hero_type->getValue();
    $meeting_type = $common->getMeetingTypes($coach, $type_val[0]['value']);
    $interval = isset($meeting_type->meetingLength) ?
      $meeting_type->meetingLength->value : 30;
        $dateVal = $event->date_daterange->getValue();
        $body = [
          'dateStart' => $dateVal[0]['value'],//"2021-08-05T10:00:00.000Z",
          'dateEnd' => $dateVal[0]['end_value'],
          'meetingLength' => $interval,
          'type' => $type_val[0]['value'],
          'subject' => $dateVal[0]['value'] .
            ' ' . $type_val[0]['value'] . ' with ' .
            $coach->getDisplayName(). ' and ' . $user->getDisplayName(),
          'contactEmails' => [
            $user->getEmail(),
          ],
        ];
         $resp = $common->createMeeting($coach, $body);
    if ((is_object($resp)) && (!empty($resp->id))) {
        $event->field_calendar_hero_id->setValue($resp->id);
        $event->save();
        $connection = \Drupal::database();
        $query = $connection->update('calendar_hero_meetings');
        $query->condition('meeting_id', $event->id());
        $query->fields([
          'meeting_id' => $resp->id,
          'status' => 'scheduled',
        ]);
        $context['results']['count']++;

      }
    }
  }
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      if(isset($results['count'])) {
        \Drupal::messenger()->addMessage(t('Total events created/updated') . ' : ' . $results['count'], 'custom', TRUE);
      }
      if (isset($results['message'])) {
        foreach ($results['message'] as $message) {
          \Drupal::messenger()->addMessage($message, 'status', TRUE);
        }
      }
      if(isset($results['error'])) {
        \Drupal::messenger()->addMessage(t('The next events have not been created'), 'custom_warning', TRUE);
        foreach($results['error'] as $key => $results) {
              \Drupal::messenger()->addMessage($results, 'custom_warning', TRUE);
        }
      }
    }
    else {
      \Drupal::messenger()->addMessage(t('Finished with an error.'), 'custom_error', TRUE);
        foreach($results['error'] as $key => $results) {
              \Drupal::messenger()->addMessage($results, 'custom_error', TRUE);
        }
    }

    $template = isset($results['template']) ? $results['template'] : 'meeting';
    //if (isset($results['template'])) {
      //$url = Url::fromRoute('calendar_hero_integration.batch_result', ['template' => $template]);
      //$response = new RedirectResponse($url->toString());
      //$response->send();
    //}
  }
}
