<?php

namespace Drupal\aa_search\Hook;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class AASearchThemeHooks defines theme hooks for the aa_search module.
 */
class AASearchThemeHooks {
  use StringTranslationTrait;

  public function __construct(
    protected RouteMatchInterface $route_match,
    protected RequestStack $request_stack,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected AudioficUtils $utils,
    protected AudioficTagUtils $tag_utils,
    protected DateFormatterInterface $date_formatter,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, string $form_id) {
    if (in_array($form_id, ['simple_search_api_form', 'complex_search_api_form'])) {
      $this->removeUneccessarySearchParams($form);
    }

    // Targets all exposed search forms, eg: work search, series search etc.
    if (str_starts_with($form['#id'], 'views-exposed-form-search')) {
      $this->alterViewsExposedSearch($form);
    }
  }

  /**
   * Implements hook_views_post_render().
   *
   * Rewrites the page title of the taxonomy term search page.
   */
  #[Hook('views_post_render')]
  public function viewsPostRender(ViewExecutable $view, &$output, CachePluginBase $cache) {
    if ($this->route_match->getRouteName() !== 'entity.taxonomy_term.canonical') {
      return;
    }

    $term = $this->route_match->getParameter('taxonomy_term');
    $term_name = $term->name->value;

    switch ($term->bundle()) {
      case 'series':
        $title = $term_name;
        break;

      case 'author':
      case 'reader':
      case 'cover_artist':
        $title_case_bundle = ucwords(str_replace('_', ' ', $term->bundle()));
        $title = "Search works in $term_name ($title_case_bundle)";
        break;

      default:
        $title = "Search works in: $term_name";
        break;
    }

    $view->setTitle($title);
    $route = $this->route_match->getRouteObject();
    $route->setDefault('_title', $title);
    $route->setDefault('_title_callback', NULL);
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter() for input.
   *
   * Alter twig suggestions for specific input widgets, to allow for form
   * specific templates.
   */
  #[Hook('theme_suggestions_input_alter')]
  public function themeSuggestionsInputAlter(&$suggestions, array $variables, $hook) {
    $element = $variables['element'];
    if (!isset($element['#attributes']['data_twig_suggestion'])) {
      return;
    }

    $suggestions[] = $hook . '__' . $element['#type'] . '__' . $element['#attributes']['data_twig_suggestion'];
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter() for form_element.
   *
   * Alter twig suggestions for specific form elements, to allow for form
   * specific templates.
   */
  #[Hook('theme_suggestions_form_element_alter')]
  public function themeSuggestionsFormElementAlter(&$suggestions, array $variables, $hook) {
    $element = $variables['element'];
    if (!isset($element['#attributes']['data_twig_suggestion'])) {
      return;
    }

    $suggestions[] = $hook . '__' . $element['#attributes']['data_twig_suggestion'];
  }

  /**
   * Implements template_preprocess_page_title().
   *
   * Sets the page title to NULL on legacy series search pages.
   */
  #[Hook('preprocess_page_title')]
  public function preprocessPageTitle(&$variables) {
    if (
      $this->route_match->getRouteName() !== 'entity.taxonomy_term.canonical' or
      !\array_key_exists('title', $variables) or
      !\is_array($variables['title']) or
      !\array_key_exists('#markup', $variables['title'])
    ) {
      return;
    }

    $term = $this->route_match->getParameter('taxonomy_term');
    if ($term->bundle() === 'series') {
      $variables['title'] = NULL;
    }
  }

  /**
   * Implements hook_preprocess_fieldset().
   *
   * Allows twig templates to know whether a fieldset contains user-input
   * so that they can be expanded by default.
   */
  #[Hook('preprocess_fieldset')]
  public function preprocessFieldset(&$variables) {
    if (isset($variables['element']['#name'])) {
      $variables['fieldset_name'] = str_replace('_', '-', $variables['element']['#name']);
    }

    $variables['selection_made'] = !empty($variables['element']['#value']);

    if (isset($variables['fieldset_name']) and
        $variables['fieldset_name'] === 'filter-duration-fieldset' and
        (!empty($variables['element']['duration-from']['#value']) or
         !empty($variables['element']['duration-to']['#value']))
    ) {
      $variables['selection_made'] = TRUE;
    }
  }

  /**
   * Implements template_preprocess_input().
   *
   * Splits seconds into HH:MM:SS for the search duration widgets.
   */
  #[Hook('preprocess_input')]
  public function preprocessInput(&$variables) {
    if (!isset($variables['element']['#id']) or
        !in_array($variables['element']['#id'], ['edit-duration-from--2', 'edit-duration-to--2'])
    ) {
      return;
    }

    $total_seconds = $variables['element']['#value'];
    if ($total_seconds !== '') {
      $formatted_duration = $this->utils->secondsToHms((int) $total_seconds);
      $variables['hours'] = $formatted_duration['hours'];
      $variables['mins'] = $formatted_duration['mins'];
      $variables['seconds'] = $formatted_duration['seconds'];
    }
  }

  /**
   * Implements template_preprocess_views_view().
   *
   * Collects additional data needed for search views.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(&$variables) {
    $view = $variables['view'];
    if ($view->id() !== 'search') {
      return;
    }

    $entities = array_map(fn($result) => $result->_entity, $view->result);
    $variables['total_nsfw'] = $this->utils->getTotalNsfw($entities);
    $url_data = $this->getUrlPathAndQuery(TRUE);

    switch ($view->current_display) {
      case 'search_taxonomy':
        $tid = array_first($variables['view']->argument)->argument;
        $term = Term::load($tid);

        if (!$term) {
          break;
        }

        $variables['rss_feed'] = '/taxonomy/term/' . $tid . '/rss.xml?' . $url_data['url_query'];
        $variables['taxonomy_term'] = $term;
        if ($term->parent->target_id !== 0) {
          $variables['term_parent'] = Term::load($term->parent->target_id);
        }
        $variables['term_children'] = $this->entity_type_manager->getStorage('taxonomy_term')->loadTree(
          $term->bundle(), $term->id(), NULL, TRUE);

        switch ($term->bundle()) {
          case 'author':
          case 'reader':
          case 'cover_artist':
            $this->processSearchUserTaxonomyView($variables, $term);
            break;

          case 'series':
            $this->processSearchLegacySeriesTaxonomyView($variables, $term);
            break;
        }
        break;

      case 'search':
        $variables['rss_feed'] = $url_data['url_path'] . '/rss.xml?' . $url_data['url_query'];
        break;
    }
  }

  /**
   * Find & return the current url path & query.
   */
  private function getUrlPathAndQuery(bool $remove_page = FALSE): array {
    $current_uri = $this->request_stack->getCurrentRequest()->getRequestUri();
    $url_sections = explode('?', $current_uri);

    // Remove trailing forwards slash, if present.
    $url_path = $url_sections[0];
    $url_path = substr($url_path, -1) === '/' ? substr($url_path, 0, strlen($url_path) - 1) : $url_path;

    // Remove page param, if present & requested.
    $url_query = implode('?', array_slice($url_sections, 1, count($url_sections)));
    if ($remove_page && strlen($url_query) > 0) {
      $url_query_sections = explode('&', $url_query);
      $url_query_sections = array_reduce($url_query_sections, function ($acc, $query_section) {
        if (!str_starts_with($query_section, 'page')) {
          $acc[] = $query_section;
        }
        return $acc;
      });
      if (empty($url_query_sections)) {
        $url_query = "";
      } else {
        $url_query = implode('&', $url_query_sections);
      }
    }

    return ['url_path' => $url_path, 'url_query' => $url_query];
  }

  /**
   * Set data for a author/reader/cover artist tag search view.
   */
  private function processSearchUserTaxonomyView(&$variables, TermInterface $term) {
    $user_ids = $this->entity_type_manager->getStorage('user')->getQuery()
      ->condition("field_{$term->bundle()}_name.entity:taxonomy_term.tid", $term->id())
      ->accessCheck(TRUE)
      ->execute();
    $user = array_first(User::loadMultiple($user_ids));
    $variables['user'] = $user;

    if (empty($user)) {
      return;
    }

    $pseudonyms = [];
    foreach (["author", "reader", "cover_artist"] as $bundle) {
      if ($term->bundle() === $bundle) {
        continue;
      }
      $pseudonyms = array_merge($pseudonyms, $user->get("field_{$bundle}_name")->referencedEntities());
    }

    $variables['pseudonyms'] = $pseudonyms;
  }

  /**
   * Set data for a legacy series tag search view.
   *
   * This search page is an exception that renders as though it is a regular
   * series page, hence needing to aggregate metadata.
   */
  private function processSearchLegacySeriesTaxonomyView(&$variables, TermInterface $term) {
    // Need to do my own query to get legacy series metadata, because
    // the view results could be filtered!
    $series_work_ids = $this->entity_type_manager->getStorage('node')->getQuery()
      ->condition('type', 'legacy_work')
      ->condition('field_legacy_series.entity:taxonomy_term.tid', $term->id())
      ->sort('created', 'ASC')
      ->accessCheck(TRUE)
      ->execute();
    $series_works = Node::loadMultiple($series_work_ids);

    $authors = [];
    $readers = [];
    $languages = [];
    $all_tags = [];

    foreach ($series_works as $work) {
      $authors = array_merge($authors, $this->tag_utils->fetchFieldTerms($work, 'field_author'));
      $readers = array_merge($readers, $this->tag_utils->fetchFieldTerms($work, 'field_reader'));
      $languages = array_merge($languages, $this->tag_utils->fetchFieldTerms($work, 'field_language'));

      foreach (["field_fandom2", "field_relationship", "field_category", "field_format"] as $key) {
        $all_tags = array_merge($all_tags, $this->tag_utils->fetchFieldTerms($work, $key));
      }
    }

    $variables['authors'] = $this->utils->removeDuplicateEntities($authors);
    $variables['readers'] = $this->utils->removeDuplicateEntities($readers);
    $variables['languages'] = $this->utils->removeDuplicateEntities($languages);
    $variables['all_tags'] = $this->utils->removeDuplicateEntities($all_tags);

    $first_work = array_first($series_works);
    if (!empty($first_work)) {
      $created_date = $this->date_formatter->format($first_work->getCreatedTime(), "custom", "j F Y");
      $variables['created_at'] = $created_date;
    }

    $updated_date = $this->date_formatter->format($term->getChangedTime(), "custom", "j F Y");
    $variables['updated_at'] = $updated_date;
  }

  /**
   * Removes the 'form_build_id', 'form_token' & 'form_id' from the url.
   */
  private function removeUneccessarySearchParams(&$form) {
    $form['form_build_id']['#access'] = FALSE;
    $form['form_token']['#access'] = FALSE;
    $form['form_id']['#access'] = FALSE;
  }

  /**
   * Alters views exposed search forms.
   */
  private function alterViewsExposedSearch(&$form) {
    // Ensure widgets that need specific styling have appropriate attribute.
    foreach ([
      'search_api_fulltext', 'fandom', 'fandom-exclude', 'relationship',
      'relationship-exclude', 'reader', 'reader-exclude', 'author', 'author-exclude',
      'duration-from', 'duration-to', 'title',
    ] as $key) {
      $form[$key]['#attributes']['data_twig_suggestion'] = 'search';
    }
    $form['actions']['submit']['#attributes']['data_twig_suggestion'] = 'search';
    $form['actions']['reset']['#attributes']['data_twig_suggestion'] = 'search';
    $form['sort_by']['#attributes']['data_twig_suggestion'] = 'search';
    $form['sort_bef_combine']['#attributes']['data_twig_suggestion'] = 'search';

    // Place duration widgets into a fieldset.
    $form['duration_fieldset'] = [
      '#type' => 'fieldset',
      '#name' => 'filter_duration_fieldset',
      '#title' => $this->t('Filter Duration'),
      'duration-from' => $form['duration-from'],
      'duration-to' => $form['duration-to'],
    ];
    unset($form['duration-from']);
    unset($form['duration-to']);

    // Attach custom libraries.
    $form['#attached']['library'][] = 'aa_search/duration';
    $form['#attached']['library'][] = 'aa_search/search-sidebar';
    $form['#attached']['library'][] = 'aa_search/jump-to-filters';
  }

}
