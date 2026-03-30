<?php

namespace Drupal\aa_utils\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sets the current user as a context on user profile routes.
 */
class UserRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new UserRouteContext.
   */
  public function __construct(
    protected RouteMatchInterface $route_match,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = [];
    $context_definition = EntityContextDefinition::create('user')->setRequired(FALSE);
    $value = NULL;
    if (($route_object = $this->route_match->getRouteObject())) {
      $route_contexts = $route_object->getOption('parameters');
      // Check for a node revision parameter first.
      if (isset($route_contexts['user_revision']) && $revision = $this->route_match->getParameter('user_revision')) {
        $value = $revision;
      }
      elseif (isset($route_contexts['user']) && $user = $this->route_match->getParameter('user')) {
        $value = $user;
      }
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);

    $context = new Context($context_definition, $value);
    $context->addCacheableDependency($cacheability);
    $result['user'] = $context;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = EntityContext::fromEntityTypeId('user', $this->t('User from URL'));
    return ['user' => $context];
  }

}
