<?php
 namespace Drupal\coaching_package\Plugin\views\field;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\coaching_package\Helper;

/**
 * Field handler to show if assessment was completed.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("package_is_active")
 */
class PackageIsActive extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
  }


  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {

  $entities = $values->_relationship_entities;
  $active = FALSE;
  if (isset($entities['field_coaching_package'])) {
    $package = $entities['field_coaching_package'];
    //ksm($entities['reverse__coaching_package__field_client']->id());
    $active = coaching_package_is_active($package);
  }
   // $output['#markup'] = ($values->_entity->get('current_page')->value == 'send_data') ? '<b>yes</b>' : 'no';
    //$completed = ($values->_entity->id());
    $output['#value'] = $active;
    $output['#markup'] = $active ? 'Yes' : 'No';
    return $output;

  }
}
