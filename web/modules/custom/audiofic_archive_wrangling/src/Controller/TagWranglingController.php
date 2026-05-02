<?php

namespace Drupal\audiofic_archive_wrangling\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Drupal\views\Entity\View;

/**
 * Provides the root page for the tag wrangling pages.
 */
class TagWranglingController extends ControllerBase {

  /**
   * System Manager Service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  public function __construct(
    SystemManager $systemManager,
    MenuLinkTreeInterface $menu_link_tree,
  ) {
    $this->systemManager = $systemManager;
    $this->menuLinkTree = $menu_link_tree;
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
      ],
    ];
    // TODO: view to browse arbitrary non-canon tags (unsorted/non-canon/unwrangleable). Expose filters
    // and allow users to see data on the tags in a table (n.o. works tagged with this, vocab, synonymous canonical).

    $canonical_block['title'] = $this->t('Canonical Pages');
    $canonical_block['content'] = [
      '#theme' => 'admin_block_content',
      '#content' => [
        'create_canonical' => [
          'title' => $this->t('Create Canonical tag'),
          'description' => $this->t('The form to create a new canonical tag - please refer to our tagging standards & ensure you are not duplicating an existing canonical tag!'),
          'url' => Url::fromRoute('audiofic_archive_wrangling.create_canonical'),
        ],
      ],
    ];
    // TODO: View to allow browsing canonical relationships. Exposed filters, links to our edit page, relevant data.
    // TODO: Page to allow browsing canonical fandom heirarchy - Show only the top level terms and allow users to
    // browse down into the data. Root fandom -> everything
    // TODO: View to help find canonical fandoms with invalid heirarchy data - not linked to a root fandom

    $build = [
      '#theme' => 'admin_page',
      '#blocks' => [$wrangling_block, $canonical_block],
    ];
    return $build;
  }

  /**
   * Fetches a link to a wrangling view.
   */
  private function getWranglingViewBlock(string $view_id, string $view_display): array {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = \Drupal::entityTypeManager()
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

}
