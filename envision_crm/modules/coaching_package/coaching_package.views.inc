<?php

/**
 * Implements hook_views_data_alter().
 */
function coaching_package_views_data_alter(array &$data) {

  $data['coaching_package']['package_is_active'] = [
    'title' => t('Is the coaching package active?'),
    'field' => [
      'title' => t('Is the coaching package active?'),
      'help' => t('Shows if the start date of a package is before today and the end day is after today.'),
      'id' => 'package_is_active',
    ],
  ];

  $data['coaching_package']['hours_used'] = [
    'title' => t('Hours summary'),
    'field' => [
      'title' => t('Hours summary'),
      'help' => t('Displays total hours, used hours and remained hours'),
      'id' => 'hours_used',
    ],
  ];

}



