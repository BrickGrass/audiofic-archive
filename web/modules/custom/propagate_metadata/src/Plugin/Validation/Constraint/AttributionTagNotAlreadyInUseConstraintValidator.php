<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the AttributionTagNotAlreadyInUse constraint.
 *
 * Ensures that reader/author/cover artist tags can only be used
 * by a single user at one time.
 */
class AttributionTagNotAlreadyInUseConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(protected EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {
    if (!$value instanceof UserInterface) {
      return;
    }

    $this->ensureTagsAreNotInUse($value, $constraint, 'field_reader_name');
    $this->ensureTagsAreNotInUse($value, $constraint, 'field_author_name');
    $this->ensureTagsAreNotInUse($value, $constraint, 'field_cover_artist_name');
  }

  /**
   * Creates a constraint violation for every tag already in use.
   */
  private function ensureTagsAreNotInUse(UserInterface $user, Constraint $constraint, string $field_name) {
    $tags = $user->get($field_name)->referencedEntities();
    if (empty($tags)) {
      return;
    }

    $vocab = $this->entityTypeManager->getStorage('vocabulary')->load(array_first($tags)->bundle());

    foreach ($tags as $tag) {
      $user_storage = $this->entityTypeManager->getStorage('user');
      $user_with_tag = array_first($user_storage->getQuery()
        ->condition("$field_name.entity:taxonomy_term.tid", [$tag->id()], 'IN')
        ->condition('uid', [$user->id()], 'NOT IN')
        ->accessCheck(TRUE)
        ->execute());
      if (!$user_with_tag) {
        continue;
      }

      $this->context->addViolation($constraint->errorMessage, [
        '%user_displayname' => $user_storage->load($user_with_tag)->getDisplayName(),
        '%tag_type' => $vocab->label(),
        '%tag_name' => $tag->getName(),
      ]);
    }
  }

}
