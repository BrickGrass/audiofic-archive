<?php

namespace Drupal\vfd\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The controller class to respond to file download link.
 */
class DownloadViewController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file_system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Constructs a NotFound object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   The file system manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, FileSystem $file_system) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  public function downloadTest() {
    return [
      '#markup' => $this->t('Download test method called.'),
    ];
  }

  /**
   * Callback function for the file download controller.
   */
  public function download($path) {
    dpm($path);
    \Drupal::logger('vfd')->notice('Download method called with path: @path', ['@path' => $path]);
    $field_machine_name = '';
    $field_type = '';
    $views_list = $this->configFactory->get('vfd.settings')->get('vfd_table');
  
    foreach ($views_list as $view) {
      if ($path == $view['view']) {
        $field_machine_name = $view['field'];
        $field_type = $view['field_type'];
        break;
      }
    }
  
    if ($field_machine_name == '' && $field_type == '') {
      return [
        '#markup' => $this->t('No downloadable files associated with this listing.'),
      ];
    }
  
    $zip = new \ZipArchive();
    $file_name = $this->fileSystem->getTempDirectory() . "/Downloads.zip";
    if ($zip->open($file_name, \ZipArchive::CREATE) !== TRUE) {
      exit("Cannot open <$file_name>\n");
    }
    $dir = $this->fileSystem->getTempDirectory() . '/Downloads';
    if (!file_exists($dir)) {
      mkdir($dir);
    }
  
    if ($field_type == 'media') {
      $results = views_get_view_result($path);
      \Drupal::logger('vfd')->notice('View results: @results', ['@results' => print_r($results, TRUE)]);
      foreach ($results as $value) {
        $node = $this->entityTypeManager->getStorage('node')->load($value->nid);
        if ($node) {
          $media = $node->$field_machine_name->entity;
          if ($media) {
            $file = $media->field_media_audio_file->entity;
            if ($file) {
              $file_uri = $file->getFileUri();
              \Drupal::logger('vfd')->notice('File URI: @uri', ['@uri' => $file_uri]);
              $copy_result = $this->fileSystem->copy($file_uri, $this->fileSystem->realpath($dir), FileSystemInterface::EXISTS_REPLACE);
              \Drupal::logger('vfd')->notice('Copy result: @result', ['@result' => $copy_result ? 'Success' : 'Failure']);
            }
            else {
              \Drupal::logger('vfd')->error('Failed to load file entity.');
            }
          }
          else {
            \Drupal::logger('vfd')->error('Failed to load media entity.');
          }
        }
        else {
          \Drupal::logger('vfd')->error('Failed to load node with ID: @id', ['@id' => $value->nid]);
        }
      }
    }
  
    if ($field_type == 'file') {
      $results = views_get_view_result($path);
      \Drupal::logger('vfd')->notice('View results: @results', ['@results' => print_r($results, TRUE)]);
      foreach ($results as $value) {
        $node = $this->entityTypeManager->getStorage('node')->load($value->nid);
        if ($node) {
          $file = $node->$field_machine_name->entity;
          if ($file) {
            $file_uri = $file->getFileUri();
            \Drupal::logger('vfd')->notice('File URI: @uri', ['@uri' => $file_uri]);
            $copy_result = $this->fileSystem->copy($file_uri, $this->fileSystem->realpath($dir), FileSystemInterface::EXISTS_REPLACE);
            \Drupal::logger('vfd')->notice('Copy result: @result', ['@result' => $copy_result ? 'Success' : 'Failure']);
          }
          else {
            \Drupal::logger('vfd')->error('Failed to load file entity.');
          }
        }
        else {
          \Drupal::logger('vfd')->error('Failed to load node with ID: @id', ['@id' => $value->nid]);
        }
      }
    }
  
    $this->createZip($zip, $this->fileSystem->realpath($dir));
    $zip_close_result = $zip->close();
    \Drupal::logger('vfd')->notice('Zip close result: @result', ['@result' => $zip_close_result ? 'Success' : 'Failure']);
  
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
    header('Content-Length: ' . filesize($file_name));
    readfile($file_name);
    flush();
    unlink($file_name);
    $this->deleteFolder($dir);
    exit();
  }

  /**
   * Generates the required zip file.
   */
  private function createZip(\ZipArchive &$zip, $dir) {
    if (is_dir($dir)) {

      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== FALSE) {

          // If file.
          if (is_file($dir . '/' . $file)) {
            if ($file != '' && $file != '.' && $file != '..') {
              $toRemove = $this->fileSystem->realpath($this->fileSystem->getTempDirectory() . '/Downloads');
              $path = str_replace($toRemove, '', $dir);
              $zip->addFile($dir . '/' . $file, $path . '/' . $file);
            }
          }
          else {
            // If directory.
            if (is_dir($dir . '/' . $file)) {

              if ($file != '' && $file != '.' && $file != '..') {
                $toRemove = $this->fileSystem->realpath($this->fileSystem->getTempDirectory() . '/Downloads');
                $path = str_replace($toRemove, '', $dir);
                // Add empty directory.
                $zip->addEmptyDir($path . '/' . $file);

                $folder = $dir . '/' . $file;

                // Read data of the folder.
                $this->createZip($zip, $folder);
              }
            }

          }

        }
        closedir($dh);
      }
    }
  }

  /**
   * Deletes the temporary folder.
   */
  private function deleteFolder($dir) {
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
            $this->deleteFolder($dir . "/" . $object);
          }
          else {
            unlink($dir . "/" . $object);
          }
        }
      }
      rmdir($dir);
    }
  }

}
