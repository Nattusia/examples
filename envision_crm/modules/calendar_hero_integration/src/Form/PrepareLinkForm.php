<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\calendar_hero_integration\Common;

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class PrepareLinkForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_prepare_link_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $coach = NULL) {

    $form = [];

    //$roles = ['editor', 'publisher', 'superhero'];
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($coach);

    $build = $this->checkConnection($user);
    if ($build !== TRUE) {
      return $build;
    }

    $form['#user'] = $user;

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
    $config = \Drupal::config('calendar_hero_integration.settings');

    $form['template'] = [
      '#title' => $this->t('Meeting type'),
      '#type' => 'select',
      '#options' => $this->getMeetingTypes($user),
      '#default_value' => $form_state->getValue('template'),
      '#empty_value' => 'Select the meeting type',
      '#required' => TRUE,
      '#description' => 'New meeting types can be created in the Calendar Hero account',
    ];

    $form['message'] = [
      '#title' => $this->t('The message text'),
      '#type' => 'textarea',
      '#default_value' => $form_state->getValue('message') ? $form_state->getValue('message') : $config->get('invite-message'),
      '#description' => $this->t('Allowed tokens: [invitation_link]. If you do not set it, the link will be added to the end of the message.'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send invitation'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $values['client'] = EntityAutocomplete::extractEntityIdFromAutocompleteInput($values['client']);
    $form_state->setValue('client', $values['client']);

    if ((!$values['client']) ||
      (!$clientObj = \Drupal::entityTypeManager()->getStorage('user')->load($values['client']))) {
      $form_state->setErrorByName('client', $this->t('There is no client with an id given'));
    }
    else {
      $form_state->set('client_obj', $clientObj);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $client = $form_state->get('client_obj');
    $clientObj = \Drupal::entityTypeManager()->getStorage('user')->load($values['client']);

    $mailto = $client->getEmail();
    $message = $this->prepareText($form, $form_state);

    calendar_hero_integration_send_mail($mailto, $message);
  }

  /**
   * Prepares the email invitation text. Replaces the link token with a real link.
   *
   * @param array $form
   *   The form array.
   *
   * @param FromStateInterface $form_state
   *   The form_state.
   *
   * @return string $message
   *   The message text with a link inserted.
   */
  public function prepareText($form, $form_state) {
    $values = $form_state->getValues();

    //$event_type = \Drupal::entityTypeManager()
    //  ->getStorage('opigno_calendar_event_type')->load($values['template']);

    //$event_type_settings = $event_type->getThirdPartySettings('custom_xai_calendar_embed');
    //$template_path = preg_replace('/^\//', '', $event_type_settings['xai_path']);
    $token = calendar_hero_integration_set_token($form['#user']->id(), $values['client'], $values['template']);
    $params = [
      'template' => $values['template'],
    ];
    $options['query']['token'] = $token;
    $options['absolute'] = TRUE;
    $url = Url::fromRoute('calendar_hero_integration.link_calendar', $params, $options);
    $link_text = $url->toString();
    //$link = Link::fromTextAndUrl('Schedule the meeting', $url)->toString();
    if (preg_match('/\[invitation_link\]/', $values['message'])) {
      $message = preg_replace('/\[invitation_link\]/', $link_text, $values['message']);
    }
    else {
      $message = $values['message'] . "\n\r" . $link_text;
    }

    return $message;
  }

  /**
   * Gets the available calendar event types.
   *
   * @return array
   *   The array with types ids as keys and xai templates names as values.
   */
  public function getEventTypes($user) {
    return ['meeting' => 'Meeting'];
    //return custom_xai_calendar_embed_get_event_types($user, TRUE, FALSE);
  }

  public function checkConnection($user) {
    $common = new Common();
    return $common->checkConnection($user);
  }

  public function getMeetingTypes($user) {
    return calendar_hero_integration_get_meeting_types($user);
  }
}
