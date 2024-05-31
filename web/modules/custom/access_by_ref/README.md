CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Lightweight module that extends edit permission to a user in three cases:

 * "User": The node references the user
 * "Transitive": The node references a node that the user has permission to edit
 * "Shared": The node has a value in a specified field that is the same as one
   in the user's profile
 * "Email"(dev version only). Grant access to a user with the email listed in
   the selected field.

In each case, the rule only applies to logged-in users with general permission
to access nodes by reference, and only on the node types and field names set in
the configuration page.

Permissions are "chainable", but note that there is no protection against
infinite loops, so some care is advised in configuration.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/access_by_ref

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/access_by_ref


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Access by Reference module as you would normally install a
   contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Configuration > Content Authoring > Set
       Access by reference to set access node editing by entity/field
       references.
    3. Select the Managed Content Type, Reference Type, Controlling Field, and
       Extra Data.
    4. Submit.


MAINTAINERS
-----------

 * Michael Moradzadeh (Cayenne) - https://www.drupal.org/u/cayenne
 * Marcel - https://www.drupal.org/u/marceldeb
