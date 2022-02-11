<?php

/**
 * Implements hook_views_data_alter().
 */
function calendar_hero_integration_data_alter(array &$data) {
ksm(array_keys($data));
  $data['opigno_calendar_event']['extract_members'] = [
    'title' => t('Members field data'),
    'field' => [
      'title' => t('Members field data'),
      'help' => t('Shows some user data from member field'),
      'id' => 'extract_members',
    ],
  ];

  // $data['coaching_package']['hours_used'] = [
  //   'title' => t('Hours summary'),
  //   'field' => [
  //     'title' => t('Hours summary'),
  //     'help' => t('Displays total hours, used hours and remained hours'),
  //     'id' => 'hours_used',
  //   ],
  // ];

}
