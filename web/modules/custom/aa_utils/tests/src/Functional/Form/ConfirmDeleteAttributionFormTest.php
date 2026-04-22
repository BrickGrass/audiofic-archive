<?php

namespace Drupal\Tests\aa_utils\Functional;

use Drupal\aa_utils\Form\ConfirmDeleteAttributionForm;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the form to remove a reader attribution from a work.
 */
#[CoversClass(ConfirmDeleteAttributionForm::class)]
#[Group('aa_utils')]
class ConfirmDeleteAttributionFormTest extends BrowserTestBase {
  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['user', 'node', 'taxonomy', 'aa_utils'];

  /**
   * Theme to enable. (TODO: olivero is user, claro is admin.)
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $bundle = 'work';
    $fieldOwner = 'field_owner';
    $fieldReader = 'field_reader';

    $this->createVocabulary([
      'name' => 'reader',
      'vid' => 'reader',
    ]);

    $this->drupalCreateContentType([
      'type' => $bundle,
      'name' => 'Work',
    ], create_body: FALSE);
    $this->createEntityReferenceField('node', $bundle, $fieldOwner, 'Owner', 'taxonomy_term');
    $this->createEntityReferenceField('node', $bundle, $fieldReader, 'Reader', 'taxonomy_term');

    // Attach field_reader_name field to user entity!
    // $this->createEntityReferenceField('node');
  }

  /**
   * Test that a user not attributed on a work cannot access the form.
   *
   * @todo Currently this is just checking that we can fetch the form in a valid state whatsoever!
   */
  public function testFormAccess() {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $work = $this->drupalCreateNode([
      'type' => $bundle,
      'title' => 'Podfic Title',
      'field_owner' => [1],
      'field_reader' => [1, 2],
    ]);

    // https://audiofic-archive.ddev.site/node/32863/user/2/delete
    $this->drupalGet('node/' . $work->id() . '/user/2/delete');
    $web_assert = $this->assertSession();
    $web_assert->pageTextContains('Do you want to delete your reader attribution from Podfic Title?');
  }

}
