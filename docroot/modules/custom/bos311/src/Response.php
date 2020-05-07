<?php

namespace Drupal\bos311;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\ServerException;

class Response {

  public function fetch($url, $retryOnError = true)
  {
    $client = new Client();
    try {
      /**
       * @var $response ResponseInterface $response
       */
      $response = $client->get($url);
      $body = $response->getBody();
      $body = \GuzzleHttp\json_decode($body);
      $body = \GuzzleHttp\json_encode($body);
      return $body;
    } catch (ServerException $e) {
      if ($retryOnError) {
        return self::fetch($url, $retryOnError);
      }
      echo 'Caught response: ' . $e->getResponse()->getStatusCode();
    }
  }

}
