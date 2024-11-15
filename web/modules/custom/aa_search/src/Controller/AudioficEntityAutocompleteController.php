<?php

namespace Drupal\aa_search\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\aa_search\AudioficEntityAutocompleteMatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a route controller for audiofic entity autocomplete form elements.
 */
class AudioficEntityAutocompleteController extends ControllerBase {
  /**
   * The autocomplete matcher for entity references.
   *
   * @var \Drupal\aa_search\AudioficEntityAutocompleteMatcher
   */
  protected $matcher;

  /**
   * Constructs an AudioficEntityAutocompleteController object.
   *
   * @param \Drupal\aa_search\AudioficEntityAutocompleteMatcher $matcher
   *   The matcher.
   */
  public function __construct(AudioficEntityAutocompleteMatcher $matcher) {
    $this->matcher = $matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('aa_search.autocomplete_matcher')
    );
  }

  /**
   * Set the entity autocomplete matcher.
   *
   * @param \Drupal\aa_search\AudioficEntityAutocompleteMatcher $matcher
   *   The autocomplete matcher for entity references.
   */
  protected function setMatcher(AudioficEntityAutocompleteMatcher $matcher) {
    $this->matcher = $matcher;
  }

  /**
   * Autocomplete the label of an entity.
   */
  public function handleAutocomplete(Request $request, $target_type, $selection_handler, $selection_settings_key): JsonResponse {
    $matches = [];
    $selected = $request->query->get('selected')
      ? explode(',', $request->query->get('selected'))
      : [];
    $input = $request->query->get('q');

    if ($input === NULL) {
      // Disallow access when the selection settings key is not found in the
      // key/value store.
      throw new AccessDeniedHttpException();
    }

    // Selection settings are passed in as a hashed key of a serialized array
    // stored in the key/value store.
    $selection_settings = $this->keyValue('entity_autocomplete')->get($selection_settings_key, FALSE);
    // Validate autocomplete minimum length.
    if ($input === '' && $selection_settings['suggestions_dropdown']) {
      return new JsonResponse([]);
    }

    if ($selection_settings !== FALSE) {
      $selection_settings_hash = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());
      if (!hash_equals($selection_settings_hash, $selection_settings_key)) {
        // Disallow access when the selection settings hash does not match the
        // passed-in key.
        throw new AccessDeniedHttpException('Invalid selection settings key.');
      }
    }

    $matches = $this->matcher->getMatches($target_type, $selection_handler, $selection_settings, mb_strtolower($input), $selected);
    return new JsonResponse($matches);
  }

}
