<?php

namespace Drupal\bos311\Controller;

use Drupal\bos311\FetchResponses;
use Drupal\Core\Controller\ControllerBase;
use Drupal\bos311\Record;

class Bos311Controller extends ControllerBase {


  public function bos311() {
    $foo = new FetchResponses();
  }

  public function bos311Old() {
    $record = new Record(
      '11111111111J',
      'closed',
      'FooOBeR',
      'Deft jumping zebras',
      '2019-08-04T17:27:36',
      '162 commercial street',
      '-72.001',
      '51',
      'https://example.com/photo.jpg',
      'Closed this shit. Or did I',
      '2019-09-04T17:27:36',
    );
    $record->createRecord();

    $element = [
      "#markup" => "<h2>Hello (boston) world ;)</h2>"
    ];
    return $element;
  }

}