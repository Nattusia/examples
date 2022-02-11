<?php
namespace Drupal\custom_tree;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Provides common functions for custom functionality.
 */
class Common {

 /**
   * Gets entities ids which are fit by properties.
   *
   * @param string $entity_type
   *   The type of entity, e.x: node, user, commerce_product, profile etc.
   * @param array $params
   *   The array of properties or fields. Each key is property name and
   *   value is array of possible values.
   *
   * @return array
   *   Array of entity ids
   */
  public function getEntitiesByParams($entity_type, array $params, $range = []) {

    $query = \Drupal::entityTypeManager()->getStorage($entity_type);
    $query_result = $query->getQuery();

    foreach ($params as $key => $param) {
      if (is_array($param)) {
        $query_result->condition($key, $param, 'IN');
      }
      if ($param === NULL) {
        $query_result->condition($key, $param, 'IS NULL');
      }
    }
    if (!empty($range)) {
     $query_result->range($range[0], $range[1]);
    }
    $entity_ids = $query_result->execute();

    return $entity_ids;
  }

  /**
   * Gets tree elements by parent. Orders by weight.
   *
   * @param int $parent
   *   The parent nid.
   *
   * @return array
   *   Array of nids.
   */
  public function getTreeElements($parent) {
    $connection = \Drupal::database();

    $query = $connection->select('node__field_tree_parent');
    $query->condition('field_tree_parent_target_id', $parent);
    $query->orderBy('field_tree_parent_weight', 'ASC');
    $query->fields('node__field_tree_parent', ['entity_id']);
    $nids = $query->execute()->fetchCol();

    return $nids;
  }

  public function getBundles() {
    $field = \Drupal::entityTypeManager()->getStorage('field_storage_config')
      ->load('node.field_tree_parent');
    $bundles = $field->getBundles();

    return $bundles;
  }

  /**
   * Redirects user to the given url defined by its rout name.
   *
   * @param string $route_name
   *   The route machine name.
   * @param array $params
   *   The route parameters.
   * @param array $options
   *   An associative array of additional URL options like query, fragment etc.
   *   For more information look at \Drupal\Core\Url::fromUri().
   */
  public static function redirect($route_name, array $params = [], array $options = []) {
    $url = Url::fromRoute($route_name, $params, $options);
    $response = new RedirectResponse($url->toString());

    $response->send();
    exit();
  }

  public function getMaxWeight($parent) {
    $weight = 0;
    $nids = $this->getTreeElements($parent);

    if (!empty($nids)) {
      $nids = array_reverse($nids);
      $nid = reset($nids);
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $field_parent = $node->field_tree_parent->getValue();

      if ($field_parent) {
        foreach ($field_parent as $parent_value) {
          if ($parent_value['target_id'] == $parent) {
            $weight = $parent_value['weight'];
          }
        }
      }
    }

    return $weight;
  }

  public function hasChildren($nid) {
    $params = [
      'field_tree_parent' => [$nid],
    ];

    return !empty($this->getEntitiesByParams('node', $params));
  }

  public function getTreeTypes($parent_term = 0, $api = FALSE, $version = '') {
    if (is_object($parent_term)) {
      $parent_term = $parent_term->id();
    }

    if ($parent_term != 0) {

      $params = [
        'field_possible_type_parents' => [$parent_term],
      ];

      $tids = $this->getEntitiesByParams('taxonomy_term', $params);

    }
    else {
      $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $query_result = $query->getQuery();
      //$query_result->condition('field_possible_type_parents', NULL, 'IS NULL');
      $query_result->condition('field_is_tree_type', 1);
      $query_result->condition('vid', 'trees_and_children');
      $tids = $query_result->execute();
    }

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $options = [];

    foreach ($terms as $term) {
      if ($language != $term->language()->getId()) {
        if ($term->hasTranslation($language)) {
          $term = $term->getTranslation($language);
        }
      }
      if (!$api) {

        $options[$term->id()] = $term->getName();
      }
      else {

        $user = \Drupal::currentUser();
        $grps = $this->getUserMembership($user);
        $query = \Drupal::request()->query->all();
        foreach ($grps as $grp) {
          $group = $grp->getGroup();
          if ((isset($query['group'])) && ($query['group'] != $group->id())) {
            continue;
          }
          $grpType = $group->getGroupType();
          // $grpObj = $grp->getGroup();
          // $member = $grpObj->getMember($user);
          // ksm(get_class_methods($member));

          $content_type = $term->field_content_types->getValue();
          $pluginId = 'group_node:' . $content_type[0]['target_id'];
          $permission = 'create ' . $pluginId . ' entity';

          if (($grpType->hasContentPlugin($pluginId)) && ($grp->hasPermission($permission))) {
            $options[$term->id()] = $content_type[0]['target_id']; //$term->getName();
            if (!empty($version)) {
              $options[$term->id()] = [
                'type' => $content_type[0]['target_id'],
                'typeId' => $term->id(),
                'typeName' => $term->getName(),
              ];
            }
          }
        }
      }
    }

    return $options;
  }

  public function createTreeElement($type, $uid, $values, $returnObj = FALSE) {

    $weight = (int) $this->getMaxWeight($values['parent']);
    $new_node = [
      'type' => $type,
      'title' => $values['tree-title'],
      'langcode' => 'en',
      'uid' => $uid,
      'status' => 1,
      'field_tree_parent' => [
        0 => [
          'target_id' => $values['parent'],
          'weight' => $weight + 1,
        ]
      ],
      'field_tree_show_submitted' => [
        0 => [
          'value' => $values['show-avatar'],
        ],
      ],
      'field_tree_root' => [
        0 => [
          'target_id' => $values['root']
        ],
      ],

      'field_tree_type' => [
        0 => [
          'target_id' => $values['tree-type'],
        ],
      ],
    ];

    if (isset($values['responsive_for_type'])) {
      $new_node['field_responsive_for_type'] = [
        0 => [
          'target_id' => $values['responsive_for_type'],
        ],
      ];
    }

    $node = \Drupal::entityTypeManager()->getStorage('node')->create($new_node);
    $node->save();

    if (!$returnObj) {
      return $node->id();
    }

    return $node;
  }

  public function checkTitleTemplate($values) {

    if (!empty($values['title-template'])) {
      $regular = '/\[.*?\]/';
      $raw_template = preg_replace($regular, '.+', $values['title-template']);
      $template_regular = '/^' . trim($raw_template) . '$/';
      $title = isset($values['tree-title']) ?
        $values['tree-title'] : $values['title'][0]['value'];
      return preg_match($template_regular, trim($title));
    }

    return TRUE;
  }

  /**
   * Adds permissions to create and edit own content of a particular content type
   *   for a given user role.
   *
   * @param string $entity_type
   *   The entity type machine name.
   *
   * @param $role
   *   The role machine name.
   */
  public function addCreateAndEditOwnPermission($entity_type, $role = 'authenticated') {
    $createPerm = 'create ' . $entity_type . ' content';
    $editPerm = 'edit own ' . $entity_type . ' content';
    if ($role == 'editor') {
      $editPerm = 'edit any ' . $entity_type . ' content';
    }
    $roleEnt = \Drupal::entityTypeManager()->getStorage('user_role')->load($role);
    $roleEnt->grantPermission($createPerm);
    $roleEnt->grantPermission($editPerm);
    $roleEnt->save();
  }

  public function isNodeOwner($nid) {
    $owner = FALSE;
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if ($node) {
      $current_user = \Drupal::currentUser();
      $tree_owner = '';
      if ($node->hasField('field_tree_root')) {
        $root_node = $node->field_tree_root->referencedEntities();
        if ($root_node) {
          $tree_owner = $root_node[0]->getOwnerId();
        }
      }

      $user_obj = \Drupal::entityTypeManager()->getStorage('user')->load($current_user->id());
      $roles = $user_obj->getRoles();
      $owner =  ((in_array('administrator', $roles)) ||
        (in_array('editor', $roles)) ||
        ($tree_owner == $current_user->id()) ||
        ($node->getOwnerId() == $current_user->id()));
    }

    return $owner;
  }

  public function changeParents($values) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($values['nid']);

    $parents = $node->field_tree_parent->getValue();

    if ($parents) {
      foreach ($parents as $index => $parent) {
        if ($parent['target_id'] == $values['old-parent']) {
          if (!is_numeric($values['new-parent'])) {
            $new_parent_array = explode('(', $values['new-parent']);
            $new_parent = (int) end($new_parent_array);
          }
          else {
            $new_parent = $values['new-parent'];
          }
          $new_value = [
            $index => [
              'weight' => $parent['weight'],
              'target_id' => $new_parent,
            ],
          ];
          $node->field_tree_parent->setValue($new_value);
          if ($node->getType() == 'tree_of_trees') {
            $old_parent = \Drupal::entityTypeManager()->getStorage('node')->load($values['old-parent']);
            $old_term_value = $old_parent->field_responsive_for_type->getValue();
            $node_term = $node->field_responsive_for_type->referencedEntities();
            $new_parent_node = \Drupal::entityTypeManager()->getStorage('node')->load($new_parent);
            $new_parent_term_value = $new_parent_node->field_responsive_for_type->getValue();

            $term_possible_parents = $node_term[0]->field_possible_type_parents->getValue();
            foreach ($term_possible_parents as $delta => $possible_parent) {
              if ($possible_parent['target_id'] == $old_term_value[0]['target_id']) {
                $term_possible_parents[$delta]['target_id'] = $new_parent_term_value[0]['target_id'];
                break;
              }
            }
            $node_term[0]->field_possible_type_parents->setValue($term_possible_parents);
            $node_term[0]->save();
          }
          break;
        }
      }
      $node->save();
    }
  }

  public function checkTreeGroup($nid) {

    $database = \Drupal::database();
    $query = $database->select('group_content_field_data', 'gc');
    $query->condition('gc.entity_id', $nid, '=');
    $query->condition('gc.type', '%-group_node-%', 'LIKE');
    $query->range(0, 1);
    $query->fields('gc', ['gid']);
    $result = $query->execute()->fetchField();

    return $result;
  }

  public function getNidsInGroup($nids, $gids, $range = []) {
    $database = \Drupal::database();
    $query = $database->select('group_content_field_data', 'gc');
    $query->condition('gc.entity_id', $nids, 'IN');
    if ($gids) {
      $query->condition('gc.gid', $gids, 'IN');
      if (!empty($range)) {
       $query->range($range[0], $range[1]);
      }
      $query->fields('gc', ['entity_id', 'gid']);

      $result = $query->execute()->fetchAllKeyed();

      return $result;
    }

    return [];

  }

  public function getUserMembership($account) {
    $grp_membership_service = \Drupal::service('group.membership_loader');
    $grps = $grp_membership_service->loadByUser($account);

    return $grps;
  }

  public function getEventStatus($nid, $uid) {
    $connection = \Drupal::database();
    $query = $connection->select('api_event_statuses');
    $query->condition('uid', $uid);
    $query->condition('nid', $nid);
    $query->fields('api_event_statuses', ['status', 'timestamp']);
    $status_rec = $query->execute()->fetchAll();

    if (!empty($status_rec)) {
      $status = reset($status_rec);

      if (($status->status == 'start') && ($status->timestamp <= time())) {
        $this->recordEvent($uid, 'expire', $nid);

        return $this->getEventStatus($nid, $uid);
      }

      return $status->status;
    }
  }

  public function recordEvent($uid, $type, $nid) {
    $timestamp = time();
    if ($type == 'start') {
      $timestamp = time() + 7200;
    }
    if ($type == 'snooze') {
      $timestamp = time() + 300;
    }
    $connection = \Drupal::database();
    $query = $connection->merge('api_event_statuses');
    $query->keys(['uid', 'nid'], ['uid' => $uid, 'nid' => $nid]);
    $query->fields([
      'uid' => $uid,
      'nid' => $nid,
      'status' => $type,
      'timestamp' => $timestamp,
    ]);
    $query->execute();
  }

}

