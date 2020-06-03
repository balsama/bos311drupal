<?php

namespace Drupal\bos311;

class FetchResponses {

  protected array $services;
  protected Response $response;
  protected string $startingLiServiceRequestId;
  protected string $startingFiServiceRequestId;
  protected $numberToGet = 75;

  public function __construct() {
    $this->response = new Response();
    $this->gatherServices();
  }

  public function doFetch($page, $limit) {
    foreach ($this->services as $service) {
      $this->doFetchLiRecords($service, $limit);
      $this->doFetchFiRecords($service, $page, $limit);
    }
  }

  protected function doFetchLiRecords($service, $limit) {
    $this->getRecords($service->service_code, 0, 0, $limit, true);
  }

  protected function doFetchFiRecords($service, $page, $limit) {
    if (!$page) {
      $page = \Drupal::state()->get($service->service_code, 0);
    }
    if ($page === 'done') {
      return;
    }
    $this->getRecords($service->service_code, $page, $page, $limit);
    return;
  }

  public function doFetchIndividualRecords() {
    $this->doFetchIndividualRecordsLi(null);
    $this->doFetchIndividualRecordsLiSkipped();
    $this->doFetchIndividualRecordsFi(null);
  }

  protected function doFetchIndividualRecordsLi($serviceRequestId = null) {
    if ($serviceRequestId == null) {
      $serviceRequestId = $this->findLatestServiceRequestId();
    }
    $record = $this->getRecord($serviceRequestId);
    $nextServiceRequestId = $serviceRequestId - 1;
    // Check to see if #nextServiceRequestID already exists. If so, we can move on to the
    if ($nextServiceRequestId > ($this->startingLiServiceRequestId - $this->numberToGet)) {
      $this->doFetchIndividualRecordsLi($serviceRequestId - 1);
    }
    return;
  }

  protected function doFetchIndividualRecordsLiSkipped() {
    // This is for remaining LI reports before the first FI report.
  }

  protected function doFetchIndividualRecordsFi($serviceRequestId = null) {
    if ($serviceRequestId == null) {
      $serviceRequestId = $this->findStartingFiServiceRequestId();
    }
    $record = $this->getRecord($serviceRequestId);
    if ($record) {
      $this->storeLastSavedRecordId($record->uuid());
    }
    $nextServiceRequestId = $serviceRequestId - 1;
    if (($this->startingFiServiceRequestId - $nextServiceRequestId) < $this->numberToGet) {
      $this->doFetchIndividualRecordsFi($serviceRequestId - 1);
    }
  }

  protected function findStartingFiServiceRequestId() {
    // Get the latest node in case we haven't set the last-record-uuid yet
    $nid = \Drupal::entityQuery('node')
      ->condition('type','report')
      ->range(0, 1)
      ->sort('nid', 'DESC')
      ->execute();
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($nid));
    $backupStart = $node->uuid();


    $serviceRequestId = (\Drupal::state()->get('last-record-uuid', $backupStart) - 1);
    $this->startingFiServiceRequestId = $serviceRequestId;
    return $serviceRequestId;
  }

  protected function findLatestServiceRequestId() {
    $response = $this->response->fetch("https://mayors24.cityofboston.gov/open311/v2/requests.json");
    $objResponse = json_decode($response);
    $recordToUse = reset($objResponse);

    foreach ($objResponse as $record) {
      // If one of the first 50 responses is open, use that request ID as the starting point since it will be higher
      // than any recently closed ones. If not, fall back to the first record.
      if ($record->status == "open") {
        $recordToUse = $record;
        continue;
      }
    }

    $firstResponseServiceRequestId = $recordToUse->service_request_id;
    $this->startingLiServiceRequestId = $firstResponseServiceRequestId;
    return $firstResponseServiceRequestId;
  }

  protected function gatherServices() {
    $services = $this->response->fetch('https://mayors24.cityofboston.gov/open311/v2/services.json?jurisdiction_id=boston.gov');
    $this->services = json_decode($services);
  }

  /**
   * Fetches a single record and stores it as a Record node.
   */
  protected function getRecord($service_request_id) {
    $rawRecord = json_decode($this->response->fetch("https://mayors24.cityofboston.gov/open311/v2/requests.json?service_request_id=$service_request_id"));

    if ($savedRecord = $this->saveRecord($rawRecord[0])) {
      return $savedRecord;
    }
  }

  /**
   * Fetches records for the provided service until it reaches the last record or a record that is already stored.
   * @param $service
   */
  protected function getRecords($serviceCode, $page, $start, $limit, $stopOnDupe = false) {
    if ($limit) {
      if ($page > ($limit + $start)) {
        return;
      }
    }

    $records = $this->response->fetch("https://mayors24.cityofboston.gov/open311/v2/requests.json?service_code=$serviceCode&jurisdiction_id=boston.gov&page=$page");
    $records = json_decode($records);
    foreach ($records as $rawRecord) {
      $savedRecord = $this->saveRecord($rawRecord);
      if (($savedRecord === false) && $stopOnDupe) {
        return;
      }
    }
    if (!empty($records)) {
      $this->storeLastRetrievedPageNumber($serviceCode, $page);
      $this->getRecords($serviceCode, ($page + 1), $start, $limit);
    }
    else {
      \Drupal::state()->set($serviceCode, 'done');
      return;
    }
  }

  /**
   * Saves a record!!
   * @param $rawRecord
   */
  protected function saveRecord($rawRecord) {
    if (!$rawRecord) {
      return;
    }
    $record = new Record($rawRecord);
    return $record->createRecord();
  }

  protected function storeLastRetrievedPageNumber($serviceCode, $page) {
    \Drupal::state()->set($serviceCode, $page);
  }

  protected function storeLastSavedRecordId($service_request_id) {
    \Drupal::state()->set('last-record-uuid', $service_request_id);
  }
}
