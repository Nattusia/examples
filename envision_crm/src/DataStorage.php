<?php

namespace Drupal\envision_crm;

/**
 * Defines class to data storage interface.
 */
class DataStorage {

  /**
   * Defines method to save data.
   */
  public static function save($result, $row, $values, &$context) {
    $flag = 0;
    $txtstatus = '';
    $fields = self::getFields();
    $user = [];
    $user_full = TRUE;
    $coach = $values['coach'];

    foreach ($result as $flabel => $val) {
      $new_key = mb_strtolower($flabel);
      $new_key = str_replace(' ', '_', $new_key);
      $result[$new_key] = $result[$flabel];
    }

    // Getting fields which linked with taxonomy terms
    foreach ($fields as $key => $field) {

      $fkey = mb_strtolower($field['label']);
      $fkey = str_replace(' ', '_', $fkey);

      if (!empty($result[$fkey])) {
        if (!isset($field['vocabularies'])) {
          $user[$key] = $result[$fkey];
        }
        else {
          $vids = array_keys($field['vocabularies']);

          $params = [
            'vid' => $vids,
            'name' => [$result[$field['label']]],
          ];

          if ($key == 'field_company_division') {

            $names = explode('/', $result[$fkey]);
            $params['name'] = [$names[0]];
            if ($parent = self::findTerm($params)) {
              $params['name'] = [$names[1]];
              $params['parent'] = [$parent];
            }
            else {
              $context['results']['error'][$row][] = "Can't find parent term with name " . $names[0];
            }
          }
          if ((!empty($params['name'])) && ($tid = self::findTerm($params))) {
            $user[$key] = $tid;
          }
          else {
            $context['results']['error'][$row][] = "Can't find term with name " . $params['name'][0];
          }
        }
      }

      if (($field['required']) && (empty($user[$key]))) {
        $context['results']['error'][$row][] = 'Field ' . $field['label'] . ' is required.';
        $user_full = FALSE;
      }
    }

    if (empty($user['mail'])) {
      //todo Point the row of user.
      $context['results']['error'][$row][] = 'User email is mandatory parameter';
    }
    elseif (!\Drupal::service('email.validator')->isValid($user['mail'])) {
      $context['results']['error'][$row][] = 'Email is not valid';
    }
    else {

      $check = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $user['mail']]);

      if (!empty($check)) {
        //$context['results']['error'][$row][] = 'User with email ' . $user['mail'] . ' already exists';
        $account = reset($check);
        //self::updateUser($user, $values);
      }
      //else {
        // if record has required values (mail), and mail is valid
        if ($user_full) {
          $user['name'] = $user['mail'];

          // fill out the coach;
          if (!empty($coach)) {
            $user['field_primary_coach'] = $coach;
            $user['field_created_by'] = $coach;
          }
          else {
            $current_user = \Drupal::currentUser();
            $user['field_created_by'] = $current_user->id();
          }
          $user['roles'] = ['ldp_membership'];

          // coaching package
          if (!empty($result['Coaching package'])) {
            $new_params['title'] = [$result['Coaching package']];
            $pids = self::getEntitiesByParams('coaching_package', $new_params);

            $pid = reset($pids);
            $package = \Drupal::entityTypeManager()->getStorage('coaching_package')->load($pid);
            if (!$package) {
                $context['results']['error'][$row][] = 'Coaching package ' . $result['Coaching package'] .
                ' does not exist.';
            }
            else {
              $user['field_coaching_package'] = $package->id();
            }
          }

          // if division and company is in the form, then we rewrite the file data
          if (!empty($values['company'])) {
            $user['field_company_division'] = $values['company'];
          }
          if (!empty($values['division'])) {
            $user['field_company_division'] = $values['division'];
          }

          // if we found the user (line 71) we just edit the account
          if (isset($account)) {
            unset($user['mail']);
            foreach ($user as $field => $fval) {
              $account->{$field}->setValue($fval);
            }
            $account->save($account, $user);
          }
          else {
            // if we did not find the user, then we create a new one
            $new_user = \Drupal::entityTypeManager()->getStorage('user')->create($user);
            $new_user->save();
          }

          // cohort (group)
          if (!empty($result['Cohort'])) {
            $cohort = self::findCohort($result['Cohort']);
            if ((!$cohort) && (empty($values['cohort']))) {
              $context['results']['error'][$row][] = "Can't find a Cohort with name " . $result['Cohort'];
            }
          }
          // if cohort is selected in the form, then we rewrite the file value
          if (!empty($values['cohort'])) {
            $cohort = $values['cohort'];
          }

          if (!empty($cohort)) {
            $userToAdd = isset($account) ? $account : $new_user;
            self::addUserToCohort($userToAdd, $cohort);
          }

          $flag = 1;
        }
      //}
    }
    if($flag == 1) {
      //$context['results'][] = $txtstatus . ' - ' . $item['title'];
      if(!isset($context['results']['count'])) {
        $context['results']['count'] = 0;
      }
      $context['results']['count'] = $context['results']['count'] + 1;
      $context['message'] = t('Created user @title', array('@title' => $result['Email']));
    }
    else {
      $context['results']['error'][$row][] = 'Failed to create - ' . $result['Email'];
    }
  }

  public static function entities_import_batch_finished($success, $results, $operations) {
    if ($success) {
      if(isset($results['count'])) {
        \Drupal::messenger()->addMessage(t('Total content created/updated') . ' : ' . $results['count'], 'status', TRUE);
      }
      if(isset($results['error'])) {
        \Drupal::messenger()->addMessage(t('Content has not been created/updated for the below Row Number.'), 'warning', TRUE);
        foreach($results['error'] as $key => $results) {
            $error_string = t('Row number') . ': ' . $key . '. ';
            foreach($results as $result) {
              //\Drupal::messenger()->addMessage(t('Row number') . ' : ' . $key .  ' - ' . $result, 'warning', TRUE);
              $error_string .= $result . '; ';
            }
            \Drupal::messenger()->addMessage($error_string, 'warning', TRUE);
        }
      }
    }
    else {
      \Drupal::messenger()->addMessage(t('Finished with an error.'), 'error', TRUE);
        foreach($results['error'] as $key => $results) {
            $error_string = t('Row number') . ': ' . $key . '. ';
            foreach($results as $result) {
              //\Drupal::messenger()->addMessage(t('Row number') . ' : ' . $key .  ' - ' . $result, 'warning', TRUE);
              $error_string .= $result . '; ';
            }
            \Drupal::messenger()->addMessage($error_string, 'warning', TRUE);
        }
    }
  }

  public static function getFields() {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user');
    $fields = [];
    foreach ($definitions as $key => $definition) {
      if ($key !== 'name') {
        $label = is_object($definition->getLabel()) ?
        $definition->getLabel()->__toString() : $definition->getLabel();
        $fields[$key]['label'] = $label;
        $fields[$key]['type'] = $definition->getType();
        $fields[$key]['required'] = $definition->isRequired();
        if ($definition->getType() == 'entity_reference') {
          $settings = $definition->getSettings();
          if ($settings['target_type'] == 'taxonomy_term') {
            $fields[$key]['vocabularies'] = $settings['handler_settings']['target_bundles'];
          }
        }
      }
    }

    return $fields;
  }

  public static function getEntitiesByParams($entity_type, array $params, $range = []) {

    $query = \Drupal::entityTypeManager()->getStorage($entity_type);
    $query_result = $query->getQuery();

    foreach ($params as $key => $param) {
      if (is_array($param)) {
        $query_result->condition($key, $param, 'IN');
      }
      if ($param === NULL) {
        $query_result->condition($key, $param, 'IS NULL');
      }
      if ($param == 'IS NOT NULL') {
        $query_result->condition($key, NULL, 'IS NOT NULL');
      }
    }
    if (!empty($range)) {
     $query_result->range($range[0], $range[1]);
    }
    $entity_ids = $query_result->execute();

    return $entity_ids;
  }

  /**
   * Tries to get terms by given params.
   *
   * @param array $params
   *  The params for the term searching.
   *
   * @return obj
   *  The term entity.
   */
  public static function findTerm($params) {
    $tids = self::getEntitiesByParams('taxonomy_term', $params);
    return reset($tids);
  }

  public static function findCohort($cohort) {
    $params = [
      'type' => ['opigno_class'],
      'label' => [$cohort],
    ];

    $gids = self::getEntitiesByParams('group', $params);

    return reset($gids);
  }

  /**
   * Gets all groups of the opigno_class types
   *
   * @return array
   *  The array with ids as keys and group names as values.
   */
  public static function getCohorts() {
    $params = [
      'type' => ['opigno_class'],
    ];
    $gids = self::getEntitiesByParams('group', $params);
    $groups = \Drupal::entityTypeManager()->getStorage('group')->loadMultiple($gids);
    $cohorts = [];
    foreach ($groups as $group) {
      $cohorts[$group->id()] = $group->label();
    }

    return $cohorts;
  }

  /**
   * Gets list of top level taxonomy terms with in given vocabulary and parent.
   *
   * @param string $vid
   *   The vocabulary id.
   * @param int $parent
   *   The parent tid.
   *
   * @return array
   *   The terms array with keys as tids and values as terms names.
   */
  public static function getOptions($vid, $parent = 0) {
    if($parent === NULL) {
      return [];
    }
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid, $parent, 1, FALSE);
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return $options;
  }

  public static function addUserToCohort($user, $cohort) {
    $group = \Drupal::entityTypeManager()->getStorage('group')->load($cohort);
    //ksm(get_class_methods($group));
    $group->addMember($user);
    $group->save();
  }
}
