<?php
namespace Drupal\calendar_hero_integration;

use Drupal\envision_crm\ReadExcel;

class AlternativeExport {

  public static function processRecord($record, $filename, &$context) {

    $indexes = [
       //'year' => 'Billing Year',
       //'month' => 'Billing Period',
       'client_name' => 'Client Name',
       'client_email' => 'Client Email',
       'uid' => 'Client UID',
       'field_request_id_value' => "Request id",
       'date' => 'Meeting date',
       'date_daterange_value' => 'Start Time',
       'date_daterange_end_value' => 'End Time',
       'field_first_name_value' => 'Coach First Name',
       'field_last_name_value' => 'Coach Last Name',
       'coach' => "Coach ID",
       'mail' => 'Coach Email',
       'description' => 'Meeting title',
       'duration' => "Session Hours",
       //'status' => "Meeting status",
       'meeting_id' => "Meeting ID",
       'scheduled' => "Scheduled",
       'updated' => "Updated",
    ];

    $data = [];

    foreach ($indexes as $index => $header) {

      //$data[$index] = '"' . $record->{$index} . '"';
      $f_index = $index == 'date' ? 'date_daterange_end_value' : $index;
      $data[$index] = $record->$f_index;
      $convert = [
        'date',
        'date_daterange_value',
        'date_daterange_end_value',
        'scheduled',
        'updated',
      ];
      if (in_array($index, $convert)) {
        $data[$index] = Helper::convertDateRow($record, $index, $f_index);
      }

      if ($index == 'duration') {
        $data[$index] = (int)$record->{$index}/60;
      }
    }

    //ReadExcel::writeToCSV($filename, $data);
    ReadExcel::writeToExcel($filename, $data, $indexes, 1);

    $context['results']['filename'] = $filename;

    $count = isset($context['results']['count']) ? $context['results']['count'] : 0;
    $count++;
    $context['results']['count'] = $count;
  }
}
