<?php

namespace Drupal\coaching_package;

use Drupal\envision_crm\DataStorage;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Helping functions.
 */
class Helper {

  /**
   * Checks if the current page is a part of the user profile.
   *
   * @return mixed
   *   FALSE if it is not a user page and user entity object if it's.
   */
  public static function isUserPage() {
    $route_match = \Drupal::routeMatch();
    $user_page = FALSE;
    if ($user = $route_match->getParameter('user')) {
      $user_page = $user;
    }
    if ($coach = $route_match->getParameter('coach')) {
      if($coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($coach)) {
        $user_page = $coach_obj;
      }
    }

    return $user_page;
  }

  /**
   * Gets user's active packages with their data.
   *
   * @param int $uid
   *   The user id.
   *
   * @return array
   *   Array of objects. Every element is a set of the particular package data.
   */
  public static function getUserActivePackage($uid) {
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $fields = [
      'field_coaching_package',
      'field_primary_coach',
      'field_backup_coach',
    ];
    $params_arr = [];
    foreach ($fields as $fname) {
      $fvals = $user->{$fname}->getValue();
      $params_arr[$fname] = [];
      if ($fvals) {
        $params_arr[$fname] = self::cleanFieldValue($fvals, 'target_id');
      }
    }
    $params = [
      'field_coaching_package' => $params_arr['field_coaching_package'],
      'uid' => array_merge($params_arr['field_primary_coach'], $params_arr['field_backup_coach']),

    ];
    if (!empty($params['uid'])) {
      $coaches = DataStorage::getEntitiesByParams('user', $params);
    }
    if (!empty($coaches)) {
      $connection = \Drupal::database();
      $query = $connection->select('coaching_package__field_start_end', 'end');
      $query->leftjoin('user__field_coaching_package', 'cp', 'end.entity_id = cp.field_coaching_package_target_id');
      // //$query->leftjoin('coaching_package__field_client', 'client', 'end.entity_id = client.entity_id');
      // //$query->leftjoin('coaching_package__field_coach', 'coach', 'end.entity_id = coach.entity_id');
      $query->leftjoin('coaching_package__field_total_hours', 'hours', 'hours.entity_id = end.entity_id');

      // //$query->condition('client.field_client_target_id', $uid);
      if (!empty($coaches)) {
        $query->condition('cp.entity_id', $coaches, 'IN');
      }
      $query->condition('end.field_start_end_end_value', date('Y-m-d'), '>=');

      $query->fields('end', ['entity_id', 'field_start_end_value', 'field_start_end_end_value']);
      $query->addField('cp', 'entity_id', 'coach_id');
      //$query->fields('cp', ['entity_id']);
      // //$query->fields('coach', ['field_coach_target_id']);
      $query->fields('hours', ['field_total_hours_value']);
      $entity_ids = $query->execute()->fetchAll();

      return $entity_ids;
    }
  }

  /**
   * Gets time for scheduled events in particular package.
   *
   * @param object $package_record
   *   The object with a package data.
   * @param int $uid
   *   The user id.
   * @param boolean $upcoming
   *   TRUE if only time of upcoming events is needed.
   *
   * @return string
   *  The number of hours or hours and minutes delimited with semicologn.
   */
  public static function getEventsAndTime($package_record, $uid, $remains = TRUE, $upcoming = FALSE) {

    $final_string = '';
    $entity_type = 'opigno_calendar_event';
    $params = [
      'field_calendar_event_members' => [$uid],
    ];
    $params1 = [
      'field_calendar_event_members' => [$package_record->coach_id],
    ];

    $event_ids = DataStorage::getEntitiesByParams($entity_type, $params);
    $event_ids_by_coach = DataStorage::getEntitiesByParams($entity_type,$params1);
    $select_ids = array_intersect($event_ids, $event_ids_by_coach);
    $result = [0];
    if (!empty($select_ids)) {
      $connection = \Drupal::database();
      $query = $connection->select('opigno_calendar_event__date_daterange');
      $query->condition('entity_id', $select_ids, 'IN');
      if (!$upcoming) {
        $query->condition('date_daterange_value', $package_record->field_start_end_value, '>=');
      }
      else {
        $timestamp = time();
        $current_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
        $query->condition('date_daterange_value', $current_date, '>=');
      }
      $query->condition('date_daterange_end_value', $package_record->field_start_end_end_value, '<=');
      //$query->addExpression("TIME_TO_SEC(TIMEDIFF(date_daterange_end_value, date_daterange_value))", 'difference');
      //$query->addExpression("TIME_TO_SEC(SUBTIME(date_daterange_end_value, date_daterange_value))", 'difference');
      $query->addExpression("TIMESTAMPDIFF(SECOND, date_daterange_value, date_daterange_end_value)", 'difference');
      $result = $query->execute()->fetchCol();
    }

    $final_string = $remains ? $package_record->field_total_hours_value : 0;
    //if ($result) {
    $total_minutes = $remains ?
    (($package_record->field_total_hours_value * 60) - (array_sum($result)/60)) : array_sum($result)/60;

    $hours = floor($total_minutes / 60);
    $minutes = ($total_minutes % 60);
    $final_string = $hours;
    if ($minutes > 0) {
      $final_string = $final_string . ':' . $minutes;
    }
    //}
    return $final_string;
  }

  /**
   * Gets the profile id to show remaining hours for.
   * @todo transfer this function to envision_crm module.
   */
  public static function getProfileId($coach_only = FALSE) {
    $profile = self::isUserPage();
    if ((!$profile) && (!$coach_only)) {
      $current_user = \Drupal::currentUser();
      if ($current_user->isAnonymous()) {
        $moduleHandler = \Drupal::service('module_handler');
        if ($moduleHandler->moduleExists('custom_xai_calendar_embed')) {
          $route_match = \Drupal::routeMatch();
          if ($route_match->getRouteName() == 'custom_xai_calendar_embed.link_calendar') {
            $request = \Drupal::request();
            $token = $request->query->get('token');
            if ($token) {
              $uids = custom_xai_calendar_embed_get_uid_by_token($token);
              if (!empty($uids['guest_uid'])) {
                $profile_id = $uids['guest_uid'];
              }
            }
          }
        }
      }
      else {
        $profile_id = $current_user->id();
      }
    }
    else {
      $profile_id = $profile->id();
      if ($coach_only) {
        if (!$profile->hasRole('egl_consultant')) {
          $profile_id = 0;
        }
      }
    }

    $profile_id = isset($profile_id) ? $profile_id : 0;

    return $profile_id;
  }

  public static function getCoachDashboardLink($profile_id) {
    $route = 'envision_crm.coach_dashboard';

    $params['coach'] = $profile_id;
    $options['attributes']['class'] = ['coach-dashboard-link'];
    $url = Url::fromRoute($route, $params, $options);
    $link_text = 'My Dashboard';
    $link = Link::fromTextAndUrl($link_text, $url)->toRenderable();

    return $link;
  }

  public static function cleanFieldValue($val_arr, $type = 'value') {
    return envision_crm_clean_field_value($val_arr, $type);
  }

}
