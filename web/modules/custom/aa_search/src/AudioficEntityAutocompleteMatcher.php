<?php

namespace Drupal\aa_search;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;

/**
 * Matcher class to get autocompletion results for entity reference.
 */
class AudioficEntityAutocompleteMatcher implements AudioficEntityAutocompleteMatcherInterface {

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * Constructs a AudioficEntityAutocompleteMatcher object.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   */
  public function __construct(
    SelectionPluginManagerInterface $selection_manager,
  ) {
    $this->selectionManager = $selection_manager;
  }

  /**
   * Gets matched labels based on a given search string.
   *
   * @param string $target_type
   *   The ID of the target entity type.
   * @param string $selection_handler
   *   The plugin ID of the entity reference selection handler.
   * @param array $selection_settings
   *   An array of settings that will be passed to the selection handler.
   * @param string $string
   *   (optional) The label of the entity to query by.
   * @param array $selected
   *   An array of selected values.
   *
   * @return array
   *   An array of matched entity labels, in the format required by the AJAX
   *   autocomplete API (e.g. array('value' => $value, 'label' => $label)).
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the current user doesn't have access to the specified entity.
   *
   * @see \Drupal\aa_search\Controller\AudioficEntityAutocompleteController
   */
  public function getMatches($target_type, $selection_handler, array $selection_settings, $string = '', array $selected = []): array {
    $matches = [];
    $options = $selection_settings + [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $handler = $this->selectionManager->getInstance($options);

    if ($handler !== FALSE) {
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $match_limit = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, ($match_limit === 0) ? $match_limit : $match_limit + count($selected));

      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          // Filter out already selected items.
          if (in_array($entity_id, $selected)) {
            continue;
          }

          if ($label != NULL) {
            $matches[$entity_id] = $this->buildAudioficItem($entity_id, $label);
          }
        }
      }

      if ($match_limit > 0) {
        $matches = array_slice($matches, 0, $match_limit, TRUE);
      }
    }

    return array_values($matches);
  }

  /**
   * Builds the array that represents the entity in the tagify autocomplete.
   */
  protected function buildAudioficItem($entity_id, $label): array {
    // Sanitise HTML tags in the label.
    $label = $this->isHtml($label) ? strip_tags($label) : $label;

    return [
      'id' => $entity_id,
      'name' => $label,
    ];
  }

  /**
   * Checks if a string contains HTML code.
   */
  protected function isHtml(string $string): bool {
    return $string !== strip_tags($string);
  }

}
