<?php

namespace Drupal\calendar_hero_integration;

use Drupal\envision_crm\DataStorage;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\calendar_hero_integration\Common;


/**
 * Helping functions.
 */
class Helper {

  public static function getDaysToExclude($coach, $month = 0, $template = 'meeting') {


    $look_year = date('Y');
    $selector_id = '';
    $month_origin = '';
    $request = \Drupal::request();
    $post = $request->request->all();

    if (!empty($post)) {
      $selector_id = isset($post['selectorId']) ? $post['selectorId'] : $selector_id;
      $month_origin = isset($post['monthOrigin']) ? $post['monthOrigin'] : $month_origin;
    }
    $month = $month == date('n') ? 0 : $month;

    /****** weeks and free days part1 **************/
    $common = new Common();
    $coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($coach);
    $meeting_type = $common->getMeetingTypes($coach_obj, $template);
//ksm($meeting_type);
    $interval = (isset($meeting_type->meetingLength) && isset($meeting_type->meetingLength->value)) ?
    $meeting_type->meetingLength->value : 30;

    $buffers = [
      '30' => 10,
      '60' => 15,
    ];
    $buffer_time = 10;
    foreach ($buffers as $buf_key =>$buffer) {
      if ($interval >= $buf_key) {
        $buffer_time = $buffer;
      }
    }

    $in_minutes = $interval + $buffer_time;

    $interval = $interval * 60;
    $buffer_time = $buffer_time * 60;
    $user_timezone = new \DateTimezone(self::getTimezone());
    $tomorrow = new \DateTime('tomorrow', $user_timezone);

//ksm($meetings);
    $windows = (array) $meeting_type->availabilityWindows->value;
    if ($month == 0) {
      $date1 = $tomorrow;
      //$date1 = date('Y-m-d H:m');
      $date2 = date('Y-m-t H:i');
    }
    else {
      //$date1 = date('\0\1/\0' . $month . '/Y H:m');
      $time = date('H:i');
      $year = $look_year;
      $date1 = $year . '-' . $month . '-01 ' . $time;
      $number = cal_days_in_month(CAL_GREGORIAN, $month, $year);
//kint('number - ' . $number);
      //$formatted_line = $number . '/0' . $month . '/' . $year . ' ' . $time;
      //$date2 = $year . '-' . $month . '-' . $number . ' ' . $time;
      $date2 = $year . '-' . ($month + 1) . '-01 ' . $time;
    }

    $weekends = [];
    $hours_available = [];


    $datePeriod = self::returnDates($date1, $date2, 'day');
    // $available_base = [];
    foreach($datePeriod as $date) {
      //ksm($date->format('d/m/Y H:m'));
      $week_day = strtolower($date->format('l'));
      if (empty($windows[$week_day])) {
        $weekends[] = $date->format('Y-m-d');

      }
      if (!empty($windows[$week_day])) {
        $day = $windows[$week_day];
        $number = $date->format('j');
//    ksm($day);
        $parts = count($day) - 1;
        $count = 0;

        foreach ($day as $day_part) {
          $start_time = explode(':', $day_part->start);
          $end_time = explode(':', $day_part->end);
          $date->setTime($start_time[0], $start_time[1]);
          $the_day_first_time = $date->getTimestamp();
          $end_time_hour = $end_time[0] + 1;
          $date->setTime($end_time_hour, $end_time[1]);

          $the_day_last_time = $date->getTimestamp();


          if (self::isDayFull($coach, $the_day_first_time, $the_day_last_time, $interval + $buffer_time)) {
            $count++;
            if ($count >= $parts) {
              $weekends[] = $date->format('Y-m-d');
            }
          }
          else {
            $start_date_time = $date->setTime($start_time[0], $start_time[1])->format('Y-m-d H:i');
            $end_date_time = $date->setTime($end_time_hour, $end_time[1])->format('Y-m-d H:i');

            $intervals = self::getIntervals($coach, $the_day_first_time, $the_day_last_time, $interval + $buffer_time, FALSE, $buffer_time);

            if (!$intervals) {
              $day_by_hours_period = self::returnDates($start_date_time, $end_date_time, 'minute', $in_minutes);

              foreach ($day_by_hours_period as $dhour) {
                $hours_available[$number][] = $dhour->format('H:i');
                //$hours_available[$number][] = $dhour->format('g:iA');
              }
            }
            else {
              foreach ($intervals as $period) {
                $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
                $start = new \DateTime($period['start'], $timezone);
                $start->setTimezone($user_timezone);

                $end = new \DateTime($period['end'], $timezone);
                $end->setTimezone($user_timezone);
                $period_by_hours = self::returnDates($start, $end, 'minute', $in_minutes);

                foreach ($period_by_hours as $dhour) {
                  $hourstamp = $dhour->getTimestamp();
                  $endstamp = $end->getTimestamp();
                  if($endstamp - $hourstamp >= $interval + $buffer_time) {
                    $hours_available[$number][] = $dhour->format('H:i');
                    //$hours_available[$number][] = $dhour->format('g:iA');
                  }
                }
              }
            }
          }
        }
//!empty($windows['week_day']);
      }
    }
/**********************weeks and freedays *********/

    $new_tomorrow = $month == 0 ? '' : new \DateTime($year . '-' . $month . '-01');
    $new_tomorrow = self::checkTomorrow($weekends, $new_tomorrow);
    $tomorrow_day = $new_tomorrow->format('j');
    $year = $new_tomorrow->format('Y');
    //$hours_available = array_unique($hours_available);

    return [
      'month' => $month,
      'disabledDates' => $weekends,
      'allowTimes' => $hours_available,
      'defaultDate' => $new_tomorrow->format('Y-m-d'),
      'defaultHours' => $hours_available[$tomorrow_day],
      'defaultTime' => $hours_available[$tomorrow_day][0],
      'minDate' => $new_tomorrow->format('Y-m-d'),
      'template' => $template,
      'coach' => $coach,
      'week_days' => self::getWeekDaysAvailable($meeting_type),
      'interval' => $interval,
      'selectorId' => $selector_id,
      'monthOrigin' => $month_origin,
      'rescheduling' => 0,
    ];
  }

public static function getWeekDaysAvailable($meeting_type) {
  $days_obj = $meeting_type->days->value;
  $days = [];
  foreach ($days_obj as $day_key => $day_val) {
    if ($day_val == 1) {
      $wday = new \DateTime($day_key);
      $days[] = $wday->format('D');
    }
  }

  return $days;
}

public static function checkTomorrow($weekends, $tomorrow = '') {
  $tomorrow = empty($tomorrow) ? new \DateTime('tomorrow') : $tomorrow;
  $formatted = $tomorrow->format('Y-m-d');
  if (in_array($formatted, $weekends)) {
    $tomorrow->modify('+1 day');
    return self::checkTomorrow($weekends, $tomorrow);
  }
  else {
    return $tomorrow;
  }
}

public static function returnDates($fromdate, $todate, $step = 'hour', $qty = 1) {
    $steps = [
      'day' => 'P' . $qty . 'D',
      'hour' => 'PT' . $qty . 'H',
      'week' => 'P' . $qty . 'W',
      'minute' => 'PT' . $qty . 'M',
    ];
    //$fromdate = is_string($fromdate) ?
      //\DateTime::createFromFormat('Y-j-d H:m', $fromdate) : $fromdate;
    $user_timezone = new \DateTimezone(self::getTimezone());

    $fromdate = is_string($fromdate) ? new \DateTime($fromdate, $user_timezone) : $fromdate;
    //$todate = is_string($todate) ?
      //\DateTime::createFromFormat('Y-j-d H:m', $todate) : $todate;
    $todate = is_string($todate) ? new \DateTime($todate, $user_timezone) : $todate;

    return new \DatePeriod(
        $fromdate,
        new \DateInterval($steps[$step]),
        //$todate->modify('+' . $qty . ' ' . $step)
        $todate
    );
}

function getBasicAvailableHours() {

}

public static function isDayFull($coach, $start_time, $end_time, $interval) {
  return self::getIntervals($coach, $start_time, $end_time, $interval, TRUE);
}

public static function isTimeSchedulled($coach, $start_time, $end_time) {
    // $start_time->setTime(0, 0);
    // $start_date = $start_time->getTimestamp();
    // $start_time->modify('+ 1 day');
    // $end_date = $start_time->getTimestamp();
    $params = [
    'field_calendar_event_members' => [$coach],
  ];
//  $event_ids = DataStorage::getEntitiesByParams('opigno_calendar_event', $params);
//  if (!empty($event_ids)) {

    $connection = \Drupal::database();
    //$query = $connection->select('opigno_calendar_event__date_daterange', 'a');
    $query = $connection->select('calendar_hero_meetings', 'a');
    //$query->condition('a.entity_id', $event_ids, 'IN');
    $query->condition('a.status', ['scheduled', 'rescheduled', 'reserved'], 'IN');
    $query->condition('a.coach', $coach);
    $timestamp = time();
    $current_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
    $start_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_time);
    $end_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_time);

// start_time <= && end_time => then our start time
// start_time > then our start time && end_time < then our finish time
// start_time <= then our finish time && end_time => then our finish_time
// end_time < then our finish time && end_time => our start_time

    $query->condition('a.date_daterange_end_value', $current_date, '>');
    $andGroup1 = $query->andConditionGroup()
      ->condition('a.date_daterange_value', $start_time, '<=')
      ->condition('a.date_daterange_end_value', $start_time, '>=');
    $andGroup2 = $query->andConditionGroup()
      ->condition('a.date_daterange_value', $start_time, '>=')
      ->condition('a.date_daterange_end_value', $end_time, '<=');
    $andGroup3 = $query->andConditionGroup()
      ->condition('a.date_daterange_value', $end_time, '<=')
      ->condition('a.date_daterange_end_value', $end_time, '>=');
    $andGroup4 = $query->andConditionGroup()
      ->condition('a.date_daterange_end_value', $end_time,  '<')
      ->condition('a.date_daterange_end_value', $start_time, '>=');
    $orGroup = $query->orConditionGroup()
      ->condition($andGroup1)
      ->condition($andGroup2)
      ->condition($andGroup3)
      ->condition($andGroup4);
    $query->condition($orGroup);
    //$query->fields('a');
    $query->addExpression('COUNT(*)');
    $result = $query->execute()->fetchField();

    return $result > 0;
//  }
}

public static function subqueryIntegrated($coach, $start_time, $end_time, $return_query = TRUE) {
    $connection = \Drupal::database();
    $query = $connection->select('calendar_hero_meetings', 'a');
    //$query->condition('a.entity_id', $event_ids, 'IN');
    $query->condition('a.coach', $coach);
    $query->condition('a.status', ['scheduled', 'rescheduled', 'reserved'], 'IN');
      //if (!$upcoming) {
        //$query->condition('date_daterange_value', $package_record->field_start_end_value, '>=');
      //}
      //else {
    $timestamp = time();
    $current_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
    $start_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_time);
    $end_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_time);

    $query->condition('a.date_daterange_end_value', $current_date, '>');
    $query->condition('a.date_daterange_value', $start_time, '>=');
    $query->condition('a.date_daterange_end_value', $end_time, '<');
    $query->leftjoin('calendar_hero_meetings', 'b', 'a.date_daterange_value < b.date_daterange_value AND a.meeting_id != b.meeting_id');

    $query->fields('a', ['meeting_id', 'date_daterange_value', 'date_daterange_end_value']);
    //$query->fields('a', ['entity_id', 'date_daterange_value']);
    //$query->fields('b', ['entity_id', 'date_daterange_value']);
    $query->addExpression("MIN(b.date_daterange_value)", "next_event");

    $query->groupBy('a.date_daterange_value');
    $query->groupBy('a.date_daterange_end_value');
    $query->orderBy('a.date_daterange_value');
    $query->groupBy('a.meeting_id');
    $subresult = $query->execute()->fetchAll();
    if (!empty($subresult)) {
      if (!$return_query) {
        return $subresult;
      }
      else {
        return [
          'query' => $query,
          'subresult' => $subresult,
        ];
      }
    }
}

public static function subquery($event_ids, $start_time, $end_time, $return_query = TRUE) {
    $connection = \Drupal::database();
    $query = $connection->select('opigno_calendar_event__date_daterange', 'a');
    $query->condition('a.entity_id', $event_ids, 'IN');
      //if (!$upcoming) {
        //$query->condition('date_daterange_value', $package_record->field_start_end_value, '>=');
      //}
      //else {
    $timestamp = time();
    $current_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
    $start_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_time);
    $end_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_time);

    $query->condition('a.date_daterange_end_value', $current_date, '>');
    $query->condition('a.date_daterange_value', $start_time, '>=');
    $query->condition('a.date_daterange_end_value', $end_time, '<');
    $query->leftjoin('opigno_calendar_event__date_daterange', 'b', 'a.date_daterange_value < b.date_daterange_value AND a.entity_id != b.entity_id');

    $query->fields('a', ['entity_id', 'date_daterange_value', 'date_daterange_end_value']);
    //$query->fields('a', ['entity_id', 'date_daterange_value']);
    //$query->fields('b', ['entity_id', 'date_daterange_value']);
    $query->addExpression("MIN(b.date_daterange_value)", "next_event");

    $query->groupBy('a.date_daterange_value');
    $query->groupBy('a.date_daterange_end_value');
    $query->orderBy('a.date_daterange_value');
    $query->groupBy('a.entity_id');
    $subresult = $query->execute()->fetchAll();
    if (!empty($subresult)) {
      if (!$return_query) {
        return $subresult;
      }
      else {
        return [
          'query' => $query,
          'subresult' => $subresult,
        ];
      }
    }
  }

public static function getIntervals($coach, $start_time, $end_time, $interval, $count = FALSE, $buffer_time = 0) {

  // $params = [
  //   'field_calendar_event_members' => [$coach],
  // ];
  // $event_ids = DataStorage::getEntitiesByParams('opigno_calendar_event', $params);

  //if (!empty($event_ids)) {

    $connection = \Drupal::database();
    //$subresult = self::subquery($event_ids, $start_time, $end_time);
    $subresult = self::subqueryIntegrated($coach, $start_time, $end_time);
    if (!empty($subresult)) {
      $start_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_time);
      $end_time = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_time);
      //if (date('d', $start_time) == '11') {
      //}
      $first_last_interval = self::checkFirstLastInterval($start_time, $end_time, $subresult['subresult']);

      $main_query = $connection->select($subresult['query'], 'c');
      $main_query->where('TIMESTAMPDIFF(SECOND, c.date_daterange_end_value, c.next_event) > ' . $interval);
      if ($count) {
        $main_query->addExpression('COUNT(*)', 'windows_qty');
        $result = $main_query->execute()->fetchField();

        return (($result == 0) && ($first_last_interval['first_interval'] < $interval) &&
          ($first_last_interval['last_interval'] < $interval));
      }
      else {
        $main_query->addExpression("TIMESTAMPDIFF(SECOND, c.date_daterange_end_value, c.next_event)", 'difference');
        $main_query->fields('c', ["next_event", "date_daterange_end_value", "date_daterange_value"]);
        $result = $main_query->execute()->fetchAll();
        $times_available = [];

        if ($first_last_interval['first_interval'] >= $interval) {

          $times_available[] = [
            'start' => $start_time,
            'end' => $subresult['subresult'][0]->date_daterange_value,
          ];
        }

        foreach ($result as $result) {
          if (self::checkNextIvent($end_time, $result)) {
            $times_available[] = [
              'start' => self::addBufferTime($result->date_daterange_end_value, $buffer_time),
              'end' => $result->next_event,
            ];
          }
        }

        if ($first_last_interval['last_interval'] >= $interval) {
          $last_time = end($subresult['subresult']);
            $times_available[] = [
              'start' => self::addBufferTime($last_time->date_daterange_end_value, $buffer_time),
              'end' => $end_time,
            ];
        }

        return $times_available;
      }
    }
  //}
  return FALSE;
}

public static function addBufferTime($time, $buffer_time, $timezone = 'GMT') {
  $to_convert = new \DateTime($time, new \DateTimezone($timezone));
  $to_convert->modify('+ ' . $buffer_time . ' seconds');

  return $to_convert->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
}

public static function checkNextIvent($end_time, $result_to_check) {
  $end_time = strtotime($end_time);
  $next_event = strtotime($result_to_check->next_event);

  return $next_event <= $end_time;
}

public static function checkFirstLastInterval($start_time, $end_time, $subresult) {

  $first = $subresult[0];
  $last = end($subresult);

  $first_time = strtotime($first->date_daterange_value);
  $start_time = strtotime($start_time);


  $last_time = strtotime($last->date_daterange_end_value);
  $end_time = strtotime($end_time);

  return [
    'first_interval' => $first_time - $start_time,
    'last_interval' => $end_time - $last_time,
  ];
}

public static function createEvent($query_params) {
      //$start_date = $date = date_timestamp_get(date_create($query_params['event_start_time']));
      //$end_date = $date = date_timestamp_get(date_create($query_params['event_end_time']));

      $event = [
        'type' => 'calendar_hero_event',
        'date_daterange' => [
          'value' => $query_params['event_start_time'],
          'end_value' => $query_params['event_end_time'],
      //    'value' => gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_date),
      //    'end_value' => gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_date),
        ],
        'uid' => $query_params['uid'],
      ];


      $event_type_obj = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event_type')->load('meeting');
      $title = !empty($query_params['template']) ? $query_params['template'] : 'Some event';
      $event['title'] = $title;
      if ($coach = \Drupal::entityTypeManager()->getStorage('user')->load($query_params['uid'])) {
        $event['title'] = $title . ' with ' . $coach->getDisplayName();
      }
      if ($sender = \Drupal::entityTypeManager()->getStorage('user')->load($query_params['sender'])) {
        $event['title'] = $event['title'] . ' and ' . $sender->getDisplayName();
      }
      $storage = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event');
      $new_event = $storage->create($event);
      if ($new_event->hasField('field_calendar_event_members')) {
        $new_event->field_calendar_event_members->setValue([
          0 => ['target_id' => $query_params['uid']],
          1 => ['target_id' => $query_params['sender']],
        ]);
      }

      if (($new_event->hasField('field_calendar_hero_id')) && (!empty($query_params['ch_id']))) {
        $new_event->field_calendar_hero_id->setValue($query_params['ch_id']);
      }

      if (($new_event->hasField('field_calendar_hero_type')) && (!empty($query_params['template']))) {
        $new_event->field_calendar_hero_type->setValue($query_params['template']);
      }

      $new_event->save();
      return $new_event->id();
      //$url = Url::fromRoute('entity.opigno_calendar_event.canonical',
      //  ['opigno_calendar_event' => $new_event->id()]);
}

public static function updateEvent($query_params) {
  $event = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event')->load($query_params['event_id']);
  //date_daterange->getValue();
  $new_event_time = [
    'value' => $query_params['event_start_time'],
    'end_value' => $query_params['event_end_time'],
  ];
  $event->date_daterange->setValue($new_event_time);
  if (($event->hasField('field_calendar_hero_id')) && (!empty($query_params['ch_id']))) {
    $event->field_calendar_hero_id->setValue($query_params['ch_id']);
  }

  if ($event->save()) {
    return $event->id();
  }

}

  public static function reserveEvent($query_params, $event_id) {
      $fields = [
        'meeting_id' => $event_id,
        'coach' => $query_params['uid'],
        'start_date' => $query_params['event_start_time'],
        'end_date' => $query_params['event_end_time'],
        'status' => 'reserved',
      ];

      self::updateMeetingsDatabase($fields);

  }

  public static function getTimezone() {
    $config = \Drupal::config('system.date')->get('timezone');
    return $config['default'];
  }

  public static function countCycles($params) {
    $timezone = new \DateTimezone(self::getTimezone());
    $start_date = new \DateTime($params['start'], $timezone);
    $end_date = new \DateTime($params['end'], $timezone);
    $selected_days = array_diff($params['days'], [0]);
    $hrs = $start_date->format('H');
    $minutes = $start_date->format('i');
    // we catch dates for the rest of the first week
    $cycles = self::cycleDays($start_date, $selected_days, $end_date, TRUE);
    $start_date->modify('sunday');
    $start_date->setTime($hrs, $minutes);
    $weeks = self::returnDates($start_date, $end_date, 'week');

    $count = empty($cycles) ? $params['qty'] : $params['qty'] + 1;


    foreach ($weeks as $cycle => $week) {
      if ($count % $params['qty'] == 0) {
        $cycles = array_merge($cycles, self::cycleDays($week, $selected_days, $end_date));
      }
      $count++;
    }

    return $cycles;
  }

  public static function cycleDays($week, $selected_days, $end_date, $first = FALSE) {
    $cycles = [];
    $timezone = new \DateTimezone(self::getTimezone());
    if (!$first) {
      $week->modify('+1 week');
      $week_end = $week->format('Y-m-d');
      $week_end = new \DateTime($week_end, $timezone);
      $week->modify('-1 week');

      $week_by_days = self::returnDates($week, $week_end, 'day');
    }
    else {
      $curr_week = $week->format('Y-m-d H:i');
      $week->modify('sunday');
      $curr_week = new \DateTime($curr_week, $timezone);
      $week_by_days = self::returnDates($curr_week, $week, 'day');

    }

    foreach ($week_by_days as $day) {
      if ($day <= $end_date) {
        if (in_array($day->format('D'), $selected_days)) {
          $cycles[] = $day;
        }
      }
    }

    return $cycles;
  }

  public static function updateMeetingsDatabase($fields, $keys = [], $table = 'calendar_hero_meetings') {
    $connection = \Drupal::database();
    $query = $connection->merge($table);

    $fields['date_daterange_value'] = is_object($fields['start_date']) ?
        $fields['start_date']->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT) :
        $fields['start_date'];
    $fields['date_daterange_end_value'] = is_object($fields['end_date']) ?
        $fields['end_date']->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT) :
        $fields['end_date'];
    unset($fields['start_date']);
    unset($fields['end_date']);
    $convert = ['scheduled', 'updated'];
    foreach ($convert as $convert) {
      if (!empty($fields[$convert])) {
        $fields[$convert] = $fields[$convert]->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
      }
    }

    if (empty($keys)) {
      $query->keys(['coach', 'meeting_id'],  ['coach' => $fields['coach'], 'meeting_id' => $fields['meeting_id']]);
    }
    else {
      $keysMapping = [];
      foreach ($keys as $key) {
        $keysMapping[$key] = $fields[$key];

      }
      $query->keys($keys, $keysMapping);
    }

    $query->fields($fields);
    $query->execute();
  }

  /**
   * Retrieves data from the database.
   *
   * @param array $values
   *   The filter form values.
   *
   * @return array
   *   The database query result.
   */
  public static function exportData($values = []) {

    $timezone = Helper::getTimeZone();
    $connection = \Drupal::database();
    $query = $connection->select('meetings_report_data', 'data');
    $year = date('Y');
    //$query->addExpression("TIMESTAMPDIFF(HOUR, date_daterange_value, date_daterange_end_value)", 'meeting_time');
    //$query->addExpression("WHERE (MONTH(date_daterange_value) = " . $month . ")");
    if (!empty($month)) {
      $query->where("MONTH(date_daterange_end_value) = " . $month);
    }
    if (!empty($values['from'])) {
      $fromTime = new \DateTime($values['from'], new \DateTimezone($timezone));
      $timestamp_from = $fromTime->getTimestamp();
      $from = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp_from);
      $query->condition('date_daterange_value', $from, '>=');

    }

    if (!empty($values['to'])) {
      $toTime = new \DateTime($values['to'], new \DateTimezone($timezone));
      $timestamp_to = $toTime->getTimestamp();
      $to = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp_to);
      $query->condition('date_daterange_end_value', $to, '<=');
    }
    //$query->where("YEAR(date_daterange_end_value) = " . $year);
    $query->addExpression("YEAR(date_daterange_end_value)", 'year');
    $query->addExpression("MONTH(date_daterange_end_value)", 'month');
    //$query->addExpression('date_daterange_value AT TIME ZONE ' . $timezone, 'start');
    //$query->addExpression("FORMAT(date_daterange_value, 'DD/MM/YYYY')", 'date');
    $query->addExpression("FORMAT(duration/60, 1)", 'duration');
    $query->leftjoin('user__field_first_name', 'first_name', 'first_name.entity_id = data.coach');
    $query->leftjoin('user__field_last_name', 'last_name', 'last_name.entity_id = data.coach');
    $query->leftjoin('user__field_request_id', 'request_id', 'request_id.entity_id = data.uid');
    $query->leftjoin('users_field_data', 'users', 'users.uid = data.coach');
    if (!empty($values['coach'])) {
      $query->condition('data.coach', $values['coach']);
    }
    $query->leftjoin('users_field_data', 'clients', 'clients.uid = data.uid');

    if (!empty($values['field_company_division'])) {
      $filter_uids = self::getUsersByField('field_company_division', $values['field_company_division']);
      if (!empty($filter_uids)) {
        $query->condition('clients.uid', $filter_uids, 'IN');
      }
      else {
        //return empty data
        return [];
      }
    }
    if (!empty($values['field_cohort'])) {
      $filter_uids = self::getUsersByGroup($values['field_cohort']);
      if (!empty($filter_uids)) {
        $query->condition('clients.uid', $filter_uids, 'IN');
      }
      else {
        return [];
      }
    }

    $query->fields('data', [
      'meeting_id',
      'status',
      'client_name',
      'client_email',
      'uid',
      'date_daterange_end_value',
      'type',
      'title',
      'scheduled',
      'updated',
    ]);
    $query->fields('first_name', ['field_first_name_value']);
    $query->fields('last_name', ['field_last_name_value']);
    $query->fields('request_id', ['field_request_id_value']);
    $query->fields('users', ['mail']);

    self::addSorting($query, $values);
    if (!empty($values['pager'])) {
    //   $query->pager(25);
      $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
      $result = $pager->execute()->fetchAll();
    }
    else {

      $result = $query->execute()->fetchAll();
    }

    return $result;
  }

  /**
   * Add sorting to the query.
   *
   * @param object $query
   *   The database query object.
   * @param array $values
   *   The filter form values.
   */
  public static function addSorting(&$query, $values) {
    if (isset($values['order'])) {
      $headers = [
         //'year' => 'Billing Year',
         //'month' => 'Billing Period',

        [ 'field' => 'client_name', 'data' => 'Client Name'],
        [ 'field' => 'client_email', 'data' => 'Client Email'],
        [ 'field' => 'field_request_id_value', 'data' => 'Request ID'],
        [ 'field' => 'date_daterange_end_value', 'data' => 'Session Date'],
        [ 'field' => 'field_first_name_value', 'data' => 'Coach First Name'],
        [ 'field' => 'field_last_name_value', 'data' => 'Coach Last Name'],
        [ 'field' => 'mail', 'data' => 'Coach Email'],
        [ 'field' => 'title', 'data' => 'Meeting title'],
        [ 'field' => 'type', 'data' => "Meeting type"],
        [ 'field' => 'duration', 'data' => "Session Hours"],
        [ 'field' => 'status', 'data' => "Meeting status"],
        [ 'field' => 'meeting_id', 'data' => "Meeting ID"],
        [ 'field' => 'scheduled', 'data' => "Scheduled"],
        [ 'field' => 'updated', 'data' => "Updated"],

      ];

      foreach ($headers as $header) {
        if ($values['order'] == $header['data']) {
          $orderField = $header['field'];
        }
      }

      $sort = !empty($values['sort']) ? $values['sort'] : 'asc';
      $query->orderBy($orderField, $sort);
    }

  }

  public static function getUserMeetings($values) {

    $params = [
      'roles' => ['egl_consultant'],
      //'field_calendar_hero_token' => 'IS NOT NULL'
    ];
    $timezone = self::getTimezone();
    $coaches = DataStorage::getEntitiesByParams('user', $params);
    $connection = \Drupal::database();
    $query = $connection->select('meetings_report_data_alter', 'a');
    $query->condition('a.uid', $coaches, 'NOT IN');
    if (!empty($values['uid'])) {
      $query->condition('a.uid', $values['uid']);
    }
    $query->condition('a.uid', 0, '!=');
    if (!empty($values['coach'])) {
      $query->condition('a.coach', $values['coach']);
    }
    if (!empty($values['from'])) {
      $from = self::convertFromScreenToGmdate($values['from']);
      $query->condition('a.date_daterange_value', $from, '>=');
    }
    if (!empty($values['to'])) {
      $to = self::convertFromScreenToGmdate($values['to']);
      $query->condition('a.date_daterange_value', $to, '<=');
    }

    if (!empty($values['field_company_division'])) {
      $filter_uids = self::getUsersByField('field_company_division', $values['field_company_division']);
      if (!empty($filter_uids)) {
        $query->condition('a.uid', $filter_uids, 'IN');
      }
      else {
        //return empty data
        return [];
      }
    }
    if (!empty($values['field_cohort'])) {
      $filter_uids = self::getUsersByGroup($values['field_cohort']);
      if (!empty($filter_uids)) {
        $query->condition('a.uid', $filter_uids, 'IN');
      }
      else {
        return [];
      }
    }
    if (!empty($values['common'])) {
      $query->groupBy('a.uid');
      $query->groupBy('a.coach');
      $query->groupBy('a.client_name');
      $query->groupBy('a.client_email');
      $query->fields('a', ['coach', 'uid', 'client_name', 'client_email']);
    }
    else {
      $query->fields('a', ['meeting_id', 'date_daterange_value', 'duration', 'coach', 'description', 'uid', 'client_name', 'client_email']);
      $query->orderBy('a.date_daterange_value');
    }

    if (!empty($values['pager'])) {
    //   $query->pager(25);
      $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
      $result = $pager->execute();
    }
    else {
      $result = $query->execute();
    }
    return $result;
  }

  /**
   * Internal use only function to check if is there double (different ids but the same time) for some meeting record.
   *
   * @param int $uid
   *   The user id.
   * @param int $coach
   *   The coach uid.
   * @param string $datetime
   *   The GMT date time YYYY-mm-ddT00:00:00 format
   * @param string $meeting_id
   *   The meeting id as it transfered from the calendar hero.
   *
   * @return array
   *   The array with doubles found or an empty array.
   */
  public static function getDouble($uid, $coach, $datetime, $meeting_id) {
    $connection = \Drupal::database();
    $query = $connection->select('meetings_report_data_alter', 'a');
    $query->condition('a.uid', $uid);
    $query->condition('a.coach', $coach);
    $query->condition('a.date_daterange_value', $datetime);
    $query->condition('a.meeting_id', $meeting_id, '!=');

    $query->fields('a');
    $result = $query->execute()->fetchAll();

    return $result;
  }

  /**
   * Gets summ of minutes of meetings for given coach and given user.
   *
   * @param int $uid
   *   The user id.
   * @param int $coach
   *   The coach uid.
   * @param string $daterange
   *   The GMT date time YYYY-mm-ddT00:00:00 format.
   *
   * @return int
   *   The summ time in minutes.
   */
  public static function getSubSumm($uid, $coach, $daterange, $start_end = []) {

    $connection = \Drupal::database();
    $query = $connection->select('meetings_report_data_alter', 'a');
    $query->condition('a.uid', $uid);
    $query->condition('a.coach', $coach);
    $query->condition('a.date_daterange_value', $daterange, '<=');
    if (!empty($start_end)) {

      $timezone = self::getTimezone();
      if (!empty($start_end['start'])) {
        $start_time = new \DateTime($start_end['start'], new \DateTimezone($timezone));
        $start_timestamp = $start_time->getTimestamp();
        $start_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_timestamp);
        $th = self::getUserTotalHours($uid, $start_date);
        if (!empty($th)) {
          if (!empty($th['total_hours'])) {
            if ($start_end['start'] > $th['start']) {
              $middle_time = new \DateTime($th['end'], new \DateTimezone($timezone));
              $middle_timestamp = $middle_time->getTimestamp();
              $middle_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $middle_timestamp);
              $query->condition('a.date_daterange_value', $middle_date, '>=');
            }
          }
        }
        $query->condition('a.date_daterange_value', $start_date, '>=');
      }
      if (!empty($start_end['end'])) {

        $end_time = new \DateTime($start_end['end'], new \DateTimezone($timezone));
        $end_timestamp = $end_time->getTimestamp();
        $end_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_timestamp);
        $query->condition('a.date_daterange_value', $end_date, '<=');
      }
    }
    // $query->fields('a');
    // $result = $query->execute()->fetchAll();

    $query->addExpression('SUM("duration")', 'summ');
    //$query->groupBy('a.coach');
    $sum = $query->execute()->fetchField();

    // $sum = 0;
    // $ids = [];
    // $times = [];
    // foreach ($result as $res) {
    //   //$sum = $sum + $res->duration;
    //   if ((!in_array($res->meeting_id, $ids)) && (!in_array($res->date_daterange_value, $times))) {
    //     $sum = $sum + $res->duration;
    //     $ids[] = $res->meeting_id;
    //     $times[] = $res->date_daterange_value;
    //    }
    // }

    return $sum;
  }

  /**
   * Requries data from the database using the meetings_report_data_alter table.
   * This table was added to take the data from the alternative Calendar Hero endpoint.
   *
   * @param array $values
   *   The filter form values.
   *
   * @return array
   *   The database query result.
   */
  public static function ExportDataAlter($values) {

    $connection = \Drupal::database();
    $query = $connection->select('meetings_report_data_alter', 'alter');
    //$query->condition('first.meeting_id', NULL, 'IS NOT NULL');
    //$query->addExpression("TIMESTAMPDIFF(MINUTE, alter.date_daterange_value, alter.date_daterange_end_value)", 'meeting_time');
    $query->addExpression('alter.duration/60', 'meeting_time');
    $query->leftjoin('user__field_first_name', 'first_name', 'first_name.entity_id = alter.coach');
    $query->leftjoin('user__field_last_name', 'last_name', 'last_name.entity_id = alter.coach');
    $query->leftjoin('users_field_data', 'users', 'users.uid = alter.coach');
    $query->leftjoin('user__field_request_id', 'request_id', 'alter.uid = request_id.entity_id');

    if (!empty($values['coach'])) {
      $query->condition('alter.coach', $values['coach']);
    }
    if (!empty($values['zero'])) {
      $query->condition('alter.uid', 0);
    }
    else {
      $query->condition('alter.uid', 0, '!=');
    }
    if (!empty($values['from'])) {
      $from = self::convertFromScreenToGmdate($values['from']);
      $query->condition('alter.date_daterange_value', $from, '>=');
    }
    if (!empty($values['to'])) {
      $to = self::convertFromScreenToGmdate($values['to']);
      $query->condition('alter.date_daterange_end_value', $to, '<=');
    }

    if (!empty($values['field_company_division'])) {
      $filter_uids = self::getUsersByField('field_company_division', $values['field_company_division']);
      if (!empty($filter_uids)) {
        $query->condition('alter.uid', $filter_uids, 'IN');
      }
      else {
        //return empty data
        return [];
      }
    }
    if (!empty($values['field_cohort'])) {
      $filter_uids = self::getUsersByGroup($values['field_cohort']);
      if (!empty($filter_uids)) {
        $query->condition('alter.uid', $filter_uids, 'IN');
      }
      else {
        return [];
      }
    }
    $query->fields('alter');
    $query->fields('first_name', ['field_first_name_value']);
    $query->fields('last_name', ['field_last_name_value']);
    $query->fields('request_id', ['field_request_id_value']);
    $query->fields('users', ['mail']);
    //$query->groupBy('meeting_id');
    if (!empty($values['pager'])) {
    //   $query->pager(25);
      $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
      $result = $pager->execute()->fetchAll();
    }
    else {
      //$result = $query->execute()->fetchAll();
      $entries = $query->execute();
      $result = [];
      $line_keys = [
        'client_email',
        'client_name',
        'uid',
      ];
      foreach ($entries as $entry) {
        $result[$entry->meeting_id] = isset($result[$entry->meeting_id]) ?
          $result[$entry->meeting_id] : new \stdClass();
        foreach ($entry as $key => $data) {
          if (in_array($key, $line_keys)) {
            if (!isset($result[$entry->meeting_id]->{$key})) {
              $result[$entry->meeting_id]->{$key} = $data;
            }
            else {
              $result[$entry->meeting_id]->{$key} .= ', ' . $data;
            }
          }
          else {
            $result[$entry->meeting_id]->{$key} = $data;
          }
        }
      }
    }

    return $result;
  }

  /**
   * The function for the filtering. Gets uids by field value.
   * @param string $field
   *   The field mashine name. Should be in the user profile.
   * @param string $value
   *   Users will be filtered acording to this value.
   *
   * @return array
   *   The array of uids.
   */
  public static function getUsersByField($field, $value) {
    $params = [
      $field => [$value],
    ];

    if ($field == 'field_company_division') {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('companies_divisions', $value, 1, FALSE);
      $cd = [];
      foreach ($terms as $term) {
        $cd[] = $term->tid;
      }
      $cd[] = $value;
      $params[$field] = $cd;
    }

    $uids = DataStorage::getEntitiesByParams('user', $params);

    return $uids;
  }

  /**
   * Gets users by group id. Used for filtering by a cohort.
   *
   * @param integer $group
   *   The group id.
   *
   * @return array
   *   The array of uids, which are the group memebers.
   */
  public static function getUsersByGroup($group) {
    $connection = \Drupal::database();
    $query = $connection->select('group_content_field_data');
    $query->condition('type', 'opigno_class-group_membership');
    $query->condition('gid', $group);
    $query->fields('group_content_field_data', ['entity_id']);
    $uids = $query->execute()->fetchCol();

    return $uids;
  }

  /**
   * Prepares date for output.
   *
   * @param object $record
   *   The database record.
   * @param string $index
   *   The parameter name.
   * @param string $f_index
   *   The alternative index, used if the data by the same parameter should be converted another way.
   *   Otherwise, it's equal to $index.
   * @param array $header
   *   The headers array.
   */
  public static function convertDateRow($record, $index, $f_index, $header = []) {
    $gmt = new \DateTimezone('GMT');
        //$f_index = $index == 'date' ? 'date_daterange_end_value' : $index;
    $date = new \DateTime($record->{$f_index}, $gmt);
        //$timezone = Helper::getTimeZone();
        //$date->setTimeZone(new \DateTimezone($timezone));
    $format = 'm/d/Y g:i a';
    if (($index == 'date_daterange_value') || ($index == 'date_daterange_end_value')) {
      if (!empty($record->date_daterange_value)) {
        $start_date = new \DateTime($record->date_daterange_value, $gmt);
        $end_date = new \DateTime($record->date_daterange_end_value, $gmt);
        $interval = $start_date->diff($end_date);
        $format = $interval->days > 0 ? $format : 'g:i a';
      }
    }
    $format = (($index == 'time') || ($f_index == 'time'))  ?  'g:i a' : $format;
    $format = (($index == 'date') || ($f_index == 'date'))  ?  'm/d/Y' : $format;
    if (!empty($header)) {
      if ($index == 'date_daterange_end_value' && $header['data'] == 'Session Date') {
        $format = 'm/d/Y';
      }
    }
    //$data[$index] = '" ' . $date->format($format) . '"';
    return $date->format($format);
  }

  public static function getTableName($field_name) {
    $table_mapping = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event')->getTableMapping();
    $table = $table_mapping->getFieldTableName($field_name);

    return $table;
  }



  public static function getCapacity($uids) {
    $connection = \Drupal::database();
    $query = $connection->select('user__field_capacity', 'capacity');
    $query->condition('field_capacity_value', $uids, 'IN');
    $query->fields('capacity', ['entity_id', 'field_capacity_value']);
    $result = $query->execute()->fetchAllKeyed();

    return $result;
  }

  public static function getCoachesData($query = []) {

    $range = [];
    if ($query['page'] === 0) {
      $range = [0, 25];
    }

    if (!empty($query['page'])) {
      $start = $range[1] * ($query['page']) + 1;
      $end = $start + 24;
      $range = [$start, $end];
    }
    $coaches = self::getCoaches($range);
    //ksm($coaches);
    // $uids = array_keys($coaches);
    // $participants = self::countParticipants($uids);
    // $capacity = self::getCapacity($uids);
    // $scheduled = self::getCoachesData($uids, ['events' => 1]);
    // $hours = self::getHours($uids);
    // $data = [];
    // foreach ($coaches as $uid => $name) {
    //   $data[$uid]['name'] = $name;
    //   $data[$uid]['clients'] = !empty($participants[$uid]) ? $participants[$uid] : 0;
    //   $data[$uid]['capacity'] = !empty($capacity[$uid]) ? $capacity[$uid] : 0;
    //   $data[$uid]['availability'] = $data[$uid]['capacity'] - $data[$uid]['clients'];
    //   $activation = !empty($scheduled[$uid]) ? $scheduled[$uid] : 0;
    //   $velocity = !empty($hours[$uid]) ? $hours[$uid] : 0;
    //   $data[$uid]['activation'] = $clients === 0 ? 0 : $activation/$clients;
    //   $data[$uid]['velocity'] = $clients === 0 ? 0 : $velocity/$clients;
    // }
    $data = [];
    return $data;
  }


  public static function countParticipants($uids, $params = []) {

    $event_members_table = self::getTableName('field_calendar_event_members');
    $connection = \Drupal::database();

    $countQuery = $connection->select('user__field_primary_coach', 'pc');
    $countQuery->condition('pc.field_primary_coach_target_id', $uids, 'IN');
    if (!empty($params['events'])) {
      $countQuery->leftjoin($event_members_table, 'members', 'pc.entity_id = members.field_calendar_event_members_target_id');
      $countQuery->condition('members.entity_id', NULL, 'IS NOT NULL');
    }
    $countQuery->fields('pc', ['field_primary_coach_target_id']);
    $countQuery->addExpression('COUNT(DISTINCT pc.entity_id)', 'clients');
    $countQuery->groupBy('pc.field_primary_coach_target_id');
    $countResult = $countQuery->execute();

    $result = [];
    foreach ($countResult as $row) {
      $result[$row->field_primary_coach_target_id] = $row->clients;
    }

    return $result;
  }

  public static function getHours($uids) {
    $params = [
      'type' => ['calendar_hero_event'],
      'field_calendar_event_members' => $uids,
    ];

    $timezone = new \DateTimezone('GMT');
    $last_date = new \DateTime('- 28 days', $timezone);
    $last_date = $last_date->getTimestamp();

    $timestamp = time();
    $current_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);

    $event_ids = DataStorage::getEntitiesByParams('opigno_calendar_event', $params);
    $table = self::getTableName('field_calendar_event_members');
    $connection = \Drupal::database();
    $query = $connection->select('opigno_calendar_event__date_daterange', 'dates');
    $query->leftjoin($table, 'members', 'members.entity_id = dates.entity_id');
    $query->condition('members.field_calendar_event_members_target_id', $uids, 'IN');
    $query->condition('dates.entity_id', $event_ids, 'IN');
    $query->where("TIMESTAMPDIFF(DAY, dates.date_daterange_end_value, '2021-10-28T15:48:23') >= 28");
    //$query->addExpression('TIMESTAMPDIFF(MINUTE, date_daterange_value, date_daterange_end_value)', 'diff');
    $query->fields('members', ['field_calendar_event_members_target_id']);
    $query->addExpression('SUM(TIMESTAMPDIFF(MINUTE, date_daterange_value, date_daterange_end_value))', 'hsum');
    $query->groupBy('members.field_calendar_event_members_target_id');
    //$query->fields('dates', ['date_daterange_value', 'date_daterange_end_value']);

    $results = $query->execute()->fetchAllKeyed();

    return $results;
  }

  public static function getCoaches($range = []) {
    $params = [
      'roles' => ['egl_consultant'],
      //'field_calendar_hero_token' => 'IS NOT NULL'
    ];

    $uids = DataStorage::getEntitiesByParams('user', $params, $range);
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids);
    $coaches = [];
    foreach ($users as $user) {
      $coaches[$user->id()] = $user->getDisplayName();
    }
    asort($coaches);

    return $coaches;
  }

  // public static function getCalendarHeroUsers() {
  //   $connection = \Drupal::database();
  //   $query = $connection->select('meetings_report_data_alter');
  //   $query->condition('uid', 0, '!=');
  //   $query->groupBy('uid');
  //   $query->groupBy('coach');
  //   $query->fields('meetings_report_data_alter', ['uid', 'coach', 'client_email', 'client_name']);
  //   $uids = $query->execute()->fetchAll();

  //   return $uids;
  // }

  public static function getUserTotalHours($uid, $date = '') {
    $total_hours = 0;
    $package_name = '';
    $start_end[] = [
     'value' => 0,
     'end_value' => 0,
    ];
    $info = [];
    $packs = [];
    $date_check = $date;
    if (!empty($date)) {
      $timezone = new \DateTimezone('GMT');
      $date_obj = new \DateTime($date, $timezone);
      $date_check = $date_obj->getTimestamp();
    }
    $user_obj = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    if ($user_obj->hasField('field_coaching_package')) {
      $coaching_package = $user_obj->field_coaching_package->referencedEntities();
      if (!empty($coaching_package)) {
        foreach ($coaching_package as $pack) {
        //$pack = reset($coaching_package);
          $moduleHandler = \Drupal::service('module_handler');
          if ($moduleHandler->moduleExists('coaching_package')) {
            if (coaching_package_is_active($pack, $date_check)) {

              $package_name_arr[$pack->id()] = $pack->label();
              if ($pack->hasField('field_total_hours')) {
                $hours_value = $pack->field_total_hours->getValue();
                if (!empty($hours_value)) {
                  $total_hours_arr[$pack->id()] = $hours_value[0]['value'];
                }
              }
              if ($pack->hasField('field_start_end')) {

                $start_end[$pack->id()] = $pack->field_start_end->getValue();
                $packs[$pack->id()] = $start_end[$pack->id()][0]['value'];
              }
            }
          }
        }
      }
    }
    // If user has packs with time overlapped we take the pack wich started early.

    if (!empty($packs)) {
      asort($packs);
      $key = key($packs);
      $total_hours = $total_hours_arr[$key];
      $start_end = $start_end[$key];
      $package_name = $package_name_arr[$key];
    }

    return [
      'total_hours' => $total_hours,
      'package_name' => $package_name,
      'start' => $start_end[0]['value'],
      'end' => $start_end[0]['end_value'],
    ];
  }

  public static function convertFromScreenToGmdate($date) {
    $timezone = self::getTimeZone();
    $timeobj = new \DateTime($date, new \DateTimezone($timezone));
    $timestamp = $timeobj->getTimestamp();
    $gm = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);

    return $gm;
  }

}
