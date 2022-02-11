<?php

namespace Drupal\coaching_package\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Returns responses for Envision CRM routes.
 */
class CoachingPackageController extends ControllerBase {

  public function myPackages($coach) {
    $view = \Drupal::entityTypeManager()
      ->getStorage('view')
      ->load('my_coaching_packages');
    $view = $view->getExecutable();
    $view->setArguments([$coach]);
    $view->execute();

    return $view->render();
  }

  /**
   * Checks access.
   *
   * @param AccountInterface $account
   *
   * @return AccessResult
   */
  public function accessGeneral(AccountInterface $account) {
    $access = FALSE;
    $roles = ['egl_consultant'];
    $account_roles = $account->getRoles();
    if (array_intersect($roles, $account_roles)) {
      $access = TRUE;
    }
    return AccessResult::AllowedIf(($access) ||
     ($account->hasPermission('administer users')));
  }

  /**
   * Checks access to coach Edit coaching package page.
   *
   * @param AccountInterface $account
   * @param int $coach
   * @param int $user
   *
   * @return AccessResult
   */
  public function coachAccesstoPackage(AccountInterface $account, $coach, $coaching_package) {
    $access = FALSE;
    $roles = ['egl_consultant'];
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($account->id());
    $account_roles = $user->getRoles();
    if (array_intersect($roles, $account_roles)) {
      if ($account->id() == $coach) {
        if ($package = \Drupal::entityTypeManager()->getStorage('coaching_package')->load($coaching_package)) {
          $owner_id = $package->getOwnerId();
          $access = $owner_id == $coach;
        }
      }
    }
    return AccessResult::AllowedIf(($access) ||
     ($account->hasPermission('administer users')));
  }
}
