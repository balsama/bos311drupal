<?php

namespace Drupal\bos311;

class FetchResponses {

  protected array $services;
  protected Response $response;
  protected string $startingLiServiceRequestId;
  protected string $startingFiServiceRequestId;
  protected $numberToGet = 10;
  protected $recordsSaved = 0;
  protected $apiRequestsMade = 0;
  protected $existingReportsSkipped = 0;

  public function __construct() {
    $this->response = new Response();
  }

  public function doFetchIndividualRecords() {
    $this->doFetchIndividualRecordsLi(null);
    $this->doFetchIndividualRecordsLiSkipped();
    $this->doFetchIndividualRecordsFi(null);
    $this->recordStatistics();
  }

  protected function doFetchIndividualRecordsLi($serviceRequestId = null) {
    if ($serviceRequestId == null) {
      $serviceRequestId = $this->findLatestServiceRequestId();
      $this->startingLiServiceRequestId = $serviceRequestId;
    }

    $record = $this->getRecord($serviceRequestId);
    $nextServiceRequestId = $serviceRequestId - 1;

    $existingReport = \Drupal::service('entity.repository')->loadEntityByUuid('node', $nextServiceRequestId);
    if ($existingReport) {
      // If $nextServiceRequestID already exists. If so, we ~~can~~ should be able move on to the getting the FI ones.
      // But apparently there's a problem with that because we were missing a bunch if we did.
      $this->existingReportsSkipped++;
    }
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

    $this->storeLastSavedRecordId($serviceRequestId);
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
    if (!is_numeric($serviceRequestId)) {
      $serviceRequestId = $this->findStartingFiServiceRequestId();
    }
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
        break;
      }
    }

    $firstResponseServiceRequestId = $recordToUse->service_request_id;
    return $firstResponseServiceRequestId;
  }

  /**
   * Fetches a single record and stores it as a Record node.
   */
  protected function getRecord($service_request_id) {
    $rawRecord = json_decode($this->response->fetch("https://mayors24.cityofboston.gov/open311/v2/requests.json?service_request_id=$service_request_id"));
    $this->apiRequestsMade++;

    if (count($rawRecord)) {
      if ($savedRecord = $this->saveRecord($rawRecord[0], $service_request_id)) {
        return $savedRecord;
      }
    }
  }

  /**
   * Saves a record!!
   * @param $rawRecord
   */
  protected function saveRecord($rawRecord, $service_request_id) {
    if (!$rawRecord) {
      return;
    }
    $record = new Record($rawRecord);
    if ($newRecord = $record->createRecord()) {
      $this->recordsSaved++;
      return $newRecord;
    }
  }

  protected function storeLastSavedRecordId($service_request_id) {
    if (!is_numeric($service_request_id)) {
      $service_request_id = \Drupal::state()->get('last-record-uuid', $this->findStartingFiServiceRequestId());
    }
    \Drupal::state()->set('last-record-uuid', $service_request_id);
  }

  protected function recordStatistics() {
    $message = "API calls: $this->apiRequestsMade Records saved: $this->recordsSaved Start LI: $this->startingLiServiceRequestId Start FI: $this->startingFiServiceRequestId Existing reports skipped: $this->existingReportsSkipped";
    \Drupal::logger('Boston 311 Reports')->notice($message);
  }
}
