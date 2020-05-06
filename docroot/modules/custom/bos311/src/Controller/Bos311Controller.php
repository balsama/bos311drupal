<?php

namespace Drupal\bos311\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\bos311\Record;

class Bos311Controller extends ControllerBase {

  /**
   * Jamchart page holder.
   *
   * @return array
   *   A render array. Protect at all costs.
   */
  public function bos311() {
    $record = new Record(
      '11111111111F',
      'closed',
      'FooOBeR',
      'The quick brown fox',
      '2019-08-04T17:27:36',
      '162 commercial street',
      '-72.001',
      '51',
      'https://example.com/photo.jpg',
      'Closed this shit',
      '2019-09-04T17:27:36',
    );
    $record->create();

    $element = [
      "#markup" => "<h2>Hello (boston) world ;)</h2>"
    ];
    return $element;
  }

}
