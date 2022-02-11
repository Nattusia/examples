<?php

namespace Drupal\coaching_package\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\coaching_package\Helper;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a 'CountingHoursBlock' block.
 *
 * @Block(
 *  id = "coaching_pachage_counting_hours_block",
 *  admin_label = @Translation("Counting hours block"),
 * )
 */
class CountingHoursBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $times = [];
    $profile_id = Helper::getProfileId();
    if (!empty($profile_id)) {
      if ($package = Helper::getUserActivePackage($profile_id)) {
        foreach ($package as $package_record) {
          $remaining_time = Helper::getEventsAndTime($package_record, $profile_id);
          $upcoming = Helper::getEventsAndTime($package_record, $profile_id, FALSE, TRUE);
          $times[$package_record->coach_id]['time'] = $remaining_time;
          $times[$package_record->coach_id]['total'] = $package_record->field_total_hours_value;
          $times[$package_record->coach_id]['start'] = $package_record->field_start_end_value;
          $times[$package_record->coach_id]['end'] = $package_record->field_start_end_end_value;
          $times[$package_record->coach_id]['upcoming'] = $upcoming;

        }
        $co_ids = array_keys($times);
        $coaches = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($co_ids);
        foreach ($coaches as $coach) {
          $times[$coach->id()]['coach'] = $coach->getDisplayName();
        }

        $build['#theme'] = 'coaching_package_counting_hours_block';
        $build['#content'] = $times;
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = $this->blockAccess($account);
    $profile_id = Helper::getProfileId();

    if (!empty($profile_id)) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($profile_id);
      if ($package = Helper::getUserActivePackage($profile_id)) {
        return parent::access($account, $return_as_object);
      }
    }
    else {
      return $access->forbidden();
    }
    return $access->forbidden();
  }

}
