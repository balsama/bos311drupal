<?php

namespace Drupal\bos311;

class FetchResponses {

  protected array $services;
  protected Response $response;

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

  protected function gatherServices() {
    $services = $this->response->fetch('https://mayors24.cityofboston.gov/open311/v2/services.json?jurisdiction_id=boston.gov');
    $this->services = json_decode($services);
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
    $record = new Record($rawRecord);
    return $record->createRecord();
  }

  protected function storeLastRetrievedPageNumber($serviceCode, $page) {
    \Drupal::state()->set($serviceCode, $page);
  }
}
