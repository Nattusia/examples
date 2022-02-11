<?php

namespace Drupal\envision_crm\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormStateInterface;

/**
 * Invitation link mass sending.
 *
 * @Action(
 *   id = "coach_to_user_assign_multiple",
 *   label = @Translation("Assign the coach to the selected users"),
 *   type = "user",
 *   confirm_form_route_name = "envision_crm.coach_to_user.assign_multiple"
 * )
 */
class CoachToMultipleClients extends ActionBase {

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $account->hasPermission('administer users');

    return $return_as_object ? AccessResult::AllowedIf($access) : $access;
  }


  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $tempstore = \Drupal::service('tempstore.private');
    $tempstore->get('envision_crm.coach_to_user.assign_multiple')->set('assign_multiple', $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $this->executeMultiple([$object]);
  }

}
