<?php

namespace Drupal\envision_crm\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for Envision CRM routes.
 */
class EnvisionCrmController extends ControllerBase {

  /**
   * Checks access to coach pages.
   *
   * @param AccountInterface $account
   * @param int $coach
   *
   * @return AccessResult
   */
  public function coachAccess(AccountInterface $account, $coach) {
    $access = FALSE;
    $roles = ['egl_consultant'];
    if ($account->id() == $coach) {
      $account_roles = $account->getRoles();
      $access = !empty(array_intersect($roles, $account_roles));
    }
    else {
      if ($account->hasPermission('administer users')) {
        if ($user_coach = \Drupal::entityTypeManager()->getStorage('user')->load($coach)) {
          $coach_roles = $user_coach->getRoles();
          $access = !empty(array_intersect($roles, $coach_roles));
        }
      }
    }

    return AccessResult::AllowedIf($access);
  }

  /**
   * Checks access to coach Edit client page.
   *
   * @param AccountInterface $account
   * @param int $coach
   * @param int $user
   *
   * @return AccessResult
   */
  public function coachAccesstoClient(AccountInterface $account, $coach, $user, $return_as_object = TRUE) {
    $access = FALSE;
    $roles = ['egl_consultant'];
    $account_roles = $account->getRoles();

    if (((array_intersect($roles, $account_roles)) && ($account->id() == $coach)) ||
      ($account->hasPermission('administer users'))) {

      //if ($account->id() == $coach) {
        if ($user = \Drupal::entityTypeManager()->getStorage('user')->load($user)) {
          $field_values['primaries'] = $user->field_primary_coach->getValue();
          $field_values['backups'] = $user->field_backup_coach->getValue();

          if ($field_values['primaries'] || $field_values['backups']) {
            $targets = [];
            foreach ($field_values as $coach_vals) {
              $targets += envision_crm_clean_field_value($coach_vals, 'target_id');
            }

            if ($targets) {
              if ((in_array($coach, $targets)) ||
               ($account->hasPermission('administer users'))) {
                $access = TRUE;
              }
            }
          }
        }
      //}
    }

    return $return_as_object ? AccessResult::AllowedIf($access) : $access;
  }


  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  public function addClient() {
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

      $entityTypeManager = \Drupal::entityTypeManager();
      $new_user = $entityTypeManager->getStorage('user')->load(7);
      $form = $entityTypeManager->getFormObject('user', 'add_client')->setEntity($new_user);
      $form = \Drupal::formBuilder()->getForm($form);
      return $form;

    //return $build;
  }


  public function myClients($coach) {
    $view = \Drupal::entityTypeManager()
      ->getStorage('view')
      ->load('my_clients');
    $view = $view->getExecutable();
    $view->setArguments([$coach, $coach]);
    $view->execute();

    return $view->render();
  }

  public function coachAutocomplete(Request $request) {
    $results = [];
    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      $typed_string = Tags::explode($input);
      $typed_string = Unicode::strtolower(array_pop($typed_string));

      $variable = "%" . $typed_string . "%";
      //$variable = $typed_string;

      //Get the organization name's created by the current user.
      $result = $this->getCoaches('LIKE', $variable);

      foreach ($result as $key => $value) {
        $results[] = [
          'value' => $value->getDisplayName() . ' (' . $value->id() . ')',
          'label' => $value->getDisplayName(),
          'uid' => $value->id(),
        ];
      }
    }
    return new JsonResponse($results);
  }

  public function getCoaches($operator, $variable) {
    $query = \Drupal::entityTypeManager()->getStorage('user');
    $query_result = $query->getQuery();

    $query_result->condition('roles', 'egl_consultant');
    $group = $query_result->orConditionGroup()
     ->condition('field_first_name', $variable, $operator)
     ->condition('field_last_name', $variable, $operator)
     ->condition('name', $variable, $operator);
    $query_result->condition($group);
    $entity_ids = $query_result->execute();

    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($entity_ids);

    return $users;
  }

  public function coachDashboard($coach) {

    return ['#markup' => "Welcome to your dashboard."];
  }
}
