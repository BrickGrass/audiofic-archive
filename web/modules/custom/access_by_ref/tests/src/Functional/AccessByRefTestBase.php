<?php

namespace Drupal\Tests\access_by_ref\Functional;

use Drupal\Tests\access_by_ref\Traits\AbrPageTrait;
use Drupal\Tests\access_by_ref\Traits\AbrUserTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Base class for functional tests that test the AccessByRef reference types.
 *
 * Using a base class allows the same tests to be applied to each reference
 * type, with inherited classes taking care of specific requirements for each
 * reference type. The abstract function submitAccessByRefForm must be
 * overridden by each test class and should contain the setup parameters for
 * that reference type.
 *
 * @group access_by_ref
 */
abstract class AccessByRefTestBase extends BrowserTestBase {

  use AbrPageTrait;
  use AbrUserTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A node to test access.
   *
   * @var int
   */
  protected int $abrNodeId;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'access_by_ref',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAbrUsers();
    $this->createAbrPageContentType();
  }

  /**
   * Create required users for ABR test cases.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createAbrUsers(): void {
    $this->abrAdminUser = $this->drupalCreateUser(['administer access_by_ref settings']);
    $this->abrAllowedUser = $this->drupalCreateUser(['access node by reference']);
    $this->abrOtherUser = $this->drupalCreateUser(['access node by reference']);
    $this->nonAbrUser = $this->drupalCreateUser();
  }

  /**
   * Test callback.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testUserReference() {
    $viewPage = 'node/' . $this->abrNodeId;
    $editPage = $viewPage . '/edit';

    $this->loginAndAssertPageLoad($this->abrAllowedUser, $viewPage, 200);
    $this->loginAndAssertPageLoad($this->abrAllowedUser, $editPage, 403);

    // Log in as admin user.
    $this->drupalLogin($this->abrAdminUser);
    // Verify access to access by ref admin page.
    $this->drupalGet('admin/config/content/access_by_ref');
    $this->assertSession()->statusCodeEquals(200);
    // Verify access to add new access by admin.
    $this->drupalGet('admin/config/content/access_by_ref/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('Label');
    // Submit form to add user reference type.
    $this->submitAccessByRefForm();

    $this->loginAndAssertPageLoad($this->nonAbrUser, $editPage, 403);
    $this->loginAndAssertPageLoad($this->abrOtherUser, $editPage, 403);
    $this->loginAndAssertPageLoad($this->abrAllowedUser, $editPage, 200);
  }

  /**
   * Function to check if a user can access a page.
   *
   * @param \Drupal\user\Entity\User $testUser
   *   The user to log in.
   * @param string $url
   *   The page to check access.
   * @param int $expected
   *   The expected return code.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function loginAndAssertPageLoad(User $testUser, string $url, int $expected): void {
    $this->drupalLogin($testUser);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals($expected);
  }

  /**
   * Abstract function to submit the specific abr settings for reference type.
   */
  abstract protected function submitAccessByRefForm():void;

}
