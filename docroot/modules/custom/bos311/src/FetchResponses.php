<?php

namespace Drupal\bos311;

class FetchResponses
{

    private array $services;
    private Response $response;
    private string $startingLiServiceRequestId;
    private string $startingFiServiceRequestId;
    private $numberToGet = 500;
    private $recordsSaved = 0;
    private $apiRequestsMade = 0;
    private $highestLocalServiceRequestId;
    private $highestRemoteServiceRequestId;
    private $lowestLocalServiceRequestId;
    private $serviceRequestId;
    private $rawRecord;


    public function __construct()
    {
        $this->response = new Response();
        $this->highestLocalServiceRequestId = $this->findHighestLocalServiceRequestId();
        $this->lowestLocalServiceRequestId = $this->findLowestLocalServiceRequestId();
        $this->highestRemoteServiceRequestId = $this->findHighestRemoteServiceRequestId();
    }

    public function doFetchRecords()
    {
        $this->doFetchRecordsLi();
        $this->doFetchRecordsFi();
        $this->recordStatistics();
    }

    private function doFetchRecordsLi()
    {
        if (!$this->serviceRequestId) {
            $this->serviceRequestId = $this->highestLocalServiceRequestId + 1;
        }
        if ($this->serviceRequestId > $this->highestRemoteServiceRequestId) {
            unset($this->serviceRequestId);
        }

        $this->processRecord();

        $this->serviceRequestId = $this->serviceRequestId + 1;
        $this->doFetchRecordsLi();
    }

    private function doFetchRecordsFi() {
        if (!$this->serviceRequestId) {
            $this->serviceRequestId = $this->findLowestLocalServiceRequestId();
        }

        $this->processRecord();

        $this->serviceRequestId = $this->serviceRequestId - 1;
        $this->doFetchRecordsFi();
    }

    protected function fetchRawRecord() {
        $rawRecord = json_decode($this->response->fetch("https://mayors24.cityofboston.gov/open311/v2/requests.json?service_request_id=$this->serviceRequestId"));
        $this->apiRequestsMade++;
        $this->rawRecord = $rawRecord;
    }

    private function validateRawRecord() {
        // ...
    }

    private function saveRecord() {
        $record = new Record($this->rawRecord);
        if ($newRecord = $record->saveRecord()) {
            $this->recordsSaved++;
            return $newRecord;
        }
    }

    private function findHighestLocalServiceRequestId() {
        // ...
        return $highestLocalServiceRequestId;
    }

    private function findHighestRemoteServiceRequestId() {
      // ...
      return $highestRemoteServiceRequestId();
    }

    private function findLowestLocalServiceRequestId() {
        // ...
        return $lowestLocalServiceRequestId;
    }

    private function processRecord() {
        $this->fetchRawRecord();
        $this->validateRawRecord();
        $this->saveRecord();
    }

}
