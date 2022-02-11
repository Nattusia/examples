<?php

namespace Drupal\calendar_hero_integration\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormStateInterface;
use Drupal\envision_crm\Controller\EnvisionCrmController as CrmController;


/**
 * Invitation link mass sending.
 *
 * @Action(
 *   id = "bulk_invitation_link",
 *   label = @Translation("Send the invitation link to selected users"),
 *   type = "user",
 *   confirm_form_route_name = "calendar_hero_integration.multiple_invitation_form"
 * )
 */
class BulkInvitationLink extends ActionBase {

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
    $tempstore->get('chero_integration_invite_multiple')->set('invite_multiple', $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $this->executeMultiple([$object]);
  }

}
