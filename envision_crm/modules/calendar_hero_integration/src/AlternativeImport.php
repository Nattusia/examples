<?php
namespace Drupal\calendar_hero_integration;

use Drupal\envision_crm\DataStorage;

class AlternativeImport {

  public static function getMeetings($uid, &$context) {

    $coach = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $common = new Common();
    $token = $common->getHeroToken($coach);
    if ($token) {
      $meetings = $common->getMeetingsbyDate($token);
      if (!is_string($meetings)) {
        \Drupal\calendar_hero_integration\AlternativeImport::processMeetings($meetings, $uid, $context);
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

      $contactName = $contact->name;//$contact->contactName;
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

    $description = $item->description;
    if (strlen($description) > 254) {
      $description = substr($description, 0, 254);
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
      'client_name' => $contactName,
      'client_email' => $contactEmails,
      'uid' => $clientId,
      'duration' => $duration,
      'scheduled' => $scheduled,
      'updated' => $updated,
      'description' => $description,
    ];
    $keys = ['coach', 'client_email', 'date_daterange_value'];
    //ksm($fields);
    Helper::updateMeetingsDatabase($fields, $keys, 'meetings_report_data_alter');
    $count = isset($context['results']['count']) ? $context['results']['count'] : 0;
    $count++;
    $context['results']['count'] = $count;
  }
}
