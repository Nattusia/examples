<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\calendar_hero_integration\Common;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\calendar_hero_integration\Form\BaseSchedulingForm;
use Drupal\calendar_hero_integration\Helper;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class CoachSchedulingForm extends BaseSchedulingForm {  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $store;

  /**
   * Constructs a \Drupal\demo\Form\Multistep\MultistepFormBase.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;

    $this->store = $this->tempStoreFactory->get('coach_scheduling');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('session_manager'),
      $container->get('current_user')
    );
  }


  /**
   * Saves the data from the multistep form.
   */
  protected function saveData() {
    // Logic for saving data goes here...
    $this->deleteStore();
  }

  /**
   * Helper method that removes all the keys from the store collection used for
   * the multistep form.
   */
  protected function deleteStore() {
    $keys = ['client', 'template'];
    foreach ($keys as $key) {
      $this->store->delete($key);
    }
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_coach_scheduling_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $template = NULL, $ids = [], $event = NULL, $coach = NULL) {

    $coach_obj = \Drupal::entityTypeManager()->getStorage('user')->load($coach);
    $common = new Common();
    $build = $common->checkConnection($coach_obj);
    if ($build !== TRUE) {
      return $build;
    }

    $request = \Drupal::request();
    $query = $request->query->all();
    $common = new Common();

    $event = $common->getEventToReschedule($query);
    // $form['#prefix'] = '<div id ="scheduling-wrapper">';
    // $form['#suffix'] = '</div>';
    if (!$this->store->get('template')) {

      $form['template'] = [
        '#title' => $this->t('Meeting type'),
        '#type' => 'select',
        '#options' => calendar_hero_integration_get_meeting_types($coach_obj),
        '#default_value' => $form_state->getValue('template'),
        '#empty_value' => 'Select the meeting type',
        '#required' => TRUE,
        '#description' => 'New meeting types can be created in the Calendar Hero account',
        '#weight' => -20,
      ];
    }
    else {
      $template = $this->store->get('template');
    }

    if (!$this->store->get('client')) {
      $form['client'] = [
        '#title' => $this->t('Select the client'),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => $form_state->getValue('client'),
        //'#autocomplete_path' => '/ajax/' . $coach . '/clients-autocomplete',
        '#autocomplete_route_name' => 'calendar_hero_integration.client_autocomplete',
        '#autocomplete_route_parameters' => [
          'coach' => $coach,
        ],
      ];

      $form['actions'] = [
        '#type' => 'submit',
        '#value' => $this->t('Continue'),
        '#op' => 'step1',
      ];
    }
    else {
      $client = $this->store->get('client');
    }

    if ((!empty($client)) && (!empty($template))) {

      $pre_build['host_uid'] = $coach;
      $pre_build['guest_uid'] = $client;
      $form = parent::buildForm($form, $form_state, $template, $pre_build, $event);
      $form['client']['#type'] = 'hidden';
      $form['client']['#value'] = $client;

      $form['side']['info'] = [
        '#markup' => '<div class = "info-wrapper">
          <div class = "coach-info"> You are going to schedule the event <b>' . $template . '</b> with a client <b>'
          . $form['#user']->getDisplayName() . '</b>.</div>' .
          '<div class = "mail-info"> We are going to use his email <b>' .
          $form['#user']->getEmail() . '</b> for notifications</div></div>',
      ];

      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => 'Cancel scheduling',
        '#op' => 'cancel'
      ];

    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#op'] !== 'cancel') {
      $values = $form_state->getValues();
      if (!$this->store->get('client')) {
        $values['client'] = EntityAutocomplete::extractEntityIdFromAutocompleteInput($values['client']);
        $form_state->setValue('client', $values['client']);

        if ((!$values['client']) ||
          (!$clientObj = \Drupal::entityTypeManager()->getStorage('user')->load($values['client']))) {

          $form_state->setErrorByName('client', $this->t('There is no client with an id given'));
        }
      }
      else {
        parent::validateForm($form, $form_state);
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (!$this->store->get('client')) {
      $this->store->set('client', $values['client']);
      $this->store->set('template', $values['template']);
    }
    else {
      $this->deleteStore();

      if ($form_state->getTriggeringElement()['#op'] !== 'cancel') {
      //parent::submitForm($form, $form_state);
        if ($values['pattern'] == 1) {
          $values['start'] = $values['time'];
          $cycles = Helper::countCycles($values);

          $batch = [
            'title' => $this->t('Scheduling...'),
            'operations' => [],
            'finished' =>
               '\Drupal\calendar_hero_integration\Form\CoachSchedulingForm::batchFinished',
            'init_message' => $this->t('Scheduling is starting.'),
            'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
            'error_message' => $this->t('Error occurred. Failed to schedule.'),
          //'batch_redirect' => '/schedule/multiple/' . $values['template'],
          ];
          foreach ($cycles as $cycle) {
            $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\CoachSchedulingForm', 'schedule'], [$form, $values, $cycle]];
          }

          $batch['operations'][] = [
            ['\Drupal\calendar_hero_integration\Form\CoachSchedulingForm', 'sendReserveNotification'],
            [$form, $values],
          ];


          batch_set($batch);
        }
        else {
          $timezone_abbr = Helper::getTimezone();
          $user_timezone = new \DateTimeZone($timezone_abbr);
          $cycle = new \DateTime($values['time'], $user_timezone);
          $context = [];
          $reschedule = isset($form['#event']) ? $form['#event']->id() : FALSE;
          if (!$reschedule) {
            self::schedule($form, $values, $cycle, $context, FALSE);
            self::sendReserveNotification($form, $values, FALSE);
          }
          else {
            self::reschedule($form, $values, $cycle);
          }

          $form_state->setRedirect('view.opigno_calendar.page_month');
        }
      }
    }
  }

  public static function schedule($form, $values, $start_date, &$context, $is_batch = TRUE, $reschedule = FALSE) {
    $user = $form['#user'];
    $common = new Common();

    if (!$common->contactExists($user->getEmail(), $form['#coach'])) {
      $new_contact = $common->createContact($user, $form['#coach']);
    }
    $timezone_abbr = Helper::getTimezone();
    $user_timezone = new \DateTimeZone($timezone_abbr);
    //$start_date = new \DateTime($values['time'], $user_timezone);

    $start_date->setTimezone($user_timezone);
    $start_date_stamp = $start_date->getTimestamp();


    $meeting_type = $common->getMeetingTypes($form['#coach'], $values['template']);
    $interval = isset($meeting_type->meetingLength) ?
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
        $form['#coach']->getDisplayName() . ' and '  . $user->getDisplayName(),
      'contactEmails' => [
        $user->getEmail(),
      ],
    ];

    //$resp = $common->createMeeting($form['#coach'], $body);

    //if ((is_object($resp)) && (!empty($resp->id))) {
      $query_params['event_start_time'] = $body['dateStart'];
      $query_params['event_end_time'] = $body['dateEnd'];
      $query_params['uid'] = $values['coach'];
      $query_params['sender'] = $values['uid'];
      $query_params['ch_id'] = '';
      $query_params['template'] = $values['template'];
      if (!$reschedule) {
        $event_id = Helper::createEvent($query_params);
        Helper::reserveEvent($query_params, $event_id);

      }
      else {
        $query_params['event_id'] = $reschedule;
        $event_id = Helper::updateEvent($query_params);
      }

      if (!$is_batch) {
        \Drupal::messenger()->addStatus('You reserved an event id ' . $event_id . '. The meeting time is '
          . $start_date->format('D Y-m-d H:i'));
      }
      else {
        $context['results']['message'][] = 'You reserved an event id ' . $event_id . '. The meeting time is '
          . $start_date->format('D Y-m-d H:i');
        $context['results']['count'] = !isset($context['results']['count']) ?
          0 : $context['results']['count'];
        $context['results']['count']++;
      }
    //}
    // else {
    //   if (!$is_batch) {
    //     \Drupal::messenger()->addError('Something went wrong. Please contact the site administrator. The server response is: ' . $resp);
    //   }
    //   else {
    //     $context['results']['error'][] = 'Something went wrong. Please contact the site administrator. The server response is: ' . $resp . '. The meeting time we could not schedule is ' . $start_date->format('D Y-m-d H:i');
    //   }
    // }

  }

  public static function sendReserveNotification($form, $values, $is_batch = TRUE) {
    $subject = 'The coach ' . $form['#coach']->getDisplayName . ' reserved time for you.';
    $plusing =  $is_batch ? 'sessions' : 'session';
    $token = calendar_hero_integration_set_token($values['coach'], $values['uid'], $values['template']);
    $params = [
      'template' => $values['template'],
    ];
    $options['query']['token'] = $token;
    $options['absolute'] = TRUE;
    $url = Url::fromRoute('calendar_hero_integration.confirm_schedule', $params, $options);
    $link_text = $url->toString();
    $message = 'Dear client, I just reserved the time for our ' . $plusing. '. Please, use this link to check and confirm the schedule.<br>' . $link_text;
    calendar_hero_integration_send_mail($form['#user']->getEmail(), $message, $subject, 'scheduling_link');
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
      $response = new RedirectResponse('/opigno/calendar');
      $response->send();
    //}
  }
}
