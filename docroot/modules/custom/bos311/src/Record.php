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
    private $apiKey;

    /**
     * Record constructor.
     * @param array $rawRecord
     */
    public function __construct($rawRecord)
    {
        foreach ($rawRecord as $key => $value) {
            $this->$key = $this->cleanChars($value);
        }
        $this->setApiKey();
        $this->fetchLocationData();
        $this->gatherValues();
    }

    public function saveRecord() {

    }

    private function gatherValues() {
        $values = [];
        $values['type'] = 'report';
        $values['title'] = $this->service_name . ' at ' . $this->address;
        $values['field_service_request_id'] = $this->service_request_id;
        $values['field_description'] = $this->description;
        $values['field_status_notes'] = $this->status_notes;
        $values['field_status'] = $this->status;
        $values['field_requested_datetime'] = $this->requested_datetime;
        $values['field_updated_datetime'] = $this->updated_datetime;
        $values['field_address'] = $this->address;
        $values['field_latitude'] = $this->latitude;
        $values['field_longitude'] = $this->longitude;
        $values['field_media_url'] = $this->media_url;
        $values['field_service_name'] = [
            'target_id' => $this->mapServiceName($this->service_name),
        ];

        $values['field_zip_code'] = $this->findZip();
        $values['field_neighborhood'] = $this->findNeighborhood();
    }

    private function fetchLocationData() {
      $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$this->lat,$this->long&key=$this->apiKey";
      $response = Response::fetch($url);
      if ($response->status !== "OK") {
          $this->zip = 'unknown';
          $this->neighborhood = 'unknown';
          return;
      }
      $this->locationData = $response;
      // ...
    }

    /**
     * Maps a service name to a "service" taxonomy term.
     *
     * @param string $service_name
     *   The name of the service.
     *
     * @return int
     *   The ID of the service name taxonmy term.
     * @throws \Exception
     *   If an ambiguous term is provided.
     */
    protected function mapServiceName($service_name) {
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties([
                'name' => $service_name,
                'vid' => 'service',
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
            'name' => $service_name,
            'vid' => 'service',
        ];
        $term = Term::create($values);
        $term->save();
        return $term->id();
    }

    private function cleanChars($string) {
        $cleanString = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $string);
        return $cleanString;
    }

    private function setApiKey() {
        $apiKey = file_get_contents($_SERVER['HOME'] . '/keys/google-geolocation-api.key');
        $this->apiKey = $apiKey;
    }

}
