<?php

// phpcs:ignoreFile

/**
 * Set the default location for the 'private' directory.  Note
 * that this location is protected when running on the Pantheon
 * environment, but may be exposed if you migrate your site to
 * another environment.
 */
$settings['file_private_path'] = 'sites/default/files/private';

// Check to see if we are serving an installer page.
$is_installer_url = (strpos($_SERVER['SCRIPT_NAME'], '/core/install.php') === 0);

/**
 * Add the Drupal 8 CMI Directory Information directly in settings.php to make sure
 * Drupal knows all about that.
 *
 * Issue: https://github.com/pantheon-systems/drops-8/issues/2
 *
 * IMPORTANT SECURITY NOTE:  The configuration paths set up
 * below are secure when running your site on Pantheon.  If you
 * migrate your site to another environment on the public internet,
 * you should relocate these locations. See "After Installation"
 * at https://www.drupal.org/node/2431247
 *
 */
if (empty($settings['config_sync_directory'])) {
  if ($is_installer_url) {
    $settings['config_sync_directory'] = 'sites/default/files';
  } else {
    $settings['config_sync_directory'] = getenv('DOCROOT') ? '../config' : 'sites/default/config';
  }
}

/**
 * The default list of directories that will be ignored by Drupal's file API.
 *
 * By default ignore node_modules and bower_components folders to avoid issues
 * with common frontend tools and recursive scanning of directories looking for
 * extensions.
 *
 * @see file_scan_directory()
 * @see \Drupal\Core\Extension\ExtensionDiscovery::scanDirectory()
 */
if (empty($settings['file_scan_ignore_directories'])) {
  $settings['file_scan_ignore_directories'] = [
    'node_modules',
    'bower_components',
  ];
}
