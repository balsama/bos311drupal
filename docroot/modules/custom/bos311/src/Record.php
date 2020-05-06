<?php

namespace Drupal\bos311;

use \Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

class Record {

  protected $service_request_id;
  protected $status;
  protected $service_name;
  protected $description;
  protected $status_notes;
  protected $requested_datetime;
  protected $address;
  protected $latitude;
  protected $longitude;
  protected $media_url = null;
  protected $updated_datetime = null;

  public function __construct(
    string $service_request_id,
    string $status,
    string $service_name,
    string $description,
    string $requested_datetime,
    string $address,
    float $latitude,
    float $longitude,
    string $media_url = null,
    string $status_notes = null,
    string $updated_datetime = null
  )
  {
    $this->service_request_id = $service_request_id;
    $this->status = $status;
    $this->service_name = $service_name;
    $this->description = $description;
    $this->status_notes = $status_notes;
    $this->requested_datetime = $requested_datetime;
    $this->address = $address;
    $this->latitude = $latitude;
    $this->longitude = $longitude;
    $this->media_url = $media_url;
    $this->updated_datetime = $updated_datetime;
  }

  public function create() {
    $this->validate();
    $values = $this->gatherValues();
    $node = Node::create($values);
    $node->save();
  }

  protected function gatherValues() {
    $values = [];

    $values['type'] = 'report';
    $values['title'] = substr($this->description, 0, 120);
    $values['field_service_request_id'] = $this->service_request_id;
    $values['field_description'] = $this->description;
    $values['field_status_notes'] = $this->status_notes;
    $values['field_status'] = $this->status;
    $values['field_requested_datetime'] = $this->requested_datetime;
    $values['field_updated_datetime'] = $this->updated_datetime;
    $values['field_address'] = $this->address;
    $values['field_latitude'] = $this->latitude;
    $values['field_longitide'] = $this->longitude;
    $values['field_media_url'] = $this->media_url;
    $values['field_service_name'] = [
      'target_id' => $this->mapServiceName($this->service_name),
    ];
    $values['created'] = strtotime($this->requested_datetime);

    return $values;
  }

  private function validate() {
    $this->validateDateTime($this->requested_datetime);
    if ($this->updated_datetime) {
      $this->validateDateTime($this->updated_datetime);
    }
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

  /**
   * Validates that a string is a valid ISO 8601 date string.
   * @param $date
   * @return bool
   */
  private function validateDateTime($date) {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $date, $parts) == true) {
      $time = gmmktime($parts[4], $parts[5], $parts[6], $parts[2], $parts[3], $parts[1]);

      $input_time = strtotime($date);
      if ($input_time === false) {
        return false;
      }

      return $input_time == $time;
    }
    else {
      return false;
    }
  }

}
