<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\calendar_hero_integration\Common;
use Drupal\calendar_hero_integration\Helper;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
//use Drupal\Core\Entity\Element\EntityAutocomplete;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class BaseSchedulingForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_base_scheduling_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $template = NULL, $ids = [], $event = NULL) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    if ((empty($ids)) || (empty($ids['host_uid'])) || (empty($ids['guest_uid']))) {
      return [
        '#markup' => 'The event members are not defined.'
      ];
    }

    if (!empty($event)) {
      //$event_obj = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event')->load($event);
      $form['#event'] = $event;
    }

    $members = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($ids);
    $exclude_days = $this->getDaysToExclude($template, $ids['host_uid']);

    // ksm($exclude_days);

    $form['#coach'] = $members[$ids['host_uid']];
    $form['#user'] = $members[$ids['guest_uid']];

    $form['template'] = [
      '#type' => 'hidden',
      '#value' => $template,
    ];

    $form['coach'] = [
      '#type' => 'hidden',
      '#value' => $ids['host_uid'],
    ];

    $form['uid'] = [
      '#type' => 'hidden',
      '#value' => $ids['guest_uid'],
    ];

    $form['time'] = [
      '#type' => 'hidden',
    ];

    // $form['timezone'] = [
    //   '#type' => 'textfield',
    // ];

    $form['side'] = [
      '#type' => 'container',
    ];


    $form['side']['info'] = [
      '#markup' => '<div class = "info-wrapper">
        <div class = "coach-info"> You are going to schedule the event <b>' . $template . '</b> with a coach <b>'
        . $members[$ids['host_uid']]->getDisplayName() . '</b>.</div>' .
        '<div class = "mail-info"> We are going to use your email <b>' .
        $members[$ids['guest_uid']]->getEmail() . '</b> for notifications</div></div>',
    ];

    if (isset($form['#event'])) {
      $dateVal = $form['#event']->date_daterange->getValue();
      if (!empty($dateVal)) {
        $timezone = new \DateTimeZone('GMT');
        $startTime = new \DateTime($dateVal[0]['value'], $timezone);
        $user_timezone = Helper::getTimezone();
        $startTime->setTimezone(new \DateTimeZone($user_timezone));

        $exclude_days['rescheduling'] = [];
        $exclude_days['rescheduling']['defaultDate'] = $startTime->format('Y-m-d');
        $exclude_days['rescheduling']['defaultTime'] = $startTime->format('H:i');

        $form['side']['info'] = [
          '#markup' => '<div class = "info-wrapper">
            <div class = "coach-info"> You are going to <strong>reschedule</strong> the event <b>' . $template . '</b> with <b>'
            . $members[$ids['host_uid']]->getDisplayName() . '</b>. <br/>Currently set at <b>' .
            $startTime->format('Y-M-d H:i') . '</b>. <br> Please select a different time. </div>' .
            '<div class = "mail-info"> We are going to use your email <b>' .
            $members[$ids['guest_uid']]->getEmail() . '</b> for notifications</div></div>',
        ];
      }
    }

    $form['side']['pattern'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Repeat this schedulling by the pattern.'),
    ];


    $form['calendar-wrapper']['#type'] = 'container';
    $form['calendar-wrapper']['calendar'] = [
      '#markup' => '<div id = "calendars-output" class = "clearfix"><div class = "dt-wrapper"><div id = "datetimepicker"></div></div><div class = "dt-wrapper"><div id = "datetimepicker2"></div></div></div>',
    ];

    $default_date = new \DateTime($exclude_days['defaultDate']);
    $defaultTimeArr = explode(':', $exclude_days['defaultTime']);

    $default_date->setTime(intval($defaultTimeArr[0]), intval($defaultTimeArr[1]));
    $meeting_type_to_display = ucfirst($template);
    $form['calendar-wrapper']['selected-time']['#markup'] = '<div id = "selected-time">
      <div class = "start-date-desc">' . $meeting_type_to_display . ' date</div>
      <div class = "start-date-date strong">' . $default_date->format('D d M Y') . '</div>
      <div class = "start-desc">' . $meeting_type_to_display . ' time</div>
      <div class = "start-time strong">
      <span>from </span><span>' . $default_date->format('H : i') . '</span>
      <span> to </span><span>' .
        $default_date->modify('+' . $exclude_days['interval'] . 'seconds')
          ->format('H : i') . '</span></div>
    </div>';

/*********** pattern scheduling *******/

   $form['psch'] = [
      '#type' => 'fieldset',
      '#states' => [
        'visible' => [
          'input[name="pattern"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['psch']['period'] = [
      '#type' => 'select',
      '#title' => $this->t('Repeating'),
      '#options' => [
        //'day' => $this->t('Daily'),
        'week' => $this->t('Weekly'),
        //'month' => $this->t('Monthly'),
      ],
      '#default_value' => !empty($form_state->getValue('period')) ?
        $form_state->getValue('period') : 'week',
    ];

    $form['psch']['qty'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Every'),
      '#field_suffix' => 'week(s)',
      '#default_value' => !empty($form_state->getValue('qty')) ?
        $form_state->getValue('qty') : 1,
      '#states' => [
        'required' => [
          'input[name="pattern"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $timestamp = strtotime('next Sunday');
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $day = strftime('%a', $timestamp);
        $days[$day] = $day;
        $timestamp = strtotime('+1 day', $timestamp);
    }

    $form['psch']['days'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('on'),
      '#options' => $days,
      '#states' => [
        'required' => [
          'input[name="pattern"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['psch']['end'] = [
      '#type' => 'hidden',
    ];

    $form['psch']['skip-not'] = [
      '#title' => $this->t('If some date/time in the future is scheduled already'),
      '#type' => 'select',
      '#options' => [
        'Do not schedule this particular date and let me know',
        //'Move the meeting a hour ahead for this particular date',
        //'Move the meeting a hour behind for this particular date',
      ],
      '#default_value' => !empty($form_state->getValue('skip-not')) ?
        $form_state->getValue('skip-not') : 0,
    ];

    $form['actions']['#type'] = 'container';
    if (!isset($form['#event'])) {
      $form['actions']['recursive'] = [
        '#type' => 'button',
        '#value' => $this->t('Set recurring schedule'),
        '#attributes' => [
          'class' => ['btn-success'],
        ],
      ];
    }
    else {
      $token = \Drupal::request()->query->get('token');
      $options['query']['token'] = $token;
      $cancelUrl = Url::fromRoute('calendar_hero_integration.confirm_schedule', [], $options);
      $cancelLink = Link::fromTextAndUrl('Cancel rescheduling', $cancelUrl)->toRenderable();

      $cancelLink['#attributes']['class'][] = 'btn';
      $cancelLink['#attributes']['class'][] = 'btn-warning';
      $form['actions']['cancel-link'] = $cancelLink;

    }

    $form['actions']['single'] = [
      '#type' => 'button',
      '#value' => $this->t('Set the single meeting'),
      '#attributes' => [
        'class' => ['btn-success', 'hidden'],
      ],
      '#op' => 'single',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm'),
      '#op' => 'submit',
    ];

    $form['#attached']['drupalSettings']['datetimepicker'] = $exclude_days;
    $form['#attached']['drupalSettings']['datetimepicker']['template'] = $template;
    $form['#attached']['drupalSettings']['datetimepicker']['coach'] = $ids['host_uid'];
    $form['#attached']['library'][] = 'calendar_hero_integration/datetimepicker_init';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!isset($form['#coach'])) {
      $form_state->setErrorByName('time', 'Coach is undefined');
    }

    if ((isset($form['#event'])) && ($form_state->getValue('pattern') == 1)) {
      $form_state->setErrorByName('pattern', 'Rescheduling can\'t be done by pattern. We reschedule the particular meeting only');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($values['pattern'] == 1) {
    $values['start'] = $values['time'];
    $cycles = Helper::countCycles($values);

      $batch = [
        'title' => $this->t('Scheduling...'),
        'operations' => [],
        'finished' =>
          '\Drupal\calendar_hero_integration\Form\BaseSchedulingForm::batchFinished',
        'init_message' => $this->t('Scheduling is starting.'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to schedule.'),
        //'batch_redirect' => '/schedule/multiple/' . $values['template'],
      ];
      foreach ($cycles as $cycle) {
        $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\BaseSchedulingForm', 'schedule'], [$form, $values, $cycle]];
      }

      batch_set($batch);
    }
    else {
      $timezone_abbr = Helper::getTimezone();
      //$timezone_abbr = $values['timezone'];
      $user_timezone = new \DateTimeZone($timezone_abbr);
      $cycle = new \DateTime($values['time'], $user_timezone);
      $context = [];
      $reschedule = isset($form['#event']) ? $form['#event']->id() : FALSE;
      if (!$reschedule) {
        self::schedule($form, $values, $cycle, $context, FALSE);
      }
      else {
        self::reschedule($form, $values, $cycle);
      }
    }
  }

  public static function reschedule($form, $values, $start_date) {
    $common = new Common();
    $meeting_id = $form['#event']->field_calendar_hero_id->getValue();
    if (!$common->cancelMeeting($form['#coach'], $meeting_id[0]['value'])) {
      \Drupal::messenger()->addNotice('It\'s look like we did not delete the previous event. The server response is ' . $resp . '. Please contact the site administrator with this event id: ' . $meeting_id[0]['value']);
    }
    $context = [];
    $event_id = $form['#event']->id();
    self::schedule($form, $values, $start_date, $context, FALSE, $event_id);
  }

  public function getDaysToExclude($template, $coach) {
    return Helper::getDaysToExclude($coach, 0, $template);
  }

  public static function schedule($form, $values, $start_date, &$context, $is_batch = TRUE, $reschedule = FALSE) {
    $user = $form['#user'];
    $common = new Common();

    if (!$common->contactExists($user->getEmail(), $form['#coach'])) {
      $new_contact = $common->createContact($user, $form['#coach']);
    }
    $timezone_abbr = Helper::getTimezone();
    //$timezone_abbr = $values['timezone'];
    $user_timezone = new \DateTimeZone($timezone_abbr);
    //$start_date = new \DateTime($values['time'], $user_timezone);

    $start_date->setTimezone($user_timezone);
    $start_date_stamp = $start_date->getTimestamp();

    $meeting_type = $common->getMeetingTypes($form['#coach'], $values['template']);
    $interval = (isset($meeting_type->meetingLength) && isset($meeting_type->meetingLength->value)) ?
      $meeting_type->meetingLength->value : 30;

    $end_date = $start_date_stamp + 60 * $interval;
    $context['results']['template'] = $values['template'];

    $result = Helper::isTimeSchedulled($form['#coach']->id(), $start_date_stamp, $end_date);

    if ($result) {
      //trying to change the time, as given time is busy
      switch($values['skip-not']) {
        case 0:
          if (!$is_batch) {
          \Drupal::messenger()->addStatus('The time ' . $start_date->format('D Y-m-d H:i') . ' is busy already. We do not schedule it.');
          }
          else {
            $context['results']['error'][] = 'The time ' . $start_date->format('D Y-m-d H:i') . ' is busy already. We do not schedule it.';
          }
          return;
        case 1:
          $start_date_original = $start_date;
          $start_date->modify('+ 1 hour');
          $values['skip-not'] = 0;
          if (!$is_batch) {
            \Drupal::messenger()->addStatus('The time ' . $start_date_original>format('D Y-m-d H:i') . ' is busy already. Trying to reschedule to ' . $start_date->format('D Y-m-d H:i'));
          }
          else {
            $context['results']['error'][] = 'The time ' . $start_date_original>format('D Y-m-d H:i') . ' is busy already. Trying to reschedule to ' . $start_date->format('D Y-m-d H:i');
          }
          return self:: schedule($form, $values, $start_date, $context, $is_batch);
        case 2:
          return;
      }
    }
    $body = [
      'dateStart' => gmdate('Y-m-d\TH:i:s', $start_date_stamp),//"2021-08-05T10:00:00.000Z",
      'dateEnd' => gmdate('Y-m-d\TH:i:s', $end_date),
      'meetingLength' => $interval,
      'type' => $values['template'],
      'subject' => $start_date->format('H:i') .
        ' ' . $values['template'] . ' with ' .
        $form['#coach']->getDisplayName(). ' and ' . $form['#user']->getDisplayName(),
      'contactEmails' => [
        $user->getEmail(),
      ],
    ];

    $resp = $common->createMeeting($form['#coach'], $body);

    if ((is_object($resp)) && (!empty($resp->id))) {
      $query_params['event_start_time'] = $body['dateStart'];
      $query_params['event_end_time'] = $body['dateEnd'];
      $query_params['uid'] = $values['coach'];
      $query_params['sender'] = $values['uid'];
      $query_params['ch_id'] = $resp->id;
      $query_params['template'] = $values['template'];
      if (!$reschedule) {
        $event_id = Helper::createEvent($query_params);
      }
      else {
        $query_params['event_id'] = $reschedule;
        $event_id = Helper::updateEvent($query_params);
      }

      if (!$is_batch) {
        \Drupal::messenger()->addStatus('You scheduled an event id ' . $event_id . '. The meeting time is '
          . $start_date->format('D Y-m-d H:i'));
      }
      else {
        $context['results']['message'][] = 'You scheduled an event id ' . $event_id . '. The meeting time is '
          . $start_date->format('D Y-m-d H:i');
        $context['results']['count'] = !isset($context['results']['count']) ?
          0 : $context['results']['count'];
        $context['results']['count']++;
      }
    }
    else {
      if (!$is_batch) {
        \Drupal::messenger()->addError('Something went wrong. Please contact the site administrator. The server response is: ' . $resp);
      }
      else {
        $context['results']['error'][] = 'Something went wrong. Please contact the site administrator. The server response is: ' . $resp . '. The meeting time we could not schedule is ' . $start_date->format('D Y-m-d H:i');
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
          \Drupal::messenger()->addMessage($message, 'custom_status', TRUE);
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

    //$template = isset($results['template']) ? $results['template'] : 'meeting';
    //if (isset($results['template'])) {
     // $url = Url::fromRoute('calendar_hero_integration.batch_result', ['template' => $template]);
     // $response = new RedirectResponse($url->toString());
     // $response->send();
    //}
  }
}
