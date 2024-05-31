<?php

namespace Drupal\Tests\access_by_ref\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;

/**
 * Test the "Inherit from parent" reference type.
 *
 * @group access_by_ref
 */
class InheritFromParentTest extends AccessByRefTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a parent node type for our test node to inherit permissions from.
    NodeType::create(['type' => 'abrparent', 'name' => 'Abr Parent Page'])->save();
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
      'bundle' => 'abrparent',
      'label' => 'Additional owners',
    ])->save();

    // Create a parent node that the allowed user will have access to.
    $parentNodeId = Node::create([
      'title' => 'Test node',
      'type' => 'abrparent',
      'uid' => $this->abrAdminUser->id(),
      'field_additional_owners' => [$this->abrAllowedUser->id()],
    ])->save();

    // Create node reference field to link to parent.
    FieldStorageConfig::create([
      'field_name' => 'field_parent',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_parent',
      'entity_type' => 'node',
      'bundle' => 'abrpage',
      'label' => 'Parent',
    ])->save();

    // Create a child node and link to the parent.
    $this->abrNodeId = Node::create([
      'title' => 'Test node',
      'type' => 'abrpage',
      'uid' => $this->abrAdminUser->id(),
      'field_parent' => [$parentNodeId],
    ])->save();
  }

  /**
   * Submit "access by reference" add form for both content types.
   */
  public function submitAccessByRefForm(): void {
    $this->submitForm([
      'label' => 'Abr parent access',
      'id' => 'abr_parent_access',
      'bundle' => 'abrparent',
      'field' => 'field_additional_owners',
      'reference_type' => 'user',
      'rights_update' => TRUE,
    ], 'Save');

    // Give access to child using inherit from parent.
    $this->drupalGet('admin/config/content/access_by_ref/add');
    $this->submitForm([
      'label' => 'Abr inherit access',
      'id' => 'abr_inherit_access',
      'bundle' => 'abrpage',
      'field' => 'field_parent',
      'reference_type' => 'user',
      'rights_type' => 'update',
      'rights_update' => TRUE,
    ], 'Save');
  }

}
