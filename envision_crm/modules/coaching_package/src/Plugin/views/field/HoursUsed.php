<?php
 namespace Drupal\coaching_package\Plugin\views\field;

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
 * @ViewsField("hours_used")
 */
class HoursUsed extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->args = $view->args;
  }

  /**
   * @{inheritdoc}
   */
  public function query() {
  }


  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {

    $output['#markup'] = 'There is no hours information';
    $args = $this->args;
    if ($args) {
      $entities = $values->_relationship_entities;

      $active = FALSE;
      if (isset($entities['field_coaching_package'])) {
        $package = $entities['field_coaching_package'];
        //$coach_value = $package->field_coach->getValue();
        $package_record = [
          'coach_id' => NULL,
          'field_start_end_value' => NULL,
          'field_start_end_end_value' => NULL,
          'field_total_hours_value' => NULL,
        ];

        $package_record['coach_id'] = $args[0];

        $start_end = $package->field_start_end->getValue();
        if ($start_end) {
          $package_record['field_start_end_value'] = $start_end[0]['value'];
          $package_record['field_start_end_end_value'] = $start_end[0]['end_value'];
        }
        $total_hours = $package->field_total_hours->getValue();
        if ($total_hours) {
          $package_record['field_total_hours_value'] = $total_hours[0]['value'];

          $pack_obj = (object)$package_record;
          $used_hours = Helper::getEventsAndTime($pack_obj, $values->uid, FALSE, FALSE);
          $upcoming_hours = Helper::getEventsAndTime($pack_obj, $values->uid, FALSE, TRUE);
          $remain_hours = Helper::getEventsAndTime($pack_obj, $values->uid, TRUE, FALSE);
          $final = $used_hours;
          if (($used_hours) && ($upcoming_hours)) {

            $final = $used_hours . '(' . $upcoming_hours . ' upcoming)';
          }
          $content = [
            'used' => $used_hours,
            'total' => $total_hours[0]['value'],
            'time' => $remain_hours,
            'upcoming' => $upcoming_hours,
          ];

          $output = [
            '#theme' => 'coaching_package_hours_summary',
            '#content' => $content,
          ];
        }
      }
    }

    return $output;
  }
}
