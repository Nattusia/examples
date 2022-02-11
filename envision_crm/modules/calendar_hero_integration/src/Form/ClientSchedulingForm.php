<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\calendar_hero_integration\Common;
use Drupal\calendar_hero_integration\Form\BaseSchedulingForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class ClientSchedulingForm extends BaseSchedulingForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_client_scheduling_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $template = NULL, $ids = [], $event = NULL) {

    $request = \Drupal::request();
    $query = $request->query->all();
    $common = new Common();
    $pre_build = $common->checkCalendarPage($query);
    if (isset($pre_build['#markup'])) {
      return $pre_build;
    }

    $event = $common->getEventToReschedule($query);
    $form = parent::buildForm($form, $form_state, $template, $pre_build, $event);

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
      $request_query = \Drupal::request()->query->all();
      $url = Url::fromRoute('calendar_hero_integration.confirm_schedule', [], ['query' => $request_query]);
      //$response = new RedirectResponse($url->toString());
      //$response->send();
      $form_state->setRedirect('calendar_hero_integration.confirm_schedule', [], ['query' => $request_query]);
  }
}
