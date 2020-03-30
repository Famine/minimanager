# minimanager
MiniManager 2020 for CMaNGOS

MiniManager 2020 Prerequisites:
1 - Apache webserver 
2 - PHP 7.3+
3 - MariaDB
4 - Composer - Dependency Manager for PHP

Project has been tested on a server running Debian 10.3.  You may need to modify these prereq's to suit your installation,
however we will not cover installation of the items listed above.

Composer is being installed to support the libraries associated with the SRP6 protocols required by the CMaNGOS project.  More
information on this package is available here:
https://github.com/Laizerox/php-wowemu-auth/blob/50be65910dda86a14701302bf862f41fbc208480/README.md

As this package is a newer modification of the original minimanager webservice, expect bugs as that project was 10 years out
of date.  Login to the webservice is completely disabled at the time, however registration is confirmed working.

Directions:
Extract package to your web server.
browse to web root or to the directory the files were placed (/var/www/html)
run the following command to install the required dependencies:
  composer require laizerox/php-wowemu-auth
Edit the config.php file located within the /config folder to match your specific settings.

create the appropriate database within your database server (mariadb or mysql).  sql script file (mmfpm.sql) located in /sql.  Ignore
the void folder.  These have been left here for any backward compatibility which is not expected at this time.

Once the project is configured, the connection to the database should occur and the website will populate.
