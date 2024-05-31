<?php

namespace Drupal\Tests\access_by_ref\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Test the "User reference" reference type.
 *
 * @group access_by_ref
 */
class UserReferenceTest extends AccessByRefTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Create User entity Repository URL field.
    FieldStorageConfig::create([
      'field_name' => 'field_additional_owners',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'user',
      ],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_additional_owners',
      'entity_type' => 'node',
      'bundle' => 'abrpage',
      'label' => 'Additional owners',
    ])->save();

    // Create an ABR page node owned by admin user, with normal user as
    // additional owner.
    $this->abrNodeId = Node::create([
      'title' => 'Test node',
      'type' => 'abrpage',
      'uid' => $this->abrAdminUser->id(),
      'field_additional_owners' => [$this->abrAllowedUser->id()],
    ])->save();
  }

  /**
   * Submit the "Add abr configuration" form for User reference.
   */
  protected function submitAccessByRefForm():void {
    // Submit form to add user reference type.
    $this->submitForm([
      'label' => 'Abr page access',
      'id' => 'abr_page_access',
      'bundle' => 'abrpage',
      'field' => 'field_additional_owners',
      'reference_type' => 'user',
      'rights_update' => TRUE,
    ], 'Save');
  }

}
