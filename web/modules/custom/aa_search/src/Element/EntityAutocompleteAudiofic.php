<?php

namespace Drupal\aa_search\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Provides a custom entity autocomplete element.
 *
 * The autocomplete uses jquery-tokeninput to display selected entities
 * as tags above the input field. Entity ID's can also be hidden.
 *
 * @FormElement("entity_autocomplete_aa")
 */
class EntityAutocompleteAudiofic extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $class = static::class;

    $info['#maxlength'] = NULL;
    $info['#target_type'] = NULL;
    $info['#selection_handler'] = 'default';
    $info['#selection_settings_key'] = [];
    $info['#match_operator'] = 'CONTAINS';
    $info['#show_entity_id'] = 0;
    array_unshift($info['#process'], [$class, 'processEntityAutocompleteAudiofic']);

    return $info;
  }

  /**
   * Adds aa entity autocomplete functionality to a form element.
   */
  public static function processEntityAutocompleteAudiofic(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    // Nothing to do if there is no target entity type.
    if (empty($element['#target_type'])) {
      throw new \InvalidArgumentException('Missing required #target_type parameter.');
    }

    $element['#attached']['library'][] = 'aa_search/autocomplete-token-input';

    if (!isset($element['#attributes']['class'])) {
      $element['#attributes']['class'] = [];
    }
    $element['#attributes']['class'][] = 'audiofic-autocomplete-widget';

    $storage = \Drupal::entityTypeManager()->getStorage($element['#target_type']);
    $entity_ids = explode(',', $form_state->getValue($element['#name']));
    $entities = $storage->loadMultiple($entity_ids);
    $entity_json = static::getAudioficDefaultValue($entities);

    $element['#attributes']['data-placeholder'] = $element['#placeholder'] ?? '';
    $element['#attributes']['data-show-entity-id'] = $element['#show_entity_id'] ?? '';
    $element['#attributes']['data-value'] = $entity_json;

    // Store the selection settings in the key/value store and pass a hashed key
    // in the route parameters.
    $selection_settings = $element['#selection_settings'] ?? [];
    $data = serialize($selection_settings) . $element['#target_type'] . $element['#selection_handler'];
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());

    $key_value_storage = \Drupal::keyValue('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }

    $element['#attributes']['data-autocomplete-url'] = Url::fromRoute('aa_search.entity_autocomplete', [
      'target_type' => $element['#target_type'],
      'selection_handler' => $element['#selection_handler'],
      'selection_settings_key' => $selection_settings_key,
    ])->toString();

    // TODO: Do I need this?
    $element['#attached']['drupalSettings']['aa_search']['information_message'] = [
      'limit_tag' => t('Tags are limited to:'),
      'no_matching_suggestions' => t('No matching suggestions found for:'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if ($input === FALSE && isset($element['#default_value']) && $element['#default_value']) {
      return static::getAudioficDefaultValue($element['#default_value']);
    }

    if ($input !== FALSE && is_array($input)) {
      $entity_ids = array_map(function (array $item) {
        return $item['target_id'];
      }, $input);
      $entities = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->loadMultiple($entity_ids);

      return static::getAudioficDefaultValue($entities);
    }

    return NULL;
  }

  /**
   * Formats the default values array for this widget.
   */
  public static function getAudioficDefaultValue(array $entities) {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');
    $default_value = [];

    foreach ($entities as $entity) {
      // Set the entity in the correct language for display.
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $entity_repository->getTranslationFromContext($entity);
      $entity_id = $entity->id();
      // Use the special view label, since some entities allow the label to be
      // viewed, even if the entity is not allowed to be viewed.
      $label = ($entity->access('view label')) ? $entity->label() : t('- Restricted access -');

      if ($label === NULL) {
        continue;
      }

      $default_value[] = [
        // TODO: is it an issue that I've removed this?
        // 'value' => $entity_id,
        'id' => $entity_id,
        'name' => $label,
      ];
    }

    return json_encode($default_value);
  }

}
