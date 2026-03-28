# audiofic-archive
The next generation of the [audiofic archive](https://audiofic.jinjurly.com/). The current site is doing amazingly for it's age, but Drupal 7 is well past it's EOL, so a new archive that runs on Drupal 10 is needed.

This project intends to:
- Ensure that the audiofic archive lives many more years to come, by modernising it with Drupal 10
- Reduce the work that archivists have to do by allowing podficcers to upload & manage their own works
- Enhance browsing the site with a fully custom theme
- Ensure accessibility at all levels
- And more...

This work is done by volunteers in our spare time, so as much as we would love to promise a set date that the new archive will be up and running, this simply isn't feasible. We are currently still in alpha and cannot accept input/suggestions from outside of the people who already volunteer on the audiofic archive. We are currently aiming to get the project in such a state where we can begin beta testing and accept feedback from everyone, and we will make a lot of noise when that time comes!

If you have any spare time and any level of ability with web development/html+css theming/UI design etc, we would love to have your help. George (BrickGrass) is very willing to spend time teaching anyone the ropes so that they can gain enough confidence to help out. There are tasks at every level that could use doing and everyone has to start somewhere :)

You can contact us via: jinjurlymods@squidge.org

## A sneak peek:
<img width="1835" height="934" alt="Screenshot 2026-03-11 at 8 51 50 pm" src="https://github.com/user-attachments/assets/312d420f-debf-4698-8036-cc72273916ce" />
<img width="1836" height="933" alt="Screenshot 2026-03-11 at 8 53 47 pm" src="https://github.com/user-attachments/assets/a4d1a596-f9da-4583-abf6-93543d7b1fc9" />
<img width="1836" height="936" alt="Screenshot 2026-03-11 at 8 55 26 pm" src="https://github.com/user-attachments/assets/b5eacec9-09b0-4acb-9a66-5206f9fbdbd8" />

## Setting up a development environment
Before running the project you will need to ensure that you have the following tools installed:
- if you are using windows, please first install [WSL2](https://learn.microsoft.com/en-us/windows/wsl/install) (Windows Subsystem for Linux) and ensure you install everything *inside* WSL
- [ddev](https://ddev.com/)
- [docker](https://www.docker.com/products/docker-desktop/) (docker desktop will be the easiest, but feel free to use a headless version if you are more familiar)
- [git](https://git-scm.com/install/)

Once you have all of these dependencies installed, you will need to do the following to set your local site up for the first time:
- Clone this repository using git. The command you need to run is `git clone git@github.com:BrickGrass/audiofic-archive.git`
    - NOTE: If you do not already have an ssh key set up with your git account, you will need to do that first. See [here](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/adding-a-new-ssh-key-to-your-github-account) for github's instructions on doing this.
- Navigate inside the repository you just cloned using the `cd` command. Eg: `cd audiofic-archive`
- Run `ddev start` to do the initial setup of ddevs containers. (Ie: create the database, file system, search server, apache instance, etc)
- Run `ddev composer install` to install the PHP dependencies for this project
- Open the file `web/sites/default/settings.php` and insert the line `$settings['config_sync_directory'] = '../config';` which tells the site where to look for it's config
- Run `ddev drush site-install --existing-config minimal` to install the drupal site using the existing config
    - NOTE: When this command finishes it will create an admin account for you, and it will output the password for that account to the command line. Make sure you write down/save that password for later so that you can use the admin account!
- Run `ddev drush import-menus --choice=full` to import all menu items
- Run `ddev drush import-taxonomies --choice=full` to import static taxonomy terms (warnings, ratings, etc)
- Run `ddev drush cache:rebuild` to rebuild the cache
- Open the url https://audiofic-archive.ddev.site in your web browser - there will be no homepage as you have not created one yet, but the menu should be populated and the site should be using the theme.
    - NOTE: If when you open the page in your browser you are warned about certificate issues/told the site is not secure the ssl/tls certificate that ddev generated has not been correctly added to your keystore. You can fix this by running the command: `mkcert -install && ddev poweroff && ddev start`
    - NOTE: Do not worry if the site appears, but the theme looks wrong. Log into the admin account by navigating to https://audiofic-archive.ddev.site/user/login and entering the password you saved earlier. Then navigate using the admin menu to `Appearance > Settings > Bootstrap Barrio Subtheme`, scroll to the bottom of the page and press `Save Configuration`, this should fix it.
- You will now need to create the solr collection the site will use. Ensure you are logged in as the admin user and navigate to: https://audiofic-archive.ddev.site/admin/config/search/search-api. Click on the server "Audiofic Solr" and on the edit page that loads press the "Upload configset" button. Accept the default settings on the page that opens.

## Cheat sheet of commands
- `ddev start` - start your development container if it is stopped
- `ddev mutagen sync` - resyncs the development containers files with your disk, this is useful after a big pull from git
- `ddev drush cache:rebuild` - does what it says on the tin, rebuilds all drupal caches
- `ddev drush en <drupal_module>` - After you install a new drupal module, you have to enable it using this command
- `ddev drush updatedb` - Runs any pending database migrations due to drupal core/module updates
- `ddev composer install` - Installs all packages required in the composer.json
- `ddev composer require <module_name>` - installs a new PHP or drupal module and adds it to the composer.json
- `ddev drush cex` - exports all config to the config/ directory
- `ddev drush cim` - imports config from the config/ directory
