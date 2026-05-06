<?php

namespace Drupal\bootstrap_barrio_subtheme\Hook;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class BootstrapBarrioSubthemeThemeHooks {

  public function __construct(
    protected DateFormatterInterface $date_formatter,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected AudioficUtils $utils,
    protected AudioficTagUtils $tag_utils,
    protected RouteMatchInterface $route_match,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for breadcrumb.
   *
   * Removes 'Home' from the breadcrumb trail.
   *
   * If the page is a /fandom/root_fandom page, add the fandom name to
   * the breadcrumb.
   */
  #[Hook('preprocess_breadcrumb')]
  public function preprocessBreadcrumb(&$variables) {
    if (count($variables['breadcrumb'])) {
      array_shift($variables['breadcrumb']);
    }

    if ($this->route_match->getRouteName() == 'aa_utils.fandom_page') {
      $taxonomy_term = $this->route_match->getParameter('taxonomy_term');
      if (!empty($taxonomy_term)) {
        $variables['breadcrumb'][] = ['text' => $taxonomy_term->getName()];
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for user.
   */
  #[Hook('preprocess_user')]
  public function preprocessUser(&$variables) {
    $user = $variables['user'];
    $variables['created_at'] = $this->date_formatter
      ->format($user->getCreatedTime(), "custom", "j F Y");

    $reader_name_ids = array_map(
      fn ($t) => $t->id(),
      $user->get('field_reader_name')->referencedEntities());
    $variables['reader_ids'] = implode('+', $reader_name_ids);
  }

  /**
   * Implements hook_preprocess_HOOK() for field.
   */
  #[Hook('preprocess_field')]
  public function preprocessField(&$variables) {
    $variables['view_mode'] = $variables['element']['#view_mode'];
  }

  /**
   * Implements hook_preprocess_HOOK() for node.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(&$variables) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $variables["node"];
    $node_type = $node->getType();

    if (!in_array($node_type, ['work', 'legacy_work', 'playlist'])) {
      return;
    }

    $all_tags = [];
    foreach (['field_warning', 'field_fandom2', 'field_relationship', 'field_category', 'field_format'] as $key) {
      $all_tags = array_merge($all_tags, $this->tag_utils->fetchFieldTerms($node, $key));
    }
    $variables["all_tags"] = $all_tags;

    $variables['created_at'] = $this->date_formatter->format(
      $node->getCreatedTime(), "custom", "j F Y");
    $variables['updated_at'] = $this->date_formatter->format(
      $node->getChangedTime(), "custom", "j F Y");

    if ($variables['view_mode'] === 'full' && $node_type === 'work') {
      $variables['series_positions'] = $this->getSeriesPositions($node);
    }

    if ($node_type === 'playlist') {
      $this->getSeriesData($node, $variables);
    }
  }

  /**
   * Implements hook_preprocess_views_view().
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(&$variables) {
    if (
      $variables['id'] != 'browse_relationships' &&
      $variables['id'] != 'browse_legacy_series'
    ) {
      return;
    }

    $variables['#attached']['library'][] = 'bootstrap_barrio_subtheme/browse-tags';
    $variables['#attached']['library'][] = 'aa_search/search-sidebar';
  }

  /**
   * Find the position of this work in any series it is a member of.
   */
  private function getSeriesPositions(NodeInterface $node): array {
    $series_positions = [];

    foreach ($node->get('field_series')->referencedEntities() as $series) {
      $i = 1;
      foreach ($series->get('field_works_series')->referencedEntities() as $series_work) {
        if ($node->id() === $series_work->id()) {
          $series_positions[] = ['series' => $series, 'position' => $i];
          break;
        }
        $i++;
      }
    }

    return $series_positions;
  }

  /**
   * Populates $variables with data on the series selected.
   */
  private function getSeriesData(NodeInterface $node, &$variables) {
    $work_ids = $this->entity_type_manager->getStorage('node')->getQuery()
      ->condition('type', 'work')
      ->condition('field_series.entity:node.nid', $node->id())
      ->accessCheck(TRUE)
      ->execute();
    $variables["collection_length"] = count($work_ids);

    if ($variables['view_mode'] !== 'full' or empty($work_ids)) {
      return;
    }

    $works = Node::loadMultiple($work_ids);

    $work_text_links = [];
    $work_podfic_links = [];
    foreach ($works as $work) {
      $this->appendLinks($work_text_links, $work, 'field_text_post');
      $this->appendLinks($work_podfic_links, $work, 'field_podfic_post');
    }

    $variables['work_text_links'] = $work_text_links;
    $variables['work_podfic_links'] = $work_podfic_links;
    $variables['total_nsfw'] = $this->utils->getTotalNsfw($works);
  }

  /**
   * Appends the links found in a node's field to an array.
   */
  private function appendLinks(&$all_links, NodeInterface $node, string $field) {
    $links = $node->get($field);
    $index = count($links);

    foreach ($links as $link) {
      $all_links[] = [
        'uri' => $link->uri,
        'title' => $index > 0 ? $node->getTitle() : $node->getTitle() . ' ' . $index,
      ];
      $index++;
    }
  }

}
