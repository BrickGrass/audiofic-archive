<?php

namespace Drupal\aa_utils\Controller;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the fandom index pages.
 */
class FandomIndexController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The tag utils service.
   *
   * @var \Drupal\aa_utils\Service\AudioficTagUtils
   */
  protected $tagUtils;

  /**
   * The alias repository service.
   *
   * @var \Drupal\path_alias\AliasRepositoryInterface
   */
  protected $aliasRepository;

  public function __construct(
    Connection $database,
    AudioficTagUtils $tag_utils,
    AliasRepositoryInterface $alias_repository,
  ) {
    $this->database = $database;
    $this->tagUtils = $tag_utils;
    $this->aliasRepository = $alias_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('aa_utils.tag_utils'),
      $container->get('path_alias.repository'),
    );
  }

  /**
   * Returns the main fandom index, which lists all root fandoms top ten terms.
   */
  public function fandomIndex() {
    $root_fandom_ids = $this->tagUtils->getRootFandomIds();
    foreach ($this->getAllTop5Fandoms($root_fandom_ids) as $row) {
      $top_5_lists[$row->root_fandom][] = $row;
    }

    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    $fandoms = [];
    foreach ($top_5_lists as $root_fandom_id => $top_5) {
      $root_fandom = $term_storage->load($root_fandom_id);
      $fandoms[$root_fandom_id] = [
        'root_fandom' => $root_fandom,
        'more_link' => Link::fromTextAndUrl(
          $this->t('See More'),
          Url::fromRoute('aa_utils.fandom_page', ['taxonomy_term' => $root_fandom_id])
        ),
        'top_5' => $top_5,
      ];
    }
    uasort($fandoms, function ($a, $b) {
      return strcmp($a['root_fandom']->getName(), $b['root_fandom']->getName());
    });

    $build = [
      '#theme' => 'fandom-index',
      '#fandoms' => $fandoms,
      '#attached' => [
        'library' => ['aa_utils/fandom-index'],
      ],
      '#cache' => [
        'contexts' => ['route'],
        // 1 day.
        'max-age' => 86400,
      ],
    ];

    return $build;
  }

  /**
   * Returns the index page for a root fandom.
   */
  public function fandomPage(Request $request, ?TermInterface $taxonomy_term = NULL) {
    if (empty($taxonomy_term)) {
      throw new NotFoundHttpException('No taxonomy term provided!');
    }
    if (
      !$this->tagUtils->isTagCanonicityAware($taxonomy_term) ||
      $taxonomy_term->get('field_canonicity')->value != 'canonical_root_fandom'
    ) {
      throw new NotFoundHttpException('Taxonomy term is not a root fandom!');
    }

    // On first page load, register pretty alias.
    $request_uri = $request->getRequestUri();
    if (
      preg_match('/\/fandoms\/[0-9]+/', $request_uri) &&
      !$this->aliasRepository->pathHasMatchingAlias($request_uri)
    ) {
      $term_slug = str_replace('---', '-', Html::getClass($taxonomy_term->getName()));
      $alias = $this->entityTypeManager()->getStorage('path_alias')->create([
        'path' => $request_uri,
        'alias' => '/fandoms/' . $term_slug,
      ]);
      $alias->save();
      return $this->redirect('aa_utils.fandom_page', ['taxonomy_term' => $taxonomy_term->id()]);
    }

    $fandom_index = [];
    foreach ($this->getFandomWorkTotals($taxonomy_term->id()) as $row) {
      $fandom_index[mb_strtolower(mb_substr($row->term_name, 0, 1, 'utf-8'), 'UTF-8')][] = $row;
    }

    return [
      '#theme' => 'fandom-index-page',
      '#fandom_index' => $fandom_index,
      '#attached' => [
        'library' => ['aa_utils/fandom-index'],
      ],
      '#cache' => [
        'contexts' => ['route'],
        // 1 day.
        'max-age' => 86400,
      ],
    ];
  }

  /**
   * Returns the title for a root fandom index page.
   */
  public function fandomPageTitle(?TermInterface $taxonomy_term = NULL) {
    if (empty($taxonomy_term)) {
      throw new \Exception('No taxonomy term provided!');
    }

    return $taxonomy_term->getName();
  }

  /**
   * Performs a database query that fetches the top 5 fandoms for each category.
   *
   * Fetches the following fields: term name, term id, total work count,
   * root fandom & index in top 5.
   */
  private function getAllTop5Fandoms(array $root_fandom_ids): array {
    $query = $this->database->query(
      "WITH term_data AS (
        SELECT taxonomy_term_field_data.name AS term_name,
               taxonomy_term_field_data.tid AS term_id,
               node__field_fandom2.entity_id AS work_id,
               taxonomy_term__parent.parent_target_id AS root_fandom
        FROM {taxonomy_term_field_data}
        INNER JOIN {taxonomy_term__parent}
        ON taxonomy_term_field_data.tid = taxonomy_term__parent.entity_id
        INNER JOIN {taxonomy_term__field_canonicity}
        ON taxonomy_term_field_data.tid = taxonomy_term__field_canonicity.entity_id
        INNER JOIN {node__field_fandom2}
        ON taxonomy_term_field_data.tid = node__field_fandom2.field_fandom2_target_id
        INNER JOIN {node_field_data}
        ON node__field_fandom2.entity_id = node_field_data.nid
        WHERE taxonomy_term_field_data.vid = 'fandom' AND
              taxonomy_term__field_canonicity.field_canonicity_value = 'canon' AND
              node__field_fandom2.bundle IN ('legacy_work', 'work') AND
              node_field_data.status = 1 AND
              taxonomy_term__parent.parent_target_id IN (:root_fandom_ids[])
        UNION
        SELECT taxonomy_term_field_data.name AS term_name,
               taxonomy_term_field_data.tid AS term_id,
               node__field_fandom2.entity_id AS work_id,
               taxonomy_term__parent.parent_target_id AS root_fandom
        FROM {taxonomy_term_field_data}
        INNER JOIN {taxonomy_term__parent}
        ON taxonomy_term_field_data.tid = taxonomy_term__parent.entity_id
        INNER JOIN {taxonomy_term__field_canonicity}
        ON taxonomy_term_field_data.tid = taxonomy_term__field_canonicity.entity_id
        LEFT JOIN {taxonomy_term__field_canon_sibling}
        ON taxonomy_term_field_data.tid = taxonomy_term__field_canon_sibling.field_canon_sibling_target_id
        INNER JOIN {node__field_fandom2}
        ON taxonomy_term__field_canon_sibling.entity_id = node__field_fandom2.field_fandom2_target_id
        INNER JOIN {node_field_data}
        ON node__field_fandom2.entity_id = node_field_data.nid
        WHERE taxonomy_term_field_data.vid = 'fandom' AND
              taxonomy_term__field_canonicity.field_canonicity_value = 'canon' AND
              node__field_fandom2.bundle IN ('legacy_work', 'work') AND
              node_field_data.status = 1 AND
              taxonomy_term__parent.parent_target_id IN (:root_fandom_ids[])
      ),
      term_data_grouped AS (
        SELECT term_data.term_name,
               term_data.term_id,
               term_data.root_fandom,
               COUNT(DISTINCT term_data.work_id) AS total_work_count
        FROM term_data
        GROUP BY term_data.term_id, term_data.root_fandom
      ),
      term_data_with_rank AS (
        SELECT *,
               row_number() OVER (PARTITION BY root_fandom ORDER BY total_work_count DESC) AS row_no
        FROM term_data_grouped
      )
      SELECT *
      FROM term_data_with_rank
      WHERE row_no <= 5
      ORDER BY root_fandom, total_work_count DESC;",
      [':root_fandom_ids[]' => $root_fandom_ids],
    );
    return $query->fetchAll();
  }

  /**
   * Performs a database query that fetches all fandoms in a category.
   *
   * Fetches the following fields: term name, term id & total work count.
   * The results are returned in alphabetical order.
   */
  private function getFandomWorkTotals(int $root_fandom): array {
    $query = $this->database->query(
      "WITH term_data AS (
        SELECT taxonomy_term_field_data.name AS term_name,
               taxonomy_term_field_data.tid AS term_id,
               node__field_fandom2.entity_id AS work_id
        FROM {taxonomy_term_field_data}
        INNER JOIN {taxonomy_term__parent}
        ON taxonomy_term_field_data.tid = taxonomy_term__parent.entity_id
        INNER JOIN {taxonomy_term__field_canonicity}
        ON taxonomy_term_field_data.tid = taxonomy_term__field_canonicity.entity_id
        INNER JOIN {node__field_fandom2}
        ON taxonomy_term_field_data.tid = node__field_fandom2.field_fandom2_target_id
        INNER JOIN {node_field_data}
        ON node__field_fandom2.entity_id = node_field_data.nid
        WHERE taxonomy_term_field_data.vid = 'fandom' AND
              taxonomy_term__field_canonicity.field_canonicity_value = 'canon' AND
              node__field_fandom2.bundle IN ('legacy_work', 'work') AND
              node_field_data.status = 1 AND
              taxonomy_term__parent.parent_target_id = :root_fandom_id
        UNION
        SELECT taxonomy_term_field_data.name AS term_name,
               taxonomy_term_field_data.tid AS term_id,
               node__field_fandom2.entity_id AS work_id
        FROM {taxonomy_term_field_data}
        INNER JOIN {taxonomy_term__parent}
        ON taxonomy_term_field_data.tid = taxonomy_term__parent.entity_id
        INNER JOIN {taxonomy_term__field_canonicity}
        ON taxonomy_term_field_data.tid = taxonomy_term__field_canonicity.entity_id
        LEFT JOIN {taxonomy_term__field_canon_sibling}
        ON taxonomy_term_field_data.tid = taxonomy_term__field_canon_sibling.field_canon_sibling_target_id
        INNER JOIN {node__field_fandom2}
        ON taxonomy_term__field_canon_sibling.entity_id = node__field_fandom2.field_fandom2_target_id
        INNER JOIN {node_field_data}
        ON node__field_fandom2.entity_id = node_field_data.nid
        WHERE taxonomy_term_field_data.vid = 'fandom' AND
              taxonomy_term__field_canonicity.field_canonicity_value = 'canon' AND
              node__field_fandom2.bundle IN ('legacy_work', 'work') AND
              node_field_data.status = 1 AND
              taxonomy_term__parent.parent_target_id = :root_fandom_id
      )
      SELECT term_data.term_id,
             term_data.term_name,
             COUNT(DISTINCT term_data.work_id) AS work_count
      FROM term_data
      GROUP BY term_data.term_id
      ORDER BY term_data.term_name;",
      [':root_fandom_id' => $root_fandom],
    );
    return $query->fetchAll();
  }

}
