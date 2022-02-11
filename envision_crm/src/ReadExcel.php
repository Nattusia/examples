<?php

namespace Drupal\envision_crm;

//use Drupal\entities_import\ImportUtilities;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Defines class to Read Excel.
 */
class ReadExcel {

  /**
   * Defines method to Read Excel.
   */
  public static function readExcelData($file_name) {
    $inputFileName = \Drupal::service('file_system')->realpath($file_name);
    $spreadsheet = IOFactory::load($inputFileName);
    $sheetData = $spreadsheet->getActiveSheet();
    $objPHPExcel = $spreadsheet->getActiveSheet()->getMergeCells();

    $rows = array();
    //$cellIterator->setIterateOnlyExistingCells(true);
    foreach ($sheetData->getRowIterator() as $row) {
      $cellIterator = $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(FALSE);
      $cells = [];
      foreach ($cellIterator as $cell) {
        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
          $cells[] = $cell->getFormattedValue();
        }
        else {
          $cells[] = trim($cell->getValue());
        }
      }
      $rows[] = $cells;
    }
    $headers = array_shift($rows);
    $items['header'] = $headers;
    // Read from second row.
    //$headers = array_shift($rows);
    //$items['fields'] = $headers;
    $results = array_map(function($x) use ($headers){
      return array_combine($headers, $x);
    }, $rows);
    $items['results'] = $results;
    return $items;
  }

  public static function writeToExcel($filename, $row, $headers = [], $sheet = 0) {
    $dir_path = 'public://reports/';
    //$file_name = 'EGL_Report_' . date('Y_m_d_H_i');
    $file_path = $dir_path . '/' . $filename . '.xlsx';
    $inputFileName = \Drupal::service('file_system')->realpath($file_path);

    if (!file_exists($file_path)) {
      if (empty($headers)) {
        $headers = [
           'year' => 'Billing Year',
           'month' => 'Billing Period',
           'client_name' => 'Client Name',
           'client_email' => 'Client Email',
           'uid' => 'Client UID',
           'field_request_id_value' => 'Request ID',
           'date' => 'Session Date',
           'field_first_name_value' => 'Coach First Name',
           'field_last_name_value' => 'Coach Last Name',
           'mail' => 'Coach Email',
           'title' => 'Meeting title',
           'type' => "Meeting type",
           'duration' => "Session Hours",
           'status' => "Meeting status",
           'meeting_id' => "Meeting ID",
           'scheduled' => "Scheduled",
           'updated' => 'Updated',
        ];
      }

      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      self::addHeaders($headers, $row, $sheet);
    }
    else {
      $spreadsheet = IOFactory::load($inputFileName);
      if ($sheet == 0) {
        $sheet = $spreadsheet->getActiveSheet();
        self::addData($row, $sheet);
      }
      else {
        $count = $spreadsheet->getSheetCount();
        if ($count <= $sheet) {
          $spreadsheet->createSheet();
          $spreadsheet->setActiveSheetIndex($sheet);
          $sheet = $spreadsheet->getActiveSheet();
          self::addHeaders($headers, $row, $sheet);
        }
        else {
          $spreadsheet->setActiveSheetIndex($sheet);
          $sheet = $spreadsheet->getActiveSheet();
          self::addData($row, $sheet);
        }
      }
    }

      $writer = new Xlsx($spreadsheet);
      $writer->save($inputFileName);
  }

  public static function addHeaders($headers, $row, &$sheet) {
    $data = [
      $headers, $row,
    ];
    $cRow = 0; $cCol = 0;
    foreach ($data as $line) {
      $cRow ++; // NEXT ROW
      $cCol = 65; // RESET COLUMN "A"
      foreach ($line as $cell) {
        $sheet->setCellValue(chr($cCol) . $cRow, $cell, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $cCol++;
      }
    }
  }

  public static function addData($row, &$sheet) {
    $cRow = $sheet->getHighestRow() + 1;
    $cCol = 65;
    foreach ($row as $cell) {
      $sheet->setCellValue(chr($cCol) . $cRow, $cell, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
      $cCol++;
    }
  }

  public static function writeToCSV($filename, $row) {
    $dir_path = 'public://reports/';
    //$file_name = 'EGL_Report_' . date('Y_m_d_H_i');
    $file_path = $dir_path . '/' . $filename . '.csv';
    if (!file_exists($file_path)) {
      $headers = [
         'year' => 'Billing Year',
         'month' => 'Billing Period',
         'client_name' => 'Client Name',
         'client_email' => 'Client Email',
         'date' => 'Session Date',
         'field_first_name_value' => 'Coach First Name',
         'field_last_name_value' => 'Coach Last Name',
         'mail' => 'Coach Email',
         'title' => 'Meeting title',
         'type' => "Meeting type",
         'duration' => "Session Hours",
         'status' => "Meeting status",
         'meeting_id' => "Meeting ID",
         'scheduled' => "Scheduled",
         'updated' => 'Updated'

      ];
      $headline = implode(';', $headers) . "\n";
      $file_system = \Drupal::service('file_system');
      $file_system->prepareDirectory($dir_path, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
      $file_system->saveData($headline, $file_path, FILE_EXISTS_REPLACE);
    }

    $row_line = implode(';', $row) . "\n";
    file_put_contents($file_path, $row_line, FILE_APPEND);
  }

}
