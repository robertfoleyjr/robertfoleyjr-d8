<?php

/**
 * @file
 * Contains Drupal\Tests\migrate_source_json\Unit\JSONTestCaseClient.
 *
 */

namespace Drupal\Tests\migrate_source_json\Unit;

use Drupal\migrate_source_json\Plugin\migrate\JSONClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;

/**
 * Object to retrieve and iterate over JSON data.
 */
class JSONTestCaseClient extends JSONClient {

  /**
   * Override the parent constructor, use no http request.
   */
  public function __construct() {}

  /**
   * Override the parent response, return json response object without a real http request.
   */
  public function getResponse($url) {
    $content = $this->getTestContent();
    $status = 200;
    $headers = [];

    switch ($url) {
      case 'paged1.json':
        $headers = ['Link' => '<paged2.json>; rel="next"'];
        $body = $content['paged1.json'];
        break;
      case '404.json':
        throw new BadResponseException('Not found', new Request('GET', $url));
        break;
      default:
        $body = $content[$url];
    }
    $response = new \GuzzleHttp\Psr7\Response($status, $headers, $body);
    return $response;
  }

  public function getTestContent() {
    return array(
     'top.json' => '[{"id":1,"user_name":"User Name 1","description":"Description 1","tags":["red","blue"]},{"id":2,"user_name":"User Name 2","description":"Description 2","tags":["red","yellow"]}]',
     'nested.json' => '{"data":[{"id":1,"user_name":"User Name 1","description":"Description 1","tags":["red","blue"]},{"id":2,"user_name":"User Name 2","description":"Description 2","tags":["red","yellow"]}]}',
     'paged1.json' => '{"links":{"next":"paged2.json"},"data":[{"id":1,"user_name":"User Name 1","description":"Description 1","tags":["red","blue"]},{"id":2,"user_name":"User Name 2","description":"Description 2","tags":["red","yellow"]}]}',
     'paged2.json' => '{"data":[{"id":1,"user_name":"User Name 3","description":"Description 3","tags":["red","blue"]},{"id":4,"user_name":"User Name 4","description":"Description 4","tags":["red","yellow"]}]}',
    );
  }

}
