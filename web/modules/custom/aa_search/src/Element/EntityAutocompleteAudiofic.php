<?php

namespace Drupal\aa_search\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a custom entity autocomplete element.
 *
 * The autocomplete form element allows users to select one or multiple
 * entities, which can come from all or specific bundles of an entity type.
 * A jquery-tokeninput is used to display selected entities as tags above the
 * input field. Entity ID's can also be hidden.
 *
 * Properties:
 * - #target_type: (required) The ID of the target entity type.
 * - #tags: (optional) TRUE if the element allows multiple selection. Defaults
 *   to FALSE.
 * - #default_value: (optional) The default entity or an array of default
 *   entities, depending on the value of #tags.
 * - #selection_handler: (optional) The plugin ID of the entity reference
 *   selection handler (a plugin of type EntityReferenceSelection). The default
 *   value is the lowest-weighted plugin that is compatible with #target-type.
 * - #selection_settings: (optional) An array of settings for the selection
 *   handler. Settings for the default selection handler
 *   \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection are:
 *   - target_bundles: Array of bundles to allow (omit to allow all bundles).
 *   - sort: Array with 'field' and 'direction' keys, determining how results
 *     will be sorted. Defaults to unsorted.
 *   - search_index: The machine name of the search index to query for taxonomy
 *     terms. Defaults to canon_taxonomy_terms.
 * - #autocreate: (optional) Array of settings used to auto-create entities
 *   that do not exist (omit to not auto-create entities). Elements:
 *   - bundle: (required) Bundle to use for auto-created entities.
 *   - uid: User ID to use as the author of auto-created entities. Defaults to
 *     the current user.
 * - #show_entity_id: (optional) TRUE if the entity id should be shown to the
 *   user, defaults to FALSE.
 */
#[FormElement('entity_autocomplete_aa')]
class EntityAutocompleteAudiofic extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();

    $info['#target_type'] = NULL;
    $info['#selection_handler'] = 'search_index:taxonomy_term';
    $info['#selection_settings'] = $info['#selection_settings'] ?? [];
    $info['#selection_settings']['search_index'] = 'canon_taxonomy_terms';
    $info['#tags'] = FALSE;
    $info['#autocreate'] = NULL;
    $info['#show_entity_id'] = FALSE;

    $info['#element_validate'] = [[static::class, 'validateEntityAutocomplete']];
    array_unshift($info['#process'], [static::class, 'processEntityAutocomplete']);

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if ($input === FALSE && isset($element['#default_value'])) {
      if (is_array($element['#default_value']) && $element['#tags'] !== TRUE) {
        throw new \InvalidArgumentException('The #default_value property is an array but the form element does not allow multiple values.');
      } elseif (!empty($element['#default_value']) && !is_array($element['#default_value'])) {
        // Convert to array for easier processing.
        $element['#default_value'] = [$element['#default_value']];
      }

      if ($element['#default_value']) {
        if (!(array_first($element['#default_value']) instanceof EntityInterface)) {
          throw new \InvalidArgumentException('The #default_value property has to be an entity object or an array of entity objects.');
        }

        return static::entitiesToValue($element['#default_value']);
      }
    }

    if ($input !== FALSE && is_array($input)) {
      $entity_ids = array_map(fn ($item) => $item['target_id'], $input);
      $entities = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->loadMultiple($entity_ids);
      return static::entitiesToValue($entities);
    }
  }

  /**
   * Adds aa entity autocomplete functionality to a form element.
   */
  public static function processEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    // Nothing to do if there is no target entity type.
    if (empty($element['#target_type'])) {
      throw new \InvalidArgumentException('Missing required #target_type parameter.');
    }

    // Provide default values and sanity checks for the #autocomplete parameter.
    if ($element['#autocreate']) {
      if (!isset($element['#autocreate']['bundle'])) {
        throw new \InvalidArgumentException("Missing required #autocreate['bundle'] parameter.");
      }
      $element['#autocreate']['uid'] = $element['#autocreate']['uid'] ?? \Drupal::currentUser()->id();
    }

    // Store the selection settings in the key/value store and pass a hashed key
    // in the route parameters.
    $selection_settings = $element['#selection_settings'] ?? [];
    $data = serialize($selection_settings) . $element['#target_type'] . $element['#selection_handler'];
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());

    $key_value_storage = \Drupal::keyValue('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }

    $entity_ids = explode(',', $form_state->getValue($element['#name']));
    $entities = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->loadMultiple($entity_ids);
    $entity_json = static::entitiesToValue($entities);

    $element['#attributes']['data-placeholder'] = $element['#placeholder'] ?? '';
    $element['#attributes']['data-show-entity-id'] = $element['#show_entity_id'] ?? '';
    $element['#attributes']['data-value'] = $entity_json;

    $element['#attributes']['data-autocomplete-url'] = Url::fromRoute('aa_search.entity_autocomplete', [
      'target_type' => $element['#target_type'],
      'selection_handler' => $element['#selection_handler'],
      'selection_settings_key' => $selection_settings_key,
    ])->toString();

    // TODO: Should this happen here? Or should these happen in a theming hook?
    $element['#attached']['library'][] = 'aa_search/autocomplete-token-input';
    if (!isset($element['#attributes']['class'])) {
      $element['#attributes']['class'] = [];
    }
    $element['#attributes']['class'][] = 'audiofic-autocomplete-widget';

    return $element;
  }

  /**
   * Form element validation handler for entity_autocomplete_aa elements.
   */
  public static function validateEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // If value is empty
    if (empty($element['#value'])) {
      $form_state->setValueForElement($element, NULL);
      return;
    }

    $value = NULL;

    $options = $element['#selection_settings'] + [
      'target_type' => $element['#target_type'],
      'handler' => $element['#selection_handler'],
    ];
    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
    $autocreate = (bool) $element['#autocreate'] && $handler instanceof SelectionWithAutocreateInterface;

    // GET forms might pass the validated data around on the next request, in
    // which case it will already be in the expected format.
    if (is_array($element['#value'])) {
      $value = $element['#value'];
    }
    else {
      foreach (json_decode($value) as $input) {
        if (isset($input['id'])) {
          $value[] = ['target_id' => $input['id']];
        } elseif ($autocreate) {
          // Auto-create item. See an example of how this is handled in
          // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::presave().
          /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface $handler */
          $value[] = [
            'entity' => $handler->createNewEntity(
              $element['#target_type'],
              $element['#autocreate']['bundle'],
              $input['name'],
              $element['#autocreate']['uid'],
            ),
          ];
        }
      }
    }

    if ($element['#validate_reference'] && !empty($value)) {
      // Validate existing entities.
      $ids = array_reduce($value, function ($acc, $item) {
        return isset($item['target_id']) ? array_merge($acc, [$item['target_id']]) : $acc;
      });
      if ($ids) {
        $valid_ids = $handler->validateReferenceableEntities($ids);
        if ($invalid_ids = array_diff($ids, $valid_ids)) {
          foreach ($invalid_ids as $invalid_id) {
            $form_state->setError($element, t('The referenced entity (%type: %id) does not exist.', [
              '%type' => $element['#target_type'],
              '%id' => $invalid_id,
            ]));
          }
        }
      }

      // Validate newly created entities.
      $new_entities = array_reduce($value, function ($acc, $item) {
        return isset($item['entity']) ? array_merge($acc, [$item['entity']]) : $acc;
      });

      if ($new_entities) {
        if ($autocreate) {
          $valid_new_entities = $handler->validateReferenceableNewEntities($new_entities);
          $invalid_new_entities = array_diff_key($new_entities, $valid_new_entities);
        } else {
          $invalid_new_entities = $new_entities;
        }

        foreach ($invalid_new_entities as $entity) {
          /** @var \Drupal\Core\Entity\EntityInterface $entity */
          $form_state->setError($element, t('This entity (%type: %label) cannot be referenced.', [
            '%type' => $element['#target_type'],
            '%label' => $entity->label(),
          ]));
        }
      }
    }

    if (!$element['#tags'] && !empty($value)) {
      $last_value = $value[count($value) - 1];
      $value = $last_value['target_id'] ?? $last_value;
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * Formats an array of entities to a value string.
   */
  public static function entitiesToValue(array $entities): string {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    $entity_values = [];
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($entities as $entity) {
      // Set the entity in the correct language for display.
      $entity = $entity_repository->getTranslationFromContext($entity);
      $entity_id = $entity->id();
      // Use the special view label, since some entities allow the label to be
      // viewed, even if the entity is not allowed to be viewed.
      $label = ($entity->access('view label')) ? $entity->label() : t('- Restricted access -');

      if ($label === NULL) {
        continue;
      }

      $entity_values[] = [
        'id' => $entity_id,
        'name' => $label,
      ];
    }

    return json_encode($entity_values);
  }

}
