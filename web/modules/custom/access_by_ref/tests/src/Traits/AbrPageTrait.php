<?php

namespace Drupal\Tests\access_by_ref\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

/**
 * Provides a helper method for creating a repository content type with fields.
 */
trait AbrPageTrait {

  /**
   * Creates an abrpage content type with fields.
   */
  protected function createAbrPageContentType(): void {
    NodeType::create(['type' => 'abrpage', 'name' => 'Abr Page'])->save();

    // Create Description field.
    FieldStorageConfig::create([
      'field_name' => 'field_description',
      'type' => 'text_long',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_description',
      'entity_type' => 'node',
      'bundle' => 'abrpage',
      'label' => 'Description',
    ])->save();
  }

}
