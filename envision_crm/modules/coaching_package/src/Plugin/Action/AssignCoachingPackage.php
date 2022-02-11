<?php

namespace Drupal\coaching_package\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormStateInterface;
use Drupal\envision_crm\Controller\EnvisionCrmController as CrmController;


/**
 * Invitation link mass sending.
 *
 * @Action(
 *   id = "coaching_package_assign_multiple",
 *   label = @Translation("Assign the coaching package to the selected users"),
 *   type = "user",
 *   confirm_form_route_name = "coaching_package.assign_multiple"
 * )
 */
class AssignCoachingPackage extends ActionBase {

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    //$result = $object->access('update', $account, TRUE);
    $controller = new CrmController();

    return $controller->coachAccesstoClient($account, $account->id(), $object->id(), $return_as_object);
  }


  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $tempstore = \Drupal::service('tempstore.private');
    $tempstore->get('coaching_package_assign_multiple')->set('assign_multiple', $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $this->executeMultiple([$object]);
  }

}
