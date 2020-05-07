<?php

namespace Drupal\bos311;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\EntityStorageException;
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

  /**
   * Record constructor.
   * @param array $rawRecord
   */
  public function __construct ($rawRecord)
  {
    foreach ($rawRecord as $key => $value) {
      if ($key == 'lat') {
        $key = 'latitude';
      }
      if ($key == 'long') {
        $key = 'longitude';
      }
      $this->$key = $value;
    }
  }

  /**
   * Creates a new record or passes the record off to the update method if it already exists.
   * @throws EntityStorageException
   */
  public function createRecord() {
    $values = $this->gatherValues();

    $existingReport = \Drupal::service('entity.repository')->loadEntityByUuid('node', $this->service_request_id);
    if ($existingReport) {
      return $this->updateRecord($existingReport);
    }

    $node = Node::create($values);
    $node->save();
    return $node;
  }

  /**
   * Makes limited updates to a record. This method will only update the Status Notes, Status, and Updated Date field.
   */
  protected function updateRecord($existingReport) {

    $existingReport->field_status_notes = $this->status_notes;
    $existingReport->field_updated_datetime = $this->updated_datetime;
    $existingReport->field_status = $this->status;

    $existingReport->save();
    return $existingReport;
  }

  /**
   * Prepares and validates the raw values from the 311 API.
   * @return array
   * @throws \Exception
   */
  protected function gatherValues() {
    $this->validateProvidedValues();

    $values = [];
    $values['uuid'] = $this->service_request_id;
    $values['type'] = 'report';
    $values['title'] = $this->service_request_id . " - " . substr($this->description, 0, 120);
    $values['field_service_request_id'] = $this->service_request_id;
    $values['field_description'] = $this->description;
    $values['field_status_notes'] = $this->status_notes;
    $values['field_status'] = $this->status;
    $values['field_requested_datetime'] = substr($this->requested_datetime, 0, 19);
    $values['field_updated_datetime'] = substr($this->updated_datetime, 0, 19);
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

  private function validateProvidedValues() {
    // Validate submitted date.
    $this->validateDateTime($this->requested_datetime);

    // Validate updated date (if it exists).
    if ($this->updated_datetime) {
      $this->validateDateTime($this->updated_datetime);
    }

    // Create a predictable Service Request ID if one isn't provided.
    if (empty($this->service_request_id)) {
      $this->service_request_id = sha1($this->requested_datetime . $this->address);
    }

    // Generate a service name if none is provided.
    if (empty($this->service_name)) {
      $this->service_name = "No service name provided";
    }

    // Generate a Description if one isn't provided.
    if (empty($this->description)) {
      $this->description = $this->service_name . "  - " . $this->address;
    }

    $requiredValues = [
      'requested_datetime',
      'description',
      'service_request_id'
    ];
    foreach ($requiredValues as $requiredValue) {
      if (empty($requiredValue)) {
        throw new \Exception("$requiredValue value is required");
      }
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