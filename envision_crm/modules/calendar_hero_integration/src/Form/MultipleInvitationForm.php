<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a button-form to create new assessment or go to checkout.
 */
class MultipleInvitationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_multiple_invitation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tempstore = \Drupal::service('tempstore.private')->get('chero_integration_invite_multiple');
    $invite_users = $tempstore->get('invite_multiple');
    $coach_id = $tempstore->get('coach');
    if ((!$invite_users) || (!$coach_id)) {
      $form['message'] = [
        '#markup' => $this->t('Insufficient data to send multiple invitations.')
      ];
      return $form;
    }
    $coach = \Drupal::entityTypeManager()->getStorage('user')->load($coach_id);

    $form = [];
    $roles = ['editor', 'publisher', 'superhero'];
    $form['#user'] = $coach;
    $form['#invite_users'] = $invite_users;

    $invite_string = '<h3>You are going to send the invitation link to these users: </h3>';
    $invite_string .= '<ul>';
    foreach($invite_users as $in_user) {

      $name_vals = [
        'field_first_name' => '',
        'field_last_name' => '',
      ];
      foreach ($name_vals as $fname => $fval) {
        if ($in_user->hasField($fname)) {
          $name_value = $in_user->{$fname}->getValue();
          if ($name_value) {
            $name_vals[$fname] = $name_value[0]['value'];
          }
        }
      }
      $invite_string .= '<li>' . $in_user->getEmail() . ' ' . implode(' ', $name_vals) . '</li>';
    }
    $invite_string .= '</ul>';

    $form['client-list'] = [
      '#markup' => $invite_string,
    ];

    $config = \Drupal::config('calendar_hero_integration.settings');

    $form['template'] = [
      '#title' => $this->t('Meeting type'),
      '#type' => 'select',
      '#options' => calendar_hero_integration_get_meeting_types($coach),
      '#default_value' => $form_state->getValue('template'),
      '#empty_value' => 'Select the meeting type',
      '#required' => TRUE,
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $clients = $form['#invite_users'];


      $batch = [
        'title' => $this->t('Sending invitations...'),
        'operations' => [],
        'finished' =>
          '\Drupal\calendar_hero_integration\Form\MultipleInvitationForm::batchFinished',
        'init_message' => $this->t('Sending process is starting.'),
        'progress_message' => $this->t('Processed @current out of @total. Estimated time: @estimate.'),
        'error_message' => $this->t('Error occurred. Failed to import.'),
      ];
      foreach ($clients as $client) {

      $mailto = $client->getEmail();
      $form_state->setValue('client', $client->id());
      $message = $this->prepareText($form, $form_state);

      $batch['operations'][] = [['\Drupal\calendar_hero_integration\Form\MultipleInvitationForm', 'sendInvitation'], [$mailto, $message]];
      }

      batch_set($batch);
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

    $token = calendar_hero_integration_set_token($form['#user']->id(), $values['client'], $values['template']);
    $params = [
      'template' => $values['template'],
    ];
    $options['query']['token'] = $token;
    $options['absolute'] = TRUE;
    $url = Url::fromRoute('calendar_hero_integration.link_calendar', $params, $options);
    $link_text = $url->toString();

    if (preg_match('/\[invitation_link\]/', $values['message'])) {
      $message = preg_replace('/\[invitation_link\]/', $link_text, $values['message']);
    }
    else {
      $message = $values['message'] . "\n\r" . $link_text;
    }

    return $message;
  }

  // *
  //  * Gets the available calendar event types.
  //  *
  //  * @return array
  //  *   The array with types ids as keys and xai templates names as values.

  // public function getEventTypes($user) {
  //  return custom_xai_calendar_embed_get_event_types($user, TRUE, FALSE);
  // }

  public static function sendInvitation($mailto, $message, &$context) {
    $result = calendar_hero_integration_send_mail($mailto, $message, '', '', TRUE);
    if (!$result) {
      $context['results']['error'][] = $mailto;
    }
    else {
      if(!isset($context['results']['count'])) {
        $context['results']['count'] = 0;
      }
      $context['results']['count'] = $context['results']['count'] + 1;
    }

  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      if(isset($results['count'])) {
        \Drupal::messenger()->addMessage(t('Total messages sent') . ' : ' . $results['count'], 'status', TRUE);
      }
      if(isset($results['error'])) {
        \Drupal::messenger()->addMessage(t('Messages have not been sent to the next mails'), 'warning', TRUE);
        $error_string = '';
        foreach($results['error'] as $key => $result) {
          $error_string .= $result . '; ';
        }

        \Drupal::messenger()->addMessage($error_string, 'warning', TRUE);
      }
    }
    else {
      \Drupal::messenger()->addMessage(t('Finished with an error.'), 'error', TRUE);
        $error_string = t('There are problem sending these mails ');
        foreach($results['error'] as $key => $result) {
          $error_string .= $result . '; ';
        }

        \Drupal::messenger()->addMessage($error_string, 'warning', TRUE);
    }

    $tempstore = \Drupal::service('tempstore.private')->get('chero_integration_invite_multiple');
    $tempstore->delete('invite_multiple');
    $tempstore->delete('coach');
  }
}
