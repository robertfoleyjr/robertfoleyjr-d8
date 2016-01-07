<?php

/**
 * @file
 * Contains Drupal\Tests\migrate_source_json\Unit\JSONClientTest
 */

namespace Drupal\Tests\migrate_source_json\Unit\Plugin\migrate\source;

use Drupal\migrate_source_json\Plugin\migrate\source\JSONSource;
use Drupal\Tests\migrate_source_json\Unit\JSONUnitTestCase;

/**
 * @coversDefaultClass Drupal\migrate_source_json\Plugin\migrate\JSONClient
 *
 * @group migrate_source_json
 */
class JSONClientTest extends JSONUnitTestCase {

  /**
   * The HTTP Client
   *
   * @var JSONClientInterface resource
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $clientClass = $this->configuration['clientClass'];
    $this->client = new $clientClass();

  }

  /**
   * Tests the construction of the client.
   *
   * @test
   *
   * @covers ::__construct
   */
  public function create() {
    $this->assertInstanceOf('Drupal\migrate_source_json\Plugin\migrate\JSONClientInterface', $this->client);
  }

  /**
   * Tests retrieving a top level response.
   *
   * @test
   *
   * @covers ::getResponseContent
   */
  public function getTopLevelResponseFromURL() {

    $test_content = $this->client->getTestContent();
    $url = 'top.json';
    $response = $this->client->getResponseContent($url);
    $this->assertEquals($response, $test_content[$url]);

  }

  /**
   * Tests retrieving a nested response.
   *
   * @test
   *
   * @covers ::getResponseContent
   */
  public function getNestedResponseFromURL() {

    $test_content = $this->client->getTestContent();
    $url = 'nested.json';
    $response = $this->client->getResponseContent($url);
    $this->assertEquals($response, $test_content[$url]);

  }


}
