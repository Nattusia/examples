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

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class CalendarHeroImportFormAlternative extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_import_form_alternative';
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
          '\Drupal\calendar_hero_integration\Form\CalendarHeroImportFormAlternative::batchFinished',
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
//   $meetings = $common->getMeetingsbyDate($token);
//   ksm($meetings);
// }

    foreach ($uids as $uid) {

      $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroImportFormAlternative', 'getMeetings'], [$uid]];
      //$batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroImportForm', 'getMeetings'], [$uid, 'active']];
      //$batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CalendarHeroImportForm', 'getMeetings'], [$uid, 'upcoming']];
    }

    batch_set($batch);
  }

  public static function getMeetings($uid, &$context) {

    $coach = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $common = new Common();
    $token = $common->getHeroToken($coach);
    if ($token) {
      $meetings = $common->getMeetingsbyDate($token);
      if (!is_string($meetings)) {
        \Drupal\calendar_hero_integration\Form\CalendarHeroImportFormAlternative::processMeetings($meetings, $uid, $context);
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
      $contacts = $item->contacts;
      if (!empty($contacts)) {
        foreach ($contacts as $contact) {
          self::processContactMeeting($item, $uid, $contact, $context);
        }
      }
      else {
        self::processContactMeeting($item, $uid, '', $context);
      }
    }
  }

  public static function processContactMeeting($item, $uid, $contact = '', &$context) {
    if (!empty($contact)) {
      $contactEmails = implode('; ', $contact->email);
      if (strlen($contactEmails) > 254) {
        $contactEmails = substr($contactEmails, 0, 254);
      }
      $description = $item->description;
      if (strlen($description) > 254) {
        $description = substr($description, 0, 254);
      }
      $contactName = $contact->contactName;
      $params = [
        'mail' =>  $contact->email,
      ];
      $clientId = DataStorage::getEntitiesByParams('user', $params);
      $clientId = !empty($clientId) ? reset($clientId) : 0;
    }
    else {
      $contactEmails = '';
      $contactName = '';
      $clientId = 0;
    }

    $timezone = new \DateTimeZone('GMT');
    $start_date = new \DateTime($item->dateStart, $timezone);
    $end_date = new \DateTime($item->dateEnd, $timezone);

    $start_date_timestamp = $start_date->getTimestamp();
    $end_date_timestamp = $end_date->getTimestamp();
    $duration = ($end_date_timestamp - $start_date_timestamp)/60;
    $scheduled = new \DateTime($item->dateAdded);
    $updated = new \DateTime($item->dateUpdated);
    $fields = [
      'meeting_id' => $item->id,
      'coach' => $uid,
      'start_date' => $start_date,
      'end_date' => $end_date,
      'status' => '',//$item->taskStatus,
      'client_name' => $contactName,
      'client_email' => $contactEmails,
      'uid' => $clientId,
      'duration' => $duration,
      'type' => '',//$item->meetingType,
      'title' => '', //$item->description,
      'scheduled' => $scheduled,
      'updated' => $updated,
      'description' => $description,
    ];
    //ksm($fields);
    Helper::updateMeetingsDatabase($fields, 'meetings_report_data_alter');
    $count = isset($context['results']['count']) ? $context['results']['count'] : 0;
    $count++;
    $context['results']['count'] = $count;
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
