# WordPress on App Engine Starter Project

***Note: This repo is essentially the "source" of the starter projects, allowing you to browse our code, suggest changes, etc. We provide already-built-and-zipped WordPress projects for Windows, Mac, and Linux at [Quick Start WordPress for Google App Engine](http://googlecloudplatform.github.io/appengine-php-wordpress-starter-project/).***

## Prerequisites

1. Install the [PHP SDK for Google App Engine](https://developers.google.com/appengine/downloads#Google_App_Engine_SDK_for_PHP)
2. Install [MySQL](http://dev.mysql.com/downloads/)
3. [Sign up](http://cloud.google.com/console) for a Google Cloud Platform project, and
set up a Cloud SQL instance, as described [here](https://cloud.google.com/sql/docs/getting-started#create), and a
Cloud Storage bucket, as described [here](https://developers.google.com/storage/docs/signup). You'll want to name
your Cloud SQL instance "wordpress" to match the config files provided here. To keep costs down, we suggest signing up for a D0 instance with package billing. 
4. Visit your project in the
[Google Cloud Console](http://cloud.google.com/console), going to the IAM & Admin **Service accounts**
area, and make a note of the **Service Account Name** for your application, which has an e-mail address
(e.g. `<PROJECT_ID>@appspot.gserviceaccount.com`). Then, visit the Cloud Storage section of your project,
select the checkbox next to the bucket you created in step 3, click
**Bucket Permissions**, and add your Service Account Name as a **User** account that has **Writer** permission.

## Cloning and setup

### Step 1: Clone

Clone this git repo and its submodules by running the following commands:

    git clone --recursive https://github.com/GoogleCloudPlatform/appengine-php-wordpress-starter-project.git
    cd appengine-php-wordpress-starter-project/

You now have a copy of [WordPress](http://wordpress.org/), the
[App Engine plugin for WordPress](http://wordpress.org/plugins/google-app-engine/),
[Batcache](http://wordpress.org/plugins/batcache/), and
[Memcached Object Cache](http://wordpress.org/plugins/memcached/).

### Step 2: Edit the config files

Edit `wp-config.php` and `app.yaml`, replacing `your-project-id`
to match the Project ID (not the name) that was assigned to your
Google Cloud Platform project.

### Step 3: Move files into place:

Because of GitHub and licensing limitations, we can't put these files in the right places for you.

Run this script to move all the files into place:

    move_files_after_editing.sh

This script:

1. Moves `wp-config.php` from root into `wordpress/`, replacing the file there.
2. Moves `batcache/advanced-cache.php` to `wordpress/wp-content/`
3. Moves `batcache/batcache.php` to `wordpress/wp-content/plugins/`
4. Moves `wp-memcache/object-cache.php` to `wordpress/wp-content/`
5. Moves the contents of `appengine-wordpress-plugin/` to `wordpress/wp-content/plugins/`

## Running WordPress locally

>First, edit [wp-config.php](https://github.com/GoogleCloudPlatform/appengine-php-wordpress-starter-project/edit/master/wp-config.php)
  so that the local environment password for root is not literally the string "password" -- unless that's what you used
  when setting up MySQL locally.

Using MySQL's command line version, run `databasesetup.sql` to set up your local database. For a default installation (no root password)
this would be:

    mysql -u root < databasesetup.sql

But really, all it's doing is running this line -- the WordPress installation script will do the heavy lifting
when it comes to setting up your database.

    CREATE DATABASE IF NOT EXISTS wordpress_db;

To run WordPress locally on Windows and OS X, you can use the
[Launcher](https://developers.google.com/appengine/downloads#Google_App_Engine_SDK_for_PHP)
by going to **File > Add Existing Project** or you can run one of the commands below.

On Mac and Windows, the default is to use the PHP binaries bundled with the SDK:

    $ APP_ENGINE_SDK_PATH/dev_appserver.py path_to_this_directory

On Linux, or to use your own PHP binaries, use:

    $ APP_ENGINE_SDK_PATH/dev_appserver.py --php_executable_path=PHP_CGI_EXECUTABLE_PATH path_to_this_directory

Now, with App Engine running locally, visit `http://localhost:8080/wp-admin/install.php` in your browser and run
the setup process, changing the port number from 8080 if you aren't using the default.
Or, to install directly from the local root URL, define `WP_SITEURL` in your `wp-config.php`, e.g.:

    define( 'WP_SITEURL', 'http://localhost:8080/');

You should be able to log in, and confirm that your app is ready to deploy.

### Deploy!

If all looks good, you can upload your application using the Launcher or by using this command:

    $ APP_ENGINE_SDK_PATH/appcfg.py update APPLICATION_DIRECTORY

Just like you had to do with the local database, you'll need to set up the Cloud SQL instance. The SDK includes
a tool for doing just that:

    google_sql.py <PROJECT_ID>:wordpress

This launches a browser window that authorizes the `google_sql.py` tool to connect to your Cloud SQL instance.
After clicking **Accept**, you can return to the command prompt, which has entered into the SQL command tool
and is now connected to your instance. Next to `sql>`, enter this command:

    CREATE DATABASE IF NOT EXISTS wordpress_db;

You should see that it inserted 1 row of data creating the database. You can now type `exit` -- we're done here.

Now, just like you did when WordPress was running locally, you'll need to run the install script by visiting:

    http://<PROJECT_ID>.appspot.com/wp-admin/install.php

Or, to install directly from the root URL, you can define WP_SITEURL in your `wp-config.php`, e.g.:

    define( 'WP_SITEURL', 'http://<YOUR_PROJECT_ID>.appspot.com/');

### Activating the plugins, configuring email, and hooking up WordPress to your Cloud Storage

**The following steps should be performed on your hosted copy of WordPress on App Engine**

#### Activating the plugins

Now, we just need to activate the plugins that were packaged with your app. Log into the WordPress
administration section of your blog at `http://<PROJECT_ID>.appspot.com/wp-admin`, and visit the
Plugins section. Click the links to activate **Batcache Manager** and **Google App Engine for WordPress**.

#### Configuring email and hooking WordPress up to your Cloud Storage

Now visit **Settings > App Engine**. Enable the App Engine mail service - this will use the App Engine Mail
API to send notifications from WordPress. Optionally, enter a valid e-mail address that mail should be sent
from (if you leave this blank, the plugin will determine a default address to use). The address of the account
you used to the create the Cloud Console project should work.

Stay on this page, because in order to be able to embed images and other multimedia in your WordPress content,
you need to enter the name of the Cloud Storage bucket you created when going through all the Prequisites earlier
under **Upload Settings**.

Hit **Save Changes** to commit everything.

## That's all! (PHEW)

Congratulations! You should now have a blog that loads rapidly, caches elegantly,
sends email properly, and can support adding images and other media to blog posts! Most importantly,
it will take advantage of Google's incredibly powerful infrastructure and scale gracefully to
accomodate traffic that is hitting your blog.

### Maintaining

You'll want to keep your local copy of the application handy because that's how you install other plugins and update
the ones that are packaged with this project. Due to the tight security of the
App Engine sandbox, you can't directly write to files in the application area -- they're static. That's
also why we hooked your uploads up to Cloud Storage. So, to install plugins, you log into the admin area
of your local WordPress instance, install or update any plugins you want there, and
redeploy. Then go into the admin area for your hosted WordPress instance to activate the plugins.
