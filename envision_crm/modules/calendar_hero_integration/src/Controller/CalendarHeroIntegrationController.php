<?php

namespace Drupal\calendar_hero_integration\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\calendar_hero_integration\Common;
use Drupal\calendar_hero_integration\Helper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;
use Drupal\envision_crm\Controller\EnvisionCrmController;
use  Drupal\envision_crm\ReadExcel;
use Drupal\envision_crm\DataStorage;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;


/**
 * Returns responses for Calendar Hero Integration routes.
 */
class CalendarHeroIntegrationController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    //$common = new Common();
    //$url = 'https://api.calendarhero.com/meeting/6107c7094f0db20020293595';
    //$response = $common->basicRequest('GET', $url);
    //$body = $response->getBody()->getContents();
    //ksm($body);
    $build['content'] = [
      '#type' => 'item',
      '#markup' => '<div id="datetimepicker"></div>',
    ];

    $build['#attached']['library'][] = 'calendar_hero_integration/datetimepicker_init';
    return $build;
  }

  public function catch(Request $request, $coach, $webhook) {

    $data = json_decode( $request->getContent(), TRUE );
    $log_string = 'Got webhook ' . $webhook . ' event/object id ' . $data['id'] . ' coach id ' . $coach;
    \Drupal::logger('calendar_hero')->notice($log_string);

    $hooks = [
      'meeting_request_success',
      'meeting_request_cancelled',
      'meeting_rescheduled',
    ];


    if (in_array($webhook, $hooks)) {
      $start_date = new \DateTime($data['dateScheduledStart']);
      $end_date = new \DateTime($data['dateScheduledEnd']);
      $fields = [
        'meeting_id' => $data['id'],
        'coach' => $coach,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'status' => $data['status'],
      ];

      Helper::updateMeetingsDatabase($fields);
  }

    return new JsonResponse('ok');
  }

  public function getMonth($coach, $month, $template) {
    $main_data =  Helper::getDaysToExclude($coach, $month, $template);

    return new JsonResponse($main_data);
  }

  public function clientsAutocomplete(Request $request, $coach) {
    $results = [];
    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      $typed_string = Tags::explode($input);
      $typed_string = mb_strtolower(array_pop($typed_string));

      $variable = "%" . $typed_string . "%";

      //Get the organization name's created by the current user.
      $result = $this->getClients('LIKE', $variable, $coach);

      foreach ($result as $key => $value) {
        $results[] = [
          'value' => $value->getDisplayName() . ' (' . $value->id() . ')',
          'label' => $value->getDisplayName(),
          'uid' => $value->id(),
        ];
      }
    }
    return new JsonResponse($results);
  }

  public function getClients($operator, $variable, $coach) {
    $query = \Drupal::entityTypeManager()->getStorage('user');
    $query_result = $query->getQuery();

    $group = $query_result->orConditionGroup()
     ->condition('field_primary_coach', $coach)
     ->condition('field_backup_coach', $coach);
    $query_result->condition($group);
    $query_result->condition('name', $variable, $operator);

    $entity_ids = $query_result->execute();

    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($entity_ids);

    return $users;
  }

  /**
   * Checks access to page which sends invitation link.
   *
   * @param AccountInterface $account
   * @param int $user
   *
   * @return AccessResult
   */
  public function prepareLinkAccess(AccountInterface $account, $coach) {
    $access = FALSE;
    $config = \Drupal::config('calendar_hero_integration.settings');

    $roles = $config->get('roles');
    $account_roles = $account->getRoles();
    if (array_intersect($roles, $account_roles)) {
      $access = $account->id() == $coach;
    }

    return AccessResult::AllowedIf(($access) ||
     ($account->hasPermission('administer users')));
  }

  public function batchResult(Request $request, $template) {
    $messenger = \Drupal::messenger();

    $messages = $messenger->all();
    $messenger->deleteAll();
    $markup = '<div>';
    foreach ($messages as $message) {
      foreach ($message as $key => $m) {
        $message_string = is_string($m) ? $m : $m->__toString();
        $markup .= '<div class = "' . $key . '">' . $message_string . '</div>';
      }
    }
    $markup .= '</div>';
    return [
      '#markup' => $markup,
    ];
  }

  public function dashboardController($coach) {
    $common = new Common();
    $coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($coach);

    $controller = new EnvisionCrmController();
    $build = $controller->coachDashboard($coach_obj);
    if (!$common->getHeroToken($coach_obj)) {
      $build = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\CoachSetUpForm', $coach_obj);
    }

    return $build;
  }

  public function calendarConfirmation() {

    $messenger = \Drupal::messenger();

    $messages['status'] = $messenger->messagesByType('custom_status');
    $messages['warning'] = $messenger->messagesByType('custom_warning');
    $messages['error'] = $messenger->messagesByType('custom_error');
    $messenger->deleteByType('custom_status');
    $messenger->deleteByType('custom_warning');
    $messenger->deleteByType('custom_error');
    $markup = '<div>';
    if (!empty($messages)) {

      foreach ($messages as $message) {
        foreach ($message as $key => $m) {
          $message_string = is_string($m) ? $m : $m->__toString();
          $markup .= '<div class = "' . $key . '">' . $message_string . '</div>';
        }
      }
    }
    $markup .= '</div>';

    $common = new Common();
    $query = \Drupal::request()->query->all();
    $pre_build = $common->checkCalendarPage($query);
    if (isset($pre_build['#markup'])) {
      return $pre_build;
    }

    $output = [
      '#markup' => 'Something should be here, but it\'s not',
    ];
   // if ($view = \Drupal::service('entity.manager')
   //   ->getStorage('view')
   //   ->load('opigno_calendar_copy')) {
    if ($view = \Drupal::entityTypeManager()
      ->getStorage('view')
      ->load('opigno_calendar_copy')) {

      $view = $view->getExecutable();

      $view->setDisplay('page_month');
      if (!empty($query['arg'])) {
        $view->setArguments([$query['arg']]);
      }
      $view->execute();
      $events = [];
      foreach ($view->result as $view_row) {
         $entity = $view_row->_entity;

         if ($entity->hasField('field_calendar_hero_id')) {
           $ch_id_val = $entity->field_calendar_hero_id->getValue();
           if ((empty($ch_id_val)) || (empty($ch_id_val[0]['value']))) {
             $events[] = $view_row->_entity->id();
           }
         }
      }
      $output = [];
      $ouput['markup']['#markup'] = $markup;
      $output['view'] =  $view->render();
      if (!empty($events)) {
        $form = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\ConfirmScheduleForm', $events, $pre_build);
        $output['form'] = $form;
      }
    }

    return $output;
  }

  public function outputReportAlternative() {

    $values = \Drupal::request()->query->all();
    $values['pager'] = 1;
    $result = Helper::exportDataAlter($values);

    $export_form = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\CalendarHeroExportForm');
    $headers = [
       //'year' => 'Billing Year',
       //'month' => 'Billing Period',
      [ 'field' => 'num', 'data' => 'No'],
      [ 'field' => 'client_name', 'data' => 'Client Name'],
      [ 'field' => 'client_email', 'data' => 'Client Email'],
      [ 'field' => 'field_request_id_value', 'data' => 'Request_id'],
      [ 'field' => 'uid', 'data' => "Client UID"],
      [ 'field' => 'date_daterange_end_value', 'data' => 'Session Date'],
      [ 'field' => 'date_daterange_value', 'data' => "Start Date"],
      [ 'field' => 'date_daterange_end_value', 'data' => 'End Date'],
      [ 'field' => 'field_first_name_value', 'data' => 'Coach First Name'],
      [ 'field' => 'field_last_name_value', 'data' => 'Coach Last Name'],
      [ 'field' => 'mail', 'data' => 'Coach Email'],
      [ 'field' => 'description', 'data' => 'Meeting title'],
      //[ 'field' => 'type', 'data' => "Meeting type"],
      [ 'field' => 'meeting_time', 'data' => "Session Hours"],
      //[ 'field' => 'status', 'data' => "Meeting status"],
      [ 'field' => 'meeting_id', 'data' => "Meeting ID"],
      [ 'field' => 'scheduled', 'data' => "Scheduled"],
      [ 'field' => 'updated', 'data' => "Updated"],

    ];

    $rows = [];
    $convert = [
      'date_daterange_value',
      'date_daterange_end_value',
      'scheduled',
      'updated',
    ];

    foreach ($result as $num => $row) {
      // $double = Helper::getDouble($row->uid, $row->coach, $row->date_daterange_value, $row->meeting_id);
      // if (!empty($double)) {
      //   ksm($row);
      //   ksm($double);
      // }
      $newrow = [];
      $row->num = $num + 1;

      foreach ($headers as $header) {
        $index = $header['field'];
        $findex = ($index == 'date_daterange_end_value' && $header['data'] == 'Session Date') ?
          'date' : $index;
        $newrow[$findex] = [ 'field' => $index, 'data' => $row->{$index}];

        if (in_array($index, $convert)) {
          $newrow[$findex]['data'] = Helper::convertDateRow($row, $index, $index, $header);
        }
      }

      $rows[] = $newrow;
    }

    return [
      'form' => $export_form,
      'table' => [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
      ],
      'pager' => [
        '#type' => 'pager'
      ],
      '#attached' => [
        'library' => ['calendar_hero_integration/calendar_hero_integration'],
      ],
    ];
  }

  public function outputReport() {
    $timezone = Helper::getTimeZone();
    $export_form = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\CalendarHeroExportForm');
    $query = \Drupal::request()->query->all();
    $query['pager'] = 1;
    $result = Helper::exportData($query);

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

    $rows = [];
    $convert = [
      'date_daterange_end_value',
      'scheduled',
      'updated',
    ];

    foreach ($result as $ind => $row) {

      $newrow = [];
      foreach ($headers as $header) {
        $index = $header['field'];
        $newrow[$index] = [ 'field' => $index, 'data' => $row->{$index}];

        if (in_array($index, $convert)) {
          $newrow[$index]['data'] = Helper::convertDateRow($row, $index, $index);
        }
      }
      $rows[] = $newrow;

    }

    return [

      'form' => $export_form,
      'base_tab' => [
        'table' => [
          '#theme' => 'table',
          '#header' => $headers,
          '#rows' => $rows,
          '#attributes' => ['id' => 'export-report-table'],
        ],
        'pager' => [
          '#type' => 'pager'
        ],
        '#prefix' => '<div id = "export-base-tab">',
        '#suffix' => '</div>',
      ],
      '#attached' => [
        'library' => ['calendar_hero_integration/calendar_hero_integration'],
      ],
    ];
  }

  public function coachReport() {
   //$export_form = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\CoachReportForm');
   $params = [
      'roles' => ['egl_consultant'],
      //'field_calendar_hero_token' => 'IS NOT NULL'
    ];
    //$uids = DataStorage::getEntitiesByParams('user', $params);
    $query = \Drupal::request()->query->all();
    $query['page'] = !isset($query['page']) ? 0 : $query['page'];
    $data = Helper::getCoachesData($query);

    $rows = [];
    foreach ($data as $uid => $coach) {
      $rows[$uid] = [
        ['field' => 'uid', 'data' => $uid],
        ['field' => 'name', 'data' => $coach['name']],
        ['field'=> 'capacity', 'data' => $coach['capacity']],
        ['field' => 'availability', 'data' => $coach['availability']],
        ['field' => 'activation', 'data' => $coach['activation']],
        ['field' => 'velocity', 'data' => $coach['velocity']],
      ];
    }

    $headers = [
      ['field' => 'uid', 'data' => 'Coach id'],
      ['field' => 'name', 'data' => 'Coach Name'],
      ['field' => 'capacity', 'data' => "Capacity"],
      ['field' => 'availability', 'data' => "Availability"],
      ['field' => 'activation', 'data' => 'Activation'],
      ['field' => 'velocity', 'data' => 'Velocity']

    ];

    // $total = count($uids);
    // $pager_manager = \Drupal::service('pager.manager');
    // $pager = $pager_manager->createPager($total, 25, 0);

    // $current_page = $pager->getCurrentPage();

    return [
      //'form' => $export_form,
      'table' => [
        '#theme' => 'table',
        '#rows' => $rows,
        '#header' => $headers,
      ],
      // 'pager' => [
      //   '#type' => 'pager',
      // ],
    ];
  }

  public function personalReport($coach, $user) {
    $export_form = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\PersonalReportExportForm', $coach, $user);

    $headers = [
      ['field' => 'meeting_id', 'data' => "Meeting id"],
      ['field' => 'date', 'data' => "Meeting date"],
      ['field' => 'time', 'data' => "Time"],
      ['field' => 'description', 'data' => "Description"],
      ['field' => 'coach', 'data' => "Coach"],
      ['field' => 'duration', 'data' => "Duration"],
      ['field' => 'remained', 'data' => 'Remaining time'],
      //['field' => 'sum', 'data' => "Summ"],
    ];

    $values = [
      'uid' => $user,
      'coach' => $coach,
    ];
    $query = \Drupal::request()->query->all();
    if (!empty($query)) {
      $values += $query;
    }
    $result = Helper::getUserMeetings($values);
    $records = [];
    $coaches = [];
    foreach ($result as $res) {
      $coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($res->coach);
      $coaches[$res->meeting_id][] = $coach_obj->getDisplayName();
      $hours_and_package = Helper::getUserTotalHours($res->uid, $res->date_daterange_value);
      $sum = Helper::getSubSumm($user, $coach, $res->date_daterange_value, $hours_and_package);
      $total_hours = $hours_and_package['total_hours'];

      $records[$res->meeting_id] = [
        ['field' => 'meeting_id', 'data' => $res->meeting_id],
        ['field' => 'date', 'data' => Helper::convertDateRow($res, 'date', 'date_daterange_value')],
        ['field' => 'time', 'data' => Helper::convertDateRow($res, 'time', 'date_daterange_value')],
        ['field' => 'description', 'data' => $res->description],
        ['field' => 'coach', 'data' => implode(', ', $coaches[$res->meeting_id])],
        ['field' => 'duration', 'data' => $res->duration/60],
        ['field' => 'remained', 'data' => empty($sum) ?
          $total_hours - $res->duration/60 :
          $total_hours - $sum/60],
        //['field' => 'sum', 'data' => empty($sum) ? $res->duration : $sum],
      ];

    }

    return [
      'form' => $export_form,
      'table' => [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $records,
      ],
      '#attached' => [
        'library' => ['calendar_hero_integration/calendar_hero_integration'],
      ],
    ];
  }

  public function timeReport() {
    $export_form = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\TimeReportForm');

    $headers = [
      ['field' => 'meeting_id', 'data' => "Meeting id"],
      ['field' => 'date', 'data' => "Meeting date"],
      ['field' => 'time', 'data' => "Time"],
      ['field' => 'uid', 'data' => "UID"],
      ['field' => 'client_name', 'data' => 'Client Name'],
      ['field' => 'client_email', 'data' => 'Client Mail'],
      ['field' => 'description', 'data' => "Description"],
      ['field' => 'coach', 'data' => "Coach"],
      ['field' => 'duration', 'data' => "Duration"],
      ['field' => 'package', 'data' => 'Coaching Package'],
      ['field' => 'assigned', 'data' => "Hours Assigned"],
      ['field' => 'remained', 'data' => 'Remaining time'],
      //['field' => 'sum', 'data' => "Summ"],
    ];

    $values = \Drupal::request()->query->all();
    $values['pager'] = 1;
    $result = Helper::getUserMeetings($values);
    $records = [];
    $coaches = [];
    foreach ($result as $res) {
      $coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($res->coach);
      $coach_name = $coach_obj->getDisplayName();

      $hours_and_package = Helper::getUserTotalHours($res->uid, $res->date_daterange_value);
      //$key = key($hours_and_package);
      $sum = Helper::getSubSumm($res->uid, $res->coach, $res->date_daterange_value, $hours_and_package);
      $total_hours = $hours_and_package['total_hours'];
      $records[$res->meeting_id] = [
        ['field' => 'meeting_id', 'data' => $res->meeting_id],
        ['field' => 'date', 'data' => Helper::convertDateRow($res, 'date', 'date_daterange_value')],
        ['field' => 'time', 'data' => Helper::convertDateRow($res, 'time', 'date_daterange_value')],
        ['field' => 'uid', 'data' => $res->uid],
        ['field' => 'client_name', 'data' => $res->client_name],
        ['field' => 'client_email', 'data' => $res->client_email],
        ['field' => 'description', 'data' => $res->description],
        ['field' => 'coach', 'data' => $coach_name],
        ['field' => 'duration', 'data' => $res->duration/60],
        ['field' => 'package', 'data' => $hours_and_package['package_name']],
        ['field' => 'assigned', 'data' => $total_hours],
        ['field' => 'remained', 'data' => empty($sum) ?
          $total_hours - $res->duration/60 :
          $total_hours - $sum/60],
        //['field' => 'sum', 'data' => empty($sum) ? $res->duration : $sum],
      ];

    }

    return [
      'form' => $export_form,
      'table' => [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $records,
      ],
      'pager' => [
        '#type' => 'pager'
      ],
      '#attached' => [
        'library' => ['calendar_hero_integration/calendar_hero_integration'],
      ],
    ];
  }

  public function commonTimeReport() {
    $export_form = \Drupal::formBuilder()->getForm('\Drupal\calendar_hero_integration\Form\TimeReportForm', [TRUE]);
    $headers = [
      ['field' => 'uid', 'data' => "UID"],
      ['field' => 'client_name', 'data' => 'Client Name'],
      ['field' => 'client_email', 'data' => 'Client Mail'],
      ['field' => 'coach_name', 'data' => "Coach Name"],
      ['field' => 'coach_id', 'data' => 'Coach ID'],
      //['field' => 'package', 'data' => 'Coaching Package'],
      //['field' => 'assigned', 'data' => "Hours Assigned"],
      ['field' => 'remained', 'data' => 'Remaining time'],
      //['field' => 'sum', 'data' => "Summ"],
    ];

      $query = \Drupal::request()->query->all();
      // ToDo: add param uids
      $values = $query;
      $values['common'] = TRUE;
      $values['pager'] = 1;
      $reported = Helper::getUserMeetings($values);

      $rows = [];
      foreach ($reported as $entry) {
        $coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($entry->coach);

        //$coach = $user->field_primary_coach->referencedEntities();
       //  $package_info = Helper::getUserTotalHours($entry->uid);
       //  if (!empty($package_info['end'])) {
       //   $daterange = $package_info['end'];
       // }
       // else {
        $timestamp = time();
        $package_info = [];
        $daterange = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
       //  }
        if (!empty($values['to'])) {
          $daterange = Helper::convertFromScreenToGmdate($values['to']);
        }
        if (!empty($values['from'])) {
          $package_info['start'] = Helper::convertFromScreenToGmdate($values['from']);
          $package_info['end'] = $daterange;
        }
        $sum = Helper::getSubSumm($entry->uid, $entry->coach, $daterange, $package_info);
        $rows[] = [
          ['field' => 'uid', 'data' => $entry->uid],
          ['field' => 'client_name', 'data' => $entry->client_name],
          ['field' => 'client_email', 'data' => $entry->client_email],
          ['field' => 'coach_name', 'data' => $coach_obj->getDisplayName()],
          ['field' => 'coach_id', 'data' => $entry->coach],
          //['field' => 'package', 'data' => $package_info['package_name']],
          //['field' => 'assigned', 'data' => $package_info['total_hours']],
          ['field' => 'remained', 'data' => 0 - $sum/60],
        ];
      }

    return [
      'form' => $export_form,
      'table' => [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
      ],
      'pager' => [
        '#type' => 'pager'
      ],
      '#attached' => [
       'library' => ['calendar_hero_integration/calendar_hero_integration'],
      ],
    ];
  }

}
