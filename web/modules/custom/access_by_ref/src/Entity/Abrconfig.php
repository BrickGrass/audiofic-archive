<?php

namespace Drupal\access_by_ref\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\access_by_ref\AbrconfigInterface;

/**
 * Defines the abrconfig entity.
 *
 * @ConfigEntityType(
 *   id = "abrconfig",
 *   label = @Translation("Abrconfig"),
 *   handlers = {
 *     "list_builder" = "Drupal\access_by_ref\Controller\AbrconfigListBuilder",
 *     "form" = {
 *       "add" = "Drupal\access_by_ref\Form\AbrconfigForm",
 *       "edit" = "Drupal\access_by_ref\Form\AbrconfigForm",
 *       "delete" = "Drupal\access_by_ref\Form\AbrconfigDeleteForm",
 *     }
 *   },
 *   config_prefix = "abrconfig",
 *   admin_permission = "administer access_by_ref_settings settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "bundle" = "bundle",
 *     "field" = "field",
 *     "reference_type" = "reference_type",
 *     "extra" = "extra",
 *     "rights_type" = "rights_type",
 *     "rights_read" = "rights_read",
 *     "rights_update" = "rights_update",
 *     "rights_delete" = "rights_delete"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label",
 *     "bundle" = "bundle",
 *     "field" = "field",
 *     "reference_type" = "reference_type",
 *     "extra" = "extra",
 *     "rights_type" = "rights_type",
 *     "rights_read" = "rights_read",
 *     "rights_update" = "rights_update",
 *     "rights_delete" = "rights_delete"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/content/access_by_ref/{abrconfig}",
 *     "delete-form" = "/admin/config/content/access_by_ref/{abrconfig}/delete",
 *   }
 * )
 */

class Abrconfig extends ConfigEntityBase implements AbrconfigInterface {

  /**
   * The Example ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Example label.
   *
   * @var string
   */
  protected $label;

  /**
   * The bundle field of the abr config item.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The field field of the abr config item.
   *
   * @var string
   */
  protected $field;

  /**
   * The reference type field of the abr config item.
   *
   * @var string
   */
  protected $reference_type;

  /**
   * The extra field of the abr config item.
   *
   * @var string
   */
  protected $extra;

  /**
   * The rights_type field of the abr config item.
   *
   * @var string
   */
  protected $rights_type;

  /**
   * The rights_read field of the abr config item.
   *
   * @var string
   */
  protected $rights_read;

  /**
   * The rights_update field of the abr config item.
   *
   * @var string
   */

  protected $rights_update;

  /**
   * The rights_delete field of the abr config item.
   *
   * @var string
   */
  protected $rights_delete;


  public function getBundle() {
    return $this->get('bundle');
  }

  public function setBundle($bundle) {
    return $this->set('bundle', $bundle);
  }

  public function getField() {
    return $this->get('field');
  }

  public function setField($field) {
    return $this->set('field', $field);
  }

  public function getReferenceType($readable=false) {

    if($readable == true) {
      switch ($this->get('reference_type')) {
        case 'user':
          return "User referenced";
        case 'user_mail':
          return "User's mail";
        case 'shared':
          return "Profile value";
        case 'inherit':
          return "Inherit from parent";
        default:
          return $this->get('reference_type');
      }
    }
    else{
      return $this->get('reference_type');
    }

  }

  public function setReferenceType($reference_type) {
    return $this->set('reference_type', $reference_type);
  }

  public function getExtra() {
    return $this->get('extra');
  }

  public function setExtra($extra) {
    return $this->set('extra', $extra);
  }

  public function getRightsType($readable = false) {
      return $this->get('rights_type');
  }

  public function setRightsType($rights_type) {
    return $this->set('rights_type', $rights_type);
  }

  public function getRightsRead() {
    return $this->get('rights_read');
  }

  public function setRightsRead($rights_read) {
    return $this->set('rights_read', $rights_read);
  }

  public function getRightsUpdate() {
    return $this->get('rights_update');
  }

  public function setRightsUpdate($rights_update) {
    return $this->set('rights_update', $rights_update);
  }

  public function getRightsDelete() {
    return $this->get('rights_delete');
  }

  public function setRightsDelete($rights_delete) {
    return $this->set('rights_delete', $rights_delete);
  }

}
