<?php

namespace Drupal\access_by_ref\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of Example.
 */
class AbrconfigListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {

    $header['id'] = $this->t('Machine name');
    $header['label'] = $this->t('Label');
    $header['bundle'] = $this->t('Bundle');
    $header['field'] = $this->t('Field');
    $header['reference_type'] = $this->t('Reference type');
    $header['extra'] = $this->t('Extra');
    $header['rights_type'] = $this->t('Rights type');
    $header['rights_read'] = $this->t('Read?');
    $header['rights_update'] = $this->t('Update?');
    $header['rights_delete'] = $this->t('Delete?');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $row['bundle'] = $entity->getBundle();
    $row['field'] = $entity->getField();
    $row['reference_type'] = $entity->getReferencetype(true);
    $row['extra'] = $entity->getExtra();
    $row['rights_type'] = $entity->getRightsType();
    $row['rights_read']  = ($entity->getRightsRead() == true) ? 'X' : '';
    $row['rights_update']  = ($entity->getRightsUpdate() == true) ? 'X' : '';
    $row['rights_delete']  = ($entity->getRightsDelete() == true) ? 'X' : '';

    // You probably want a few more properties here...

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $info = "<p>Lightweight module that extends read, update or delete permissions to a user in the following cases: </p> ";
    $info .= "<ol>";
    $info .= "<li><strong>User: </strong>The node <em>references the user</em></li>";
    $info .= "<li><strong>User's mail: </strong>The node <em>references the user's e-mail</em></li> ";
    $info .= "<li><strong>Profile value: </strong>The node has a value in a specified field that <em>is the same as one in the user's profile</em></li>";
    $info .= "<li><strong>Inherit from parent: </strong> The node <em>references a node</em> that the user has certain permissions on. </li>";
    $info .= "</ol>";
    $info .= "<p>In each case, the rule only applies to logged-in users with general permission to access nodes by reference, and only on the node types and field names set in the configuration page. </p>";
    $info .= "<p>Permissions are <strong>chainable</strong>, but note that there is no protection against infinite loops, so some care is advised in configuration.";

    // <li><b>Link FROM Parent:</b> <i>Risky</i> The node is referenced from a node the user can edit. If the user can edit that field, it gives them extensive control over site content</li>
    // <li><b>Secret Code:</b> <i>Experimental</i> The URL includes a parameter that matches the value of the field. E.g. ?field_foo=bar</li>
    $info .= "<p>These accesses will only be available to a user with the Access By Reference permissions, so be sure to set those</p>";
    $info .= "<p><b>To Use:</b> Select the content type to be controlled, and then the type of access you want to grant. Choose the field that will contain the effective data or link. In case we are looking for matched values, such as the 'Profile Value', specify in the Extra Field the field in the User Profile that has to match</p>";

    $form['info'] = array(
      '#type' => 'item',
      '#markup' => $info
    );

    $build['description'] = [
      '#prefix' => '<div>',
      '#markup' => $this->t($info, [
        ':components' => Url::fromRoute('entity.abrconfig.collection')->toString(),
        ':documentation' => 'https://www.drupal.org/node/',
      ]),
      '#suffix' => '</div>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];
    foreach ($this->load() as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }
    return $build;
  }

}