<?php

namespace Drupal\Tests\aa_utils\Unit;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the AudioficUtils Service.
 */
#[CoversClass(AudioficUtils::class)]
#[Group('aa_utils')]
class AudioficUtilsTest extends UnitTestCase {

  /**
   * Tests the method ::secondsToHms.
   */
  public function testSecondsToHms() {
    $utils_service = new AudioficUtils();

    $this->assertEquals(['hours' => '00', 'mins' => '00', 'seconds' => '00'],
                        $utils_service->secondsToHms(0));

    $this->assertEquals(['hours' => '03', 'mins' => '45', 'seconds' => '30'],
                        $utils_service->secondsToHms(13530));
  }

}
