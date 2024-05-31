<?php

namespace Drupal\Tests\access_by_ref\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Test the "User email" reference type.
 *
 * @group access_by_ref
 */
class UserEmailTest extends AccessByRefTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Create User entity Repository URL field.
    FieldStorageConfig::create([
      'field_name' => 'field_owner_emails',
      'type' => 'email',
      'entity_type' => 'node',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_owner_emails',
      'entity_type' => 'node',
      'bundle' => 'abrpage',
      'label' => 'Owner emails',
    ])->save();

    // Create an ABR page node owned by admin user, with normal user as
    // additional owner.
    $this->abrNodeId = Node::create([
      'title' => 'Test node',
      'type' => 'abrpage',
      'uid' => $this->abrAdminUser->id(),
      'field_owner_emails' => [$this->abrAllowedUser->getEmail()],
    ])->save();
  }

  /**
   * Submit the "Add abr configuration" form for "User email" type.
   */
  public function submitAccessByRefForm(): void {
    // Submit new access by ref user email type.
    $this->submitForm([
      'label' => 'Abr email access',
      'id' => 'abr_email_access',
      'bundle' => 'abrpage',
      'field' => 'field_owner_emails',
      'reference_type' => 'user_mail',
      'rights_update' => TRUE,
    ], 'Save');
  }

}
