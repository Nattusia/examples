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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\calendar_hero_integration\AlternativeImport;

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class CalendarHeroImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
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
        'title' => $this->t('Importing...'),
        'operations' => [],
        'finished' =>
          '\Drupal\calendar_hero_integration\Form\CalendarHeroImportForm::batchFinished',
        'init_message' => $this->t('Importing start'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to setup the account.'),
      ];
    $params = [
      'roles' => ['egl_consultant'],
      'field_calendar_hero_token' => 'IS NOT NULL'
    ];

    $uids = DataStorage::getEntitiesByParams('user', $params);

// $uid = reset($uids);
// $coach = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
// $common = new Common();
// $token = $common->getHeroToken($coach);
// if ($token) {
//   $meetings = $common->getMeetings($token, 'closed');
//   ksm($meetings);
// }

    foreach ($uids as $uid) {
      $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroImportForm', 'getMeetings'], [$uid, 'closed']];
      $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroImportForm', 'getMeetings'], [$uid, 'active']];
      $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroImportForm', 'getMeetings'], [$uid, 'upcoming']];
      $batch['operations'][] = [['\Drupal\calendar_hero_integration\AlternativeImport', 'getMeetings'], [$uid]];
    }

    batch_set($batch);
  }

  public static function getMeetings($uid, $mtype, &$context) {

    $coach = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $common = new Common();
    $token = $common->getHeroToken($coach);
    if ($token) {
      $meetings = $common->getMeetings($token, $mtype);
      if (!is_string($meetings)) {
        \Drupal\calendar_hero_integration\Form\CalendarHeroImportForm::processMeetings($meetings, $uid, $context);
      }
      else {
        $coach_user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
        $context['results']['error'][] = "We did not get meetings for the coach " . $coach_user->getDisplayName()
         . ' The server responce is: ' . $meetings;
      }
    }
  }

  public static function processMeetings($meetings, $uid, &$context) {
    foreach ($meetings as $item) {

      //$contact_email = implode('; ', $item->contacts[0])
      $contact = $item->contacts[0];
      $contactEmails = implode('; ', $contact->contactEmails);
      if (strlen($contactEmails) > 254) {
        $contactEmails = substr($contactEmails, 254);
      }
      $contactName = $contact->contactName;
      $params = [
        'mail' =>  $contact->contactEmails,
      ];
      $clientId = DataStorage::getEntitiesByParams('user', $params);
      $clientId = !empty($clientId) ? reset($clientId) : 0;

      $timezone = new \DateTimeZone('GMT');
      $start_date = new \DateTime($item->dates[0], $timezone);
      $end_date = new \DateTime($item->dates[1], $timezone);
      $scheduled = new \DateTime($item->dateAdded);
      $updated = new \DateTime($item->dateUpdated);
      $fields = [
        'meeting_id' => $item->id,
        'coach' => $uid,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'status' => $item->taskStatus,
        'client_name' => $contactName,
        'client_email' => $contactEmails,
        'uid' => $clientId,
        'duration' => $item->meetingLength,
        'type' => $item->meetingType,
        'title' => $item->info,
        'scheduled' => $scheduled,
        'updated' => $updated,
      ];
      //ksm($fields);
      Helper::updateMeetingsDatabase($fields, [], 'meetings_report_data');
      $count = isset($context['results']['count']) ? $context['results']['count'] : 0;
      $count++;
      $context['results']['count'] = $count;
    }
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      if (!empty($results['count'])) {
        \Drupal::messenger()->addStatus($results['count'] . ' meeting records have been updated/created');
      }
      else {
        \Drupal::messenger()->addWarning('The import is empty, no new entries.');
      }
      if (!empty($results['error'])) {
        foreach ($results['error'] as $error_message) {
          \Drupal::messenger()->addError($error_message);
        }
      }

      $url = Url::fromRoute('calendar_hero_integration.calendar_hero_report');
      $response = new RedirectResponse($url->toString());
      $response->send();
    }
    else {
      \Drupal::messenger()->addError("Something went wrong. Calendar Hero meetings import does not work");
    }
  }
}
