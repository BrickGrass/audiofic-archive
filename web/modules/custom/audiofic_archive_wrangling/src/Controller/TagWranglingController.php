<?php

namespace Drupal\audiofic_archive_wrangling\Controller;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the root page for the tag wrangling pages.
 */
class TagWranglingController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The tag utils service.
   *
   * @var \Drupal\aa_utils\Service\AudioficTagUtils
   */
  protected $tagUtils;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  public function __construct(
    EntityTypeManager $entity_type_manager,
    AudioficTagUtils $tag_utils,
    RendererInterface $renderer,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->tagUtils = $tag_utils;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aa_utils.tag_utils'),
      $container->get('renderer'),
    );
  }

  /**
   * Returns the tag wrangling home page.
   */
  public function home() {
    $wrangling_block['title'] = $this->t('Wrangling Pages');
    $wrangling_block['content'] = [
      '#theme' => 'admin_block_content',
      '#content' => [
        'unwrangled_fandom' => $this->getWranglingViewBlock('unwrangled_tags', 'unwrangled_fandom_tags'),
        'unwrangled_relationship' => $this->getWranglingViewBlock('unwrangled_relationship_tags', 'unwrangled_relationship_tags'),
        'all_non_canonical' => $this->getWranglingViewBlock('browse_all_non_canonical_tags', 'page_1'),
      ],
    ];

    $canonical_block['title'] = $this->t('Canonical Pages');
    $canonical_block['content'] = [
      '#theme' => 'admin_block_content',
      '#content' => [
        'browse_fandom' => [
          'title' => $this->t('Browse Canonical Fandoms'),
          'description' => $this->t('Browse the canonical fandom hierarchy'),
          'url' => Url::fromRoute('audiofic_archive_wrangling.browse_canonical_fandoms'),
        ],
        'browse_relationship' => $this->getWranglingViewBlock('browse_canonical_relationships', 'page_1'),
        'create_canonical' => [
          'title' => $this->t('Create Canonical tag'),
          'description' => $this->t('The form to create a new canonical tag - please refer to our tagging standards & ensure you are not duplicating an existing canonical tag!'),
          'url' => Url::fromRoute('audiofic_archive_wrangling.create_canonical'),
        ],
        'create_root_fandom' => [
          'title' => $this->t('Create Root Fandom tag'),
          'description' => $this->t('The form to create a new root fandom tag - these should be created very rarely, please ensure you cannot create a category under an existing root fandom!'),
          'url' => Url::fromRoute('audiofic_archive_wrangling.create_root_fandom'),
        ],
        'browse_invalid_fandoms' => [
          'title' => $this->t('Browse Invalid Canonical Fandoms'),
          'description' => $this->t('This is the list of canonical fandoms that are missing a root fandom.'),
          'url' => Url::fromRoute('audiofic_archive_wrangling.browse_invalid_canonical_fandoms'),
        ],
      ],
    ];

    $build = [
      '#theme' => 'admin_page',
      '#blocks' => [$wrangling_block, $canonical_block],
      '#cache' => [
        'contexts' => ['user.roles'],
      ],
    ];
    return $build;
  }

  /**
   * Returns the Canonical Fandom tag browsing page.
   */
  public function browseCanonicalFandoms() {
    $root_fandom_ids = $this->tagUtils->getRootFandomIds();
    $fandoms = $this->loadFandomList($this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($root_fandom_ids));

    return [
      '#theme' => 'browse_canon_fandoms',
      '#attached' => [
        'library' => ['audiofic_archive_wrangling/fandom-tree'],
      ],
      '#fandoms' => $fandoms,
      '#top_level' => TRUE,
    ];
  }

  /**
   * Ajax route which returns a section of the full canonical fandom tree.
   */
  public function getCanonicalFandomSubtree(?TermInterface $taxonomy_term = NULL) {
    if (empty($taxonomy_term)) {
      throw new \Exception('Taxonomy term not provided');
    }
    $bundle = $taxonomy_term->bundle();
    if ($bundle != 'fandom') {
      $name = $taxonomy_term->getName();
      $tid = $taxonomy_term->id();
      throw new \Exception("Taxonomy term provided must be in the fandom vocabulary, term name: $name, term bundle: $bundle, term id: $tid");
    }
    if (!$this->tagUtils->isTagCanonicityAware($taxonomy_term)) {
      throw new \Exception('Taxonomy term provided must be canonicity aware');
    }
    $root_fandoms = $this->tagUtils->getRootFandomIds();
    if ($taxonomy_term->get('field_canonicity')->value != 'canon' && !in_array($taxonomy_term->id(), $root_fandoms)) {
      throw new \Exception('Taxonomy term provided must be canonical or a root fandom');
    }

    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $fandoms = $this->loadFandomList($term_storage->loadChildren($taxonomy_term->id()));

    $build = [
      '#theme' => 'browse_canon_fandoms',
      '#fandoms' => $fandoms,
      '#top_label' => FALSE,
    ];
    return new Response($this->renderer->render($build));
  }

  /**
   * Returns the Invalid Canonical Fandom tag browsing page.
   */
  public function browseInvalidCanonicalFandoms() {
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Pretty horrible, but the best way I can figure out to do this.
    // Hopefully this page will simply not be needed very often D:
    $valid_terms = $term_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'fandom')
      ->condition('field_canonicity', 'canon')
      ->condition('parent.entity:taxonomy_term.field_canonicity', 'canonical_root_fandom')
      ->execute();
    $invalid_terms = $term_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'fandom')
      ->condition('field_canonicity', 'canon')
      ->condition('tid', $valid_terms, 'NOT IN')
      ->pager(50)
      ->execute();

    $rows = [];
    /** @var \Drupal\taxonomy\TermInterface $fandom */
    foreach ($term_storage->loadMultiple($invalid_terms) as $fandom) {
      $name = $fandom->getName();
      $url = Url::fromRoute('audiofic_archive_wrangling.wrangle_canonical', ['taxonomy_term' => $fandom->id()]);
      $link = Link::fromTextAndUrl($this->t('edit'), $url);
      $rows[] = [
        'name' => ['data' => ['#markup' => $name]],
        'edit' => ['data' => $link->toRenderable()],
      ];
    }

    return [
      'content' => [
        'caption' => [
          '#markup' => $this->t('This is the list of canonical fandoms that are missing a root fandom, please edit them to fix this.'),
        ],
        'table' => [
          '#type' => 'table',
          '#header' => [
            'name' => $this->t('Tag Name'),
            'edit' => $this->t('Edit'),
          ],
          '#rows' => $rows,
          '#empty' => $this->t('There are no invalid canonical fandom terms, congratulations!'),
        ],
        'pager' => [
          '#type' => 'pager',
        ],
      ],
    ];
  }

  /**
   * Fetches a link to a wrangling view.
   */
  private function getWranglingViewBlock(string $view_id, string $view_display): array {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load($view_id)
      ->getExecutable();
    $view->initDisplay();
    $view->setDisplay($view_display);

    return [
      'title' => $view->getTitle(),
      'description' => $view->storage->get('description'),
      'url' => $view->getUrl(),
    ];
  }

  /**
   * Fetches data on an array of taxonomy terms.
   *
   * @param []TermInterface $taxonomy_terms
   *   Array of taxonomy terms to load data on.
   */
  private function loadFandomList(array $taxonomy_terms): array {
    $fandoms = [];
    foreach ($taxonomy_terms as $tid => $fandom) {
      $children = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(TRUE)
        ->condition('field_canonicity', 'canon')
        ->condition('parent.entity:taxonomy_term.tid', $tid)
        ->execute();
      $fandoms[$tid] = [
        'name' => $fandom->getName(),
        'has_children' => !empty($children),
      ];
    }
    uasort($fandoms, function ($a, $b) {
      return strcmp($a['name'], $b['name']);
    });
    return $fandoms;
  }

}
