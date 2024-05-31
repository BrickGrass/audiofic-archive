<?php

namespace Drupal\Tests\access_by_ref\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Test the "Profile value" reference type.
 *
 * @group access_by_ref
 */
class ProfileValueTest extends AccessByRefTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Create integer Team field on content type.
    FieldStorageConfig::create([
      'field_name' => 'field_team',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_team',
      'entity_type' => 'node',
      'bundle' => 'abrpage',
      'label' => 'Team',
    ])->save();

    // Create an ABR page node owned by admin user, with normal user as
    // additional owner.
    $this->abrNodeId = Node::create([
      'title' => 'Test node',
      'type' => 'abrpage',
      'uid' => $this->abrAdminUser->id(),
      'field_team' => 'red',
    ])->save();
  }

  /**
   * Override user creation to add permissions.
   *
   * For Profile Values, this function needs to override the base class version
   * to add the "field_user_team" field, and provide values for it.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createAbrUsers(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_user_team',
      'type' => 'string',
      'entity_type' => 'user',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_user_team',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Team',
    ])->save();

    $this->abrAdminUser = $this->drupalCreateUser(['administer access_by_ref settings']);
    $this->abrAllowedUser = $this->drupalCreateUser(
      permissions: ['access node by reference'],
      values: ['field_user_team' => 'red']
    );
    // User with abr permission, but on wrong team.
    $this->abrOtherUser = $this->drupalCreateUser(
      permissions: ['access node by reference'],
      values: ['field_user_team' => 'blue']
    );
    // User on correct team, but without abr permission.
    $this->nonAbrUser = $this->drupalCreateUser(
      values: ['field_user_team' => 'red']
    );
  }

  /**
   * Submit new abr "profile value" reference type.
   */
  protected function submitAccessByRefForm():void {
    $this->submitForm([
      'label' => 'Abr page access by profile value',
      'id' => 'abr_page_access',
      'bundle' => 'abrpage',
      'field' => 'field_team',
      'reference_type' => 'shared',
      'extra' => 'field_user_team',
      'rights_update' => TRUE,
    ], 'Save');
  }

}
