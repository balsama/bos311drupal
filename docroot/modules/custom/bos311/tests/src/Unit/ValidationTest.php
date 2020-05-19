<?php

namespace Drupal\Tests\bos311\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\bos311\Record;

class ValidationTest extends UnitTestCase {

  public function testValidateDateTime() {

    $this->assertTrue(Record::validateDateTime('2020-05-18T18:19:14-04:00')); // Known good format
    $this->assertFalse(Record::validateDateTime('2020-05-13T06:54:14-04:00')); // Possible known bad format

  }

}
