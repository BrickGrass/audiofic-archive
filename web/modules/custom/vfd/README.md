CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Views Files Downloader provides an option to download all the files attached
to nodes shown in a view. It provides a dedicated link for every view to 
download files. Downloaded files are added to a Zip file, which can be used
by site visitor.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/vfd

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/vfd


REQUIREMENTS
------------

This module doesn't requires any modules outside of Drupal core.

INSTALLATION
------------
 
 * Install the vfd module as you would normally install a
   contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

 * How to Work with View Downloads :

   1. Make sure you have already configured the required file/media field
      in one of your content type and already created a view as well.
   2. Configure the required settings in '/admin/structure/views/settings/vfd'.
      Here you have to provide view name and machine name of the file/media
      field as well as type of the field.    
   2. To generate the download link of files appearing in a view, take a note
      of machine name of the view.
   3. Once done now just add string "download-view" just before the machine
      name eg  http://example.com/download-view/{view_machine_name}.
   4. The files in the corresponding view will be compressed and downloaded.


MAINTAINERS
-----------

 * Gaurav Kapoor (gaurav.kapoor) - https://www.drupal.org/u/gauravkapoor

Supporting organizations:

 * Axelerant - https://www.drupal.org/axelerant
 * OpenSense Labs - https://www.drupal.org/opensense-labs
