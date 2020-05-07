<?php

namespace Drupal\bos311;

class FetchResponses {

  protected array $services;
  protected Response $response;

  public function __construct() {
    $this->response = new Response();
    $this->gatherServices();
  }

  public function doFetch() {
    foreach ($this->services as $service) {
      $this->getRecords($service->service_code);
    }
  }

  protected function gatherServices() {
    $services = $this->response->fetch('https://mayors24.cityofboston.gov/open311/v2/services.json?jurisdiction_id=boston.gov');
    $this->services = json_decode($services);
  }

  /**
   * Fetches records for the provided service until it reaches the last record or a record that is already stored.
   * @param $service
   */
  protected function getRecords($serviceCode, $page = 0) {
    $records = $this->response->fetch("https://mayors24.cityofboston.gov/open311/v2/requests.json?service_code=$serviceCode&jurisdiction_id=boston.gov&page=$page");
    $records = json_decode($records);
    foreach ($records as $rawRecord) {
      $this->saveRecord($rawRecord);
    }
    if (!empty($records)) {
      $this->getRecords($serviceCode, ($page + 1));
    }
  }

  /**
   * Saves a record!!
   * @param $rawRecord
   */
  protected function saveRecord($rawRecord) {
    $record = new Record($rawRecord);
    $record->createRecord();
  }

}
