<?php

namespace Drupal\aa_search\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom entity autocomplete for BEF.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_aa_entity_autocomplete",
 *   label = @Translation("Audiofic Archive Entity Autocomplete"),
 * )
 */
class EntityAutocompleteAudiofic extends FilterWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity_autocomplete key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $keyValue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory'),
    );
    $instance->keyValue = $container->get('keyvalue')->get('entity_autocomplete');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    $field_id = $this->getExposedFilterFieldId();
    if (!isset($form[$field_id])) {
      return;
    }

    $form[$field_id] = [
      '#type' => 'entity_autocomplete_aa',
      '#target_type' => $form[$field_id]['#target_type'],
      '#tags' => $form[$field_id]['#tags'],
      '#selection_handler' => 'search_index:taxonomy_term',
      '#selection_settings' => $form[$field_id]['#selection_settings'] ?? [],
      '#match_operator' => 'CONTAINS',
      '#max_items' => 10,
      '#placeholder' => $this->configuration['advanced']['placeholder_text'],
      '#attributes' => ['class' => [$field_id]],
      '#element_validate' => [[$this, 'elementValidate']],
    ];

    $form[$field_id]['#selection_settings']['search_index'] = 'canon_taxonomy_terms';

    if ($this->configuration['advanced']['hide_label'] === TRUE) {
      $form[$field_id]['#title_display'] = 'invisible';
    }
  }

  /**
   * Validates and processes the autocomplete element values.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function elementValidate(array $element, FormStateInterface $form_state): void {
    $value = $form_state->getValue($element['#parents']);
    if ($value) {
      $items = explode(',', $value);
      $formatted_items = self::formattedItems($items);
      if (!empty($formatted_items)) {
        $form_state->setValue($element['#parents'], $formatted_items);
      }
    }
  }

  /**
   * Formats filter items.
   *
   * @param array $items
   *   The filter items.
   *
   * @return array
   *   The formatted filter items.
   */
  protected static function formattedItems(array $items): array {
    foreach ($items as $item) {
      $formatted_items[] = ['target_id' => $item];
    }

    return $formatted_items ?? [];
  }

}
