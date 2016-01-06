<?php

/**
 * @file
 * Contains \Drupal\Tests\config_sync\Unit\ConfigSyncMergerTest.
 */

namespace Drupal\Tests\config_sync\Unit;

use Drupal\config_sync\ConfigSyncMerger;

/**
 * @coversDefaultClass \Drupal\config_sync\ConfigSyncMerger
 * @group config_sync
 */
class ConfigSyncMergerTest extends \PHPUnit_Framework_TestCase {

  /**
   * Provides default data for previous, current, and active states.
   *
   * @return array
   *   An array of three arrays representing previous, current, and active
   *   states of a piece of configuration.
   */
  protected function getStates() {
    $previous = [
      'first' => 1,
      'second' => [
        'one',
        'two',
      ],
      'third' => [
        'one' => 'first',
        'two' => 'second',
      ],
      'fourth' => 'fourth',
    ];

    $current = $previous;

    $active = $previous;

    $active['fifth'] = 'fifth';

    return [$previous, $current, $active];
  }

  /**
   * Provides data to ::testMergeConfigItemStates().
   */
  public function statesProvider() {
    $data = [];

    // Test the case that there is no change between previous and current.
    list($previous, $current, $active) = $this->getStates();

    // If there is no difference between previous and current, no changes should be
    // made to active.
    $expected = $active;

    $data['no difference'] = [$previous, $current, $active, $expected];

    // Test additions.
    list($previous, $current, $active) = $this->getStates();

    $current['second'][] = 'three';
    $current['third']['third'] = 'three';
    $current['another'] = 'test';

    // Additions should be merged into active.
    $expected = $active;
    $expected['second'][] = 'three';
    $expected['third']['third'] = 'three';
    $expected['another'] = 'test';

    $data['additions'] = [$previous, $current, $active, $expected];

    // Test deletions.
    list($previous, $current, $active) = $this->getStates();

    unset($current['first']);
    unset($current['second'][array_search('two', $current['second'])]);
    unset($current['third']['one']);

    // Deletions should be made to active.
    $expected = $active;
    unset($expected['first']);
    unset($expected['second'][array_search('two', $expected['second'])]);
    unset($expected['third']['one']);

    $data['deletions'] = [$previous, $current, $active, $expected];

    // Test deletions when the value has been customized.
    // Expected is unchanged because a customized value should not be
    // deleted.
    $active['fifth'] = 'customized';
    unset($current['fifth']);
    $expected['fifth'] = 'customized';

    $data['deletions with customization'] = [$previous, $current, $active, $expected];

    // Test changes.
    list($previous, $current, $active) = $this->getStates();
    $current['third']['one'] = 'change';
    $current['fourth'] = 'change';

    $expected = $active;
    $expected['third']['one'] = 'change';
    $expected['fourth'] = 'change';

    $data['changes'] = [$previous, $current, $active, $expected];

    // Test changes with customization.
    // In this case, the active value should be retained despite the
    // availability of an update.
    $active['third']['one'] = 'active';
    $expected['third']['one'] = 'active';

    $data['changes with customization'] = [$previous, $current, $active, $expected];

    return $data;
  }

  /**
   * @covers ::mergeConfigItemStates
   * @dataProvider statesProvider
   */
  public function testMergeConfigItemStates($previous, $current, $active, $expected) {
    $config_sync_merger = new ConfigSyncMerger();

    $result = $config_sync_merger->mergeConfigItemStates($previous, $current, $active);

    $this->assertEquals($result, $expected);
  }

}