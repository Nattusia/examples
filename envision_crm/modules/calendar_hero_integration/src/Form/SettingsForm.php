<?php

namespace Drupal\calendar_hero_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Calendar Hero Integration settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_hero_integration_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['calendar_hero_integration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('calendar_hero_integration.settings');
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getSiteRoles(),
      '#title' => $this->t('Enable the calendar page for the following roles'),
      '#default_value' => $config->get('roles') ? $config->get('roles') : [],
      '#required' => TRUE,
    ];

    $form['invite-message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default message to invite client to schedule page'),
      '#default_value' => $config->get('invite-message'),
      '#description' => $this->t('Allowed tokens: [invitation_link]'),
    ];

    return parent::buildForm($form, $form_state);
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
    $this->config('calendar_hero_integration.settings')
      ->set('roles', $form_state->getValue('roles'))
      ->set('invite-message', $form_state->getValue('invite-message'))
      ->save();
    parent::submitForm($form, $form_state);
  }

    /**
   * Gets the site roles excluded anonymous.
   *
   * @return array
   *   The array with roles ids in keys and values.
   */
  protected function getSiteRoles() {
    return calendar_hero_integration_get_site_roles();
  }

}
