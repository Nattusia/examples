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

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class CancelEventForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_calncel_event_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, CalendarEventInterface $opigno_calendar_event = NULL) {
    //$opigno_calendar_event = \Drupal::entityTypeManager()->getStorage('opigno_calendar_event')->load($event);


    $event_time = $opigno_calendar_event->date_daterange->getValue();
    $event_start = new \DateTime($event_time[0]['value'], new \DateTimeZone('GMT'));
    $user_timezone = Helper::getTimezone();
    $event_start->setTimezone(new \DateTimeZone($user_timezone));
    $form['#event'] = $opigno_calendar_event;

    $owner = \Drupal::entityTypeManager()->getStorage('user')->load($opigno_calendar_event->getOwnerId());
    $form['#coach'] = $owner;

    $form['message']['#markup'] = 'Are you sure you want to cancel this event? <br>' .
      '<b>' . ucfirst($opigno_calendar_event->label()) . '</b>. The starting time at <b>' . $event_start->format('Y-M-d H:i') . '</b>';
    $form['actions']['#type'] = 'container';
    $redirect_params = $this->getRedirectParams($opigno_calendar_event);
    $cancel_url = Url::fromRoute(
      $redirect_params['route'],
      $redirect_params['params'],
      $redirect_params['options']);
    $link = Link::fromTextAndUrl('Do not Cancel', $cancel_url)->toRenderable();
    $form['actions']['cancel'] = $link;
    $form['actions']['cancel']['#attributes']['class'] = ['btn', 'btn-warning'];

    $form['actions']['submit'] = [
      '#type' => "submit",
      '#value' => 'Yes, cancel the event',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {}
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $event = $form['#event'];
    if ($event->hasField('field_calendar_hero_id')) {
      $ch_id = $event->field_calendar_hero_id->getValue();
      if ((!empty($ch_id)) && (!empty($ch_id[0]['value']))) {
        $common = new Common();
        $common->cancelMeeting($form['#coach'], $ch_id[0]['value']);
      }
      else {
        $connection = \Drupal::database();
        $query = $connection->update('calendar_hero_meetings');
        $query->condition('meeting_id', $event->id());
        $query->fields(['status' => 'cancelled']);
        $query->execute();
      }
    }

    $event->setUnpublished();
    $event->save();
    \Drupal::messenger()->addStatus('Event ' . $event->label() . 'has been canceled.');
    $redirect_params = $this->getRedirectParams($event);
    $form_state->setRedirect(
      $redirect_params['route'],
      $redirect_params['params'],
      $redirect_params['options'],
    );

  }

  public function getRedirectParams($opigno_calendar_event) {

    $getQuery = \Drupal::request()->query->all();
    $current_user = \Drupal::currentUser();
    $params = [];
    //view.opigno_calendar.page_month
    $route_name = 'calendar_hero_integration.confirm_schedule';
    if (!$current_user->isAnonymous()) {
      $roles = $current_user->getRoles();
      $params = [
        'coach' => $opigno_calendar_event->getOwnerId(),
      ];
      $route_name = 'envision_crm.coach_dashboard';
      if (in_array('egl_consultant', $roles)) {
        $route_name = 'view.opigno_calendar.page_month';
      }
    }

    return [
      'route' => $route_name,
      'params' => $params,
      'options' => ['query' => $getQuery],
    ];
  }
}
