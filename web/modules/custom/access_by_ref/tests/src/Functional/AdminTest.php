<?php

namespace Drupal\Tests\access_by_ref\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Test to check general functionality of Access by Ref admin page.
 *
 * @group access_by_ref
 */
class AdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['access_by_ref'];

  /**
   * User with permission to use abr.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected User|false $abrNormalUser;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    // Set up the test here.
    $this->abrNormalUser = $this->drupalCreateUser(['access node by reference']);
  }

  /**
   * Test that non-admin users can't access admin page.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testNoAccessNonAdmin():void {
    $session = $this->assertSession();

    $this->drupalGet('admin/config/content/access_by_ref');
    $session->statusCodeEquals(403);

    $this->drupalLogin($this->abrNormalUser);
    $this->drupalGet('admin/config/content/access_by_ref');
    $session->statusCodeEquals(403);
  }

}
