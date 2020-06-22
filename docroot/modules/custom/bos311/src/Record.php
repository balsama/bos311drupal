<?php

namespace Drupal\bos311;

use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Entity\EntityStorageException;
use \Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

class Record
{
    private $service_request_id;
    private $status;
    private $service_name;
    private $description;
    private $status_notes;
    private $requested_datetime;
    private $address;
    private $lat;
    private $long;
    private $media_url;
    private $updated_datetime;
    private $locationData;

    private array $values;

    private $existingReport;

    private $nominatimServer = 'https://nominatim.openstreetmap.org';

    /**
     * Record constructor.
     * @param array $rawRecord
     */
    public function __construct($rawRecord, $serviceRequestId)
    {
        foreach ($rawRecord as $key => $value) {
            $this->$key = $this->cleanChars($value);
        }
        // Always use the Service Request ID that was used to fetch the record rather than the one provided by the
        // record.
        $this->service_request_id = $serviceRequestId;
        $this->validateTimestampFields();
        $this->checkForExistingReport();
        $this->fetchLocationData();
        $this->gatherValues();
    }

    public function saveRecord() {
        if ($this->existingReport) {
            $node = $this->existingReport;
            $node = $this->updateReportData($node);
        }
        else {
            $node = Node::create($this->values);
        }
        $node->save();
        return $node;
    }

    private function gatherValues() {
        if ($this->existingReport) {
            return;
        }
        $values = [];
        $values['type'] = 'report';
        $values['title'] = $this->service_name . ' at ' . $this->address;
        $values['field_service_request_id'] = $this->service_request_id;
        $values['field_description'] = $this->description;
        $values['field_status_notes'] = $this->status_notes;
        $values['field_status'] = $this->status;
        $values['field_requested_timestamp'] = $this->requested_datetime;
        $values['field_updated_timestamp'] = $this->updated_datetime;
        $values['field_address'] = $this->address;
        $values['field_latitude'] = $this->lat;
        $values['field_longitude'] = $this->long;
        $values['field_media_url'] = $this->media_url;
        $values['field_service_name'] = [
            'target_id' => $this->mapVocabTerm($this->service_name, 'service'),
        ];
        $values['field_neighborhood'] = [
            'target_id' => $this->mapVocabTerm($this->findNeighborhoodName(), 'neighborhood'),
        ];
        $values['field_zip_code'] = $this->findZip();

        $this->values = $values;
    }

    /**
     * Given an existing report, only update the fields that are likely to change.
     * @param $existingReport
     * @return mixed
     */
    private function updateReportData($existingReport) {
        $existingReport->field_status_notes = $this->cleanChars($this->status_notes);
        $existingReport->field_updated_datetime = $this->updated_datetime;
        $existingReport->field_status = $this->status;

        return $existingReport;
    }

    private function fetchLocationData() {
        if ($this->existingReport) {
            return;
        }
        $url = "$this->nominatimServer/reverse?lat=$this->lat&lon=$this->long&format=json";
        $response = Response::fetch($url);

        $this->locationData = $response;
    }

    private function checkForExistingReport() {
        $nids = \Drupal::entityQuery('node')
            ->condition('type','report')
            ->condition('field_service_request_id', $this->service_request_id)
            ->execute();
        if ($nids) {
            $node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($nids));
        }
        $this->existingReport = $node;
    }

    /**
     * Maps a string term name to vocab's taxonomy term.
     *
     * @param string $termName
     *   The name of the taxonomy term.
     * @param string $vocabName
     *   The name of the vocabulary
     *
     * @return int
     *   The ID of the vocab's taxonomy term.
     * @throws \Exception
     *   If an ambiguous term is provided.
     */
    private function mapVocabTerm($termName, $vocabName) {
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties([
                'name' => $termName,
                'vid' => $vocabName,
            ]);

        if ($term) {
            // Already exists. Return existing term's ID.
            if (count($term) > 1) {
                // @todo isn't this dangerous? What if there's more than one term in different vocabs?
                throw new \Exception("Time to make sure the term is in a specific vocab instead of just searching by name.");
            }
            return reset($term)->id();
        }

        // Doesn't exist, let's create it and return the new term's ID.
        $values = [
            'name' => $termName,
            'vid' => $vocabName,
        ];
        $term = Term::create($values);
        $term->save();
        return $term->id();
    }

    private function findZip() {
        $default = '00000';
        if (!is_object($this->locationData)) {
            return $default;
        }
        if (!is_object($this->locationData->address)) {
            return $default;
        }
        if (!property_exists($this->locationData->address, 'postcode')) {
            return $default;
        }
        return $this->locationData->address->postcode;
    }

    private function findNeighborhoodName() {
        $default = 'unknown';
        if (!is_object($this->locationData)) {
            return $default;
        }
        if (!is_object($this->locationData->address)) {
            return $default;
        }
        if (!property_exists($this->locationData->address, 'suburb')) {
            return $default;
        }
        return $this->locationData->address->suburb;
    }

    private function validateTimestampFields() {
        $timestampFields = [
            'requested_datetime',
            'updated_datetime',
        ];

        foreach ($timestampFields as $timestampField) {
            $this->$timestampField = self::formatDateTime($this->$timestampField);
        }
    }

    /**
     * Validates that a string is a valid ISO 8601 date string.
     * @param $date
     * @return bool
     */
    public static function formatDateTime($date)
    {
        $date = strtotime($date);
        if ($date === false) {
            self::formatDateTime(mktime(time()));
        }
        return $date;
    }

    private function cleanChars($string) {
        $cleanString = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $string);
        return $cleanString;
    }

}
