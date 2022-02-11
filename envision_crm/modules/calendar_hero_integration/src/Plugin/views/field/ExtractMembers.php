<?php
 namespace Drupal\calendar_hero_integration\Plugin\views\field;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\coaching_package\Helper;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Field handler to show if assessment was completed.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("extract_members")
 */
class ExtractMembers extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['role'] = ['default' => ''];
    $options['user_field'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['role'] = [
      '#type' => 'select',
      '#options' => calendar_hero_integration_get_site_roles(),
      '#title' => $this->t('Member role'),
      '#empty_option' => 'Select',
      '#default_value' => $this->options['role'],
    ];

    $form['user_field'] = [
      '#type' => 'select',
      '#options' => $this->getUserFields(),
      '#title' => $this->t('The field to display'),
      '#empty_option' => "Select",
      '#required' => TRUE,
      '#default_value' => $this->options['user_field'] ? $this->options['user_field'] : '',
    ];
  }

  public function getUserFields() {
    $entity_type_id = 'user';
    $bundle = 'user';
    $fields = [];
    foreach (\Drupal::entityManager()->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {
        $fields[$field_name] = $field_definition->getLabel();
      }
    }

    foreach (\Drupal::entityManager()->getBaseFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {

        $fields[$field_name] = $field_definition->getLabel();

    }

    return $fields;
  }

  /**
   * @{inheritdoc}
   */
  public function query() {
  }

  //   /**
  //  * {@inheritdoc}
  //  */
  // public function getValue(ResultRow $values, $field = NULL) {
  //   return 1;
  // }


  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {

    $entity = $values->_entity;
    $users = $entity->field_calendar_event_members->referencedEntities();
    $roles_to_get = [$this->options['role']];
    $roles_to_exclude = in_array('egl_consultant', $roles_to_get) ? [] : ['egl_consultant'];
    $val = '';
    foreach ($users as $user) {
      $roles = $user->getRoles();
      //ksm($roles);
      if (array_intersect($roles_to_get, $roles)) {
        if (!array_intersect($roles_to_exclude, $roles)) {
          $field_to_get = $this->options['user_field'];
          //ksm(get_class_methods($user->{$field_to_get}));
          $val = $user->$field_to_get->getString();
        }
      }
    }
    return [
      '#markup' => $val,
    ];
   // ksm($this->options);
   // $output['#markup'] = 'We are going to show something';

    //$output['#markup'] = 'There is no hours information';
    // $args = $this->args;
    // if ($args) {
    //   $entities = $values->_relationship_entities;

    //   $active = FALSE;
    //   if (isset($entities['field_coaching_package'])) {
    //     $package = $entities['field_coaching_package'];
    //     //$coach_value = $package->field_coach->getValue();
    //     $package_record = [
    //       'coach_id' => NULL,
    //       'field_start_end_value' => NULL,
    //       'field_start_end_end_value' => NULL,
    //       'field_total_hours_value' => NULL,
    //     ];

    //     $package_record['coach_id'] = $args[0];

    //     $start_end = $package->field_start_end->getValue();
    //     if ($start_end) {
    //       $package_record['field_start_end_value'] = $start_end[0]['value'];
    //       $package_record['field_start_end_end_value'] = $start_end[0]['end_value'];
    //     }
    //     $total_hours = $package->field_total_hours->getValue();
    //     if ($total_hours) {
    //       $package_record['field_total_hours_value'] = $total_hours[0]['value'];

    //       $pack_obj = (object)$package_record;
    //       $used_hours = Helper::getEventsAndTime($pack_obj, $values->uid, FALSE, FALSE);
    //       $upcoming_hours = Helper::getEventsAndTime($pack_obj, $values->uid, FALSE, TRUE);
    //       $remain_hours = Helper::getEventsAndTime($pack_obj, $values->uid, TRUE, FALSE);
    //       $final = $used_hours;
    //       if (($used_hours) && ($upcoming_hours)) {

    //         $final = $used_hours . '(' . $upcoming_hours . ' upcoming)';
    //       }
    //       $content = [
    //         'used' => $used_hours,
    //         'total' => $total_hours[0]['value'],
    //         'time' => $remain_hours,
    //         'upcoming' => $upcoming_hours,
    //       ];

    //       $output = [
    //         '#theme' => 'coaching_package_hours_summary',
    //         '#content' => $content,
    //       ];
    //     }
    //   }
    // }

    //return $output;
  }
}
