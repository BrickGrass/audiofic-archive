<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NonCanonicalTagMustHaveSiblingConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
    if (!$value instanceof TermInterface) {
      return;
    }

    if (!$value->hasField('field_canonicity') || !$value->hasField('field_canon_sibling')) {
      return;
    }

    $canonicity = $value->get('field_canonicity')->value;
    if ($canonicity != 'non_canon') {
      return;
    }

    $canonical_sibling = array_first(array_column($value->get('field_canon_sibling')->getValue(), 'target_id'));
    if (empty($canonical_sibling)) {
      $this->context->addViolation($constraint->missingErrorMessage, [
        '%tag_name' => $value->getName(),
      ]);
      return;
    }

    /** @var \Drupal\taxonomy\TermInterface $sibling */
    $sibling = $this->entityTypeManager->getStorage('taxonomy_term')->load($canonical_sibling);

    if (empty($sibling)) {
      $this->context->addViolation($constraint->siblingDoesntExistErrorMessage, [
        '%sibling_id', $canonical_sibling,
      ]);
      return;
    }

    if (!$sibling->hasField('field_canonicity') || $sibling->get('field_canonicity')->value != 'canon') {
      $this->context->addViolation($constraint->siblingNotCanonicalErrorMessage, [
        '%target' => $value->getName(),
        '%sibling' => $sibling->getName(),
      ]);
    }
  }

}
