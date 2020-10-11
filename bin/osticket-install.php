<?php

// Based on https://github.com/CampbellSoftwareSolutions/docker-osticket/blob/master/bin/install.php

//Script settings

define('INSTALL_DIR','/var/www/src/public/');
define('SETUP_DIR',INSTALL_DIR.'../setup/'); // use git setup
define('INC_DIR',SETUP_DIR.'inc/');
define('INCLUDE_DIR',INSTALL_DIR.'include/');
define('INSTALL_CONFIG',INCLUDE_DIR.'ost-sampleconfig.php');
define('OSTICKET_CONFIGFILE',INCLUDE_DIR.'ost-config.php');

define('MAIL_CONFIG','/etc/msmtp.default');
define('MAIL_CONFIG_FILE','/etc/msmtp');

define('CRON_JOB_SCRIPT','bin/osticket-cron.php');
define('CRON_JOB_FILE','/etc/cron.d/osticket');

define('SECRET_FILE','/etc/osticket.secret.txt');
define('CONNECTION_TIMEOUT_SEC', 180);

define('LANGUAGE',getenv("LANGUAGE") ?: "en-us");

//Configure settings from environmental variables

$vars = array(
  'name'      => getenv("INSTALL_NAME")  ?: 'My Helpdesk',
  'email'     => getenv("INSTALL_EMAIL") ?: 'helpdesk@example.com',
  'fname'       => getenv("ADMIN_FIRSTNAME") ?: 'Admin',
  'lname'       => getenv("ADMIN_LASTNAME")  ?: 'User',
  'admin_email' => getenv("ADMIN_EMAIL")     ?: 'admin@example.com',
  'username'    => getenv("ADMIN_USERNAME")  ?: 'ostadmin',
  'passwd'      => getenv("ADMIN_PASSWORD")  ?: 'Admin1',
  'passwd2'     => getenv("ADMIN_PASSWORD")  ?: 'Admin1',
  'prefix'   => getenv("MYSQL_PREFIX")              ?: 'ost_',
  'dbhost'   => getenv("MYSQL_HOST")                ?: 'mysql',
  'dbname'   => getenv("MYSQL_DATABASE")            ?: 'osticket',
  'dbuser'   => getenv("MYSQL_USER")                ?: 'osticket',
  'dbpass'   => getenv("MYSQL_PASSWORD")            ?: getenv("MYSQL_ENV_MYSQL_PASSWORD"),
  'smtp_host'       => getenv("SMTP_HOST")            ?: 'localhost',
  'smtp_port'       => getenv("SMTP_PORT")            ?: 25,
  'smtp_from'       => getenv("SMTP_FROM"),
  'smtp_tls'        => getenv("SMTP_TLS"),
  'smtp_tls_certs'  => getenv("SMTP_TLS_CERTS")       ?: '/etc/ssl/certs/ca-certificates.crt',
  'smtp_user'       => getenv("SMTP_USER"),
  'smtp_pass'       => getenv("SMTP_PASSWORD"),
  'cron_interval'   => getenv("CRON_INTERVAL")        ?: 5,
  'siri'     => getenv("INSTALL_SECRET"),
  'host' => getenv("HOST"),
  'root_path' => getenv("ROOT_PATH"),
  'config'   => getenv("INSTALL_CONFIG") ?: INSTALL_CONFIG,
);

// Helper functions
function err( $msg) {
  echo '************** INSTALLER FATAL ERROR ***************';
  echo "$msg\n";
  echo '****************************************************';
  echo "Die :(";
  exit(1);
}

function boolToOnOff($v) {
  return ((boolean) $v) ? 'on' : 'off';
}

function convertStrToBool($varName, $default) {
  global $vars;
   if ($vars[$varName] != '') {
     return $vars[$varName] == '1';
   }
   return $default;
}

// Require files (must be done before any output to avoid session start warnings)
// $_SERVER['HTTP_ACCEPT_LANGUAGE'] = LANGUAGE;
chdir(SETUP_DIR);
require SETUP_DIR.'setup.inc.php';
require SETUP_DIR.'inc/class.installer.php';

/************************* Mail Configuration *******************************************/
echo "Configuring mail settings\n";

if (!$mailConfig = file_get_contents(MAIL_CONFIG)) {
  err("Failed to load mail configuration file");
};

$mailConfig = str_replace('%SMTP_HOSTNAME%', $vars['smtp_host'], $mailConfig);
$mailConfig = str_replace('%SMTP_PORT%', $vars['smtp_port'], $mailConfig);
$v = !empty($vars['smtp_from']) ? $vars['smtp_from'] : $vars['smtp_user'];
$mailConfig = str_replace('%SMTP_FROM%', $v, $mailConfig);
$mailConfig = str_replace('%SMTP_USER%', $vars['smtp_user'], $mailConfig);
$mailConfig = str_replace('%SMTP_PASS%', $vars['smtp_pass'], $mailConfig);
$mailConfig = str_replace('%SMTP_TLS_CERTS%', $vars['smtp_tls_certs'], $mailConfig);
$mailConfig = str_replace('%SMTP_TLS%', boolToOnOff(convertStrToBool('smtp_tls',true)), $mailConfig);
$mailConfig = str_replace('%SMTP_AUTH%', boolToOnOff($vars['smtp_user'] != ''), $mailConfig);

if (!file_put_contents(MAIL_CONFIG_FILE, $mailConfig) || !chown(MAIL_CONFIG_FILE,'www-data')
   || !chgrp(MAIL_CONFIG_FILE,'www-data') || !chmod(MAIL_CONFIG_FILE,0600)) {
   err("Failed to write mail configuration file");
}

//Cron interval - enable or disable
$interval = (int)$vars['cron_interval'];
if ($interval > 0) {
  echo "OSTicket cron job is set to run every {$interval} minutes\n";
  $cron = "*/{$interval} * * * * www-data /usr/bin/php ".CRON_JOB_SCRIPT."\n";
  file_put_contents(CRON_JOB_FILE, $cron);
} else {
  echo "OSTicket cron job is disabled\n";
  unlink(CRON_JOB_FILE);
}

/************************* OSTicket Installation *******************************************/

// Create installer class
$installer = new Installer(OSTICKET_CONFIGFILE); //Installer instance.

// Determine if using linked container
$linked = (boolean)getenv("MYSQL_PORT");
if (!$linked) {
  echo "Using external MySQL connection\n";
  //Check mandatory connection settings provided
  if (!getenv("MYSQL_HOST")) {
    err('Missing required environmental variable MYSQL_HOST');
  }
  if (!getenv("MYSQL_PASSWORD")) {
    err('Missing required environmental variable: MYSQL_PASSWORD');
  }
} else {
  echo "Using linked MySQL container\n";
}

// Wait for database connection
echo "Waiting for database TCP connection to become available...\n";
$s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$t = 0;
while (!@socket_connect($s,$vars['dbhost'],3306) && $t < CONNECTION_TIMEOUT_SEC) {
  $t++;
  if (($t % 15) == 0) {
    echo "Waited for $t seconds...\n";
  }
  sleep(1);
}
if ($t >= CONNECTION_TIMEOUT_SEC) {
  err("Timed out waiting for database TCP connection");
}

// Check database installation status
$db_installed = false;
echo "Connecting to database mysql://${vars['dbuser']}@${vars['dbhost']}/${vars['dbname']}\n";
if (!db_connect($vars['dbhost'],$vars['dbuser'],$vars['dbpass']))
   err(sprintf(__('Unable to connect to MySQL server: %s'), db_connect_error()));
elseif(explode('.', db_version()) < explode('.', $installer->getMySQLVersion()))
   err(sprintf(__('osTicket requires MySQL %s or later!'),$installer->getMySQLVersion()));
elseif(!db_select_database($vars['dbname']) && !db_create_database($vars['dbname'])) {
   err("Database doesn't exist");
} elseif(!db_select_database($vars['dbname'])) {
   err('Unable to select the database');
} else {
   $sql = 'SELECT * FROM `'.$vars['prefix'].'config` LIMIT 1';
   if(db_query($sql, false)) {
       $db_installed = true;
       echo "Database already installed\n";
   }
}

// Create secret if not set by env var and not previously stored
if (!$vars['siri']) {
  if (file_exists(SECRET_FILE)) {
    echo "Loading installation secret\n";
    $vars['siri'] = file_get_contents(SECRET_FILE);
  } else {
    echo "Generating new installation secret and saving\n";
    //Note that this randomly generated value is not intended to secure production sites!
    $vars['siri'] = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890_="), 0, 32);
    file_put_contents(SECRET_FILE, $vars['siri']);
  }
} else {
  echo "Using installation secret from INSTALL_SECRET environmental variable\n";
}

// Always rewrite config file in case MySQL details changed (e.g. ip address)
echo "Updating configuration file\n";
if (!$configFile = file_get_contents($vars['config'])) {
  err("Failed to load configuration file: {$vars['config']}");
};

$configFile= str_replace("define('OSTINSTALLED',FALSE);","define('OSTINSTALLED',TRUE);",$configFile);
$configFile= str_replace('%ADMIN-EMAIL',$vars['admin_email'],$configFile);
$configFile= str_replace('%CONFIG-DBHOST',$vars['dbhost'],$configFile);
$configFile= str_replace('%CONFIG-DBNAME',$vars['dbname'],$configFile);
$configFile= str_replace('%CONFIG-DBUSER',$vars['dbuser'],$configFile);
$configFile= str_replace('%CONFIG-DBPASS',$vars['dbpass'],$configFile);
$configFile= str_replace('%CONFIG-PREFIX',$vars['prefix'],$configFile);
$configFile= str_replace('%CONFIG-SIRI',$vars['siri'],$configFile);
if($vars['root_path']){
  $configFile = str_replace("# define('ROOT_PATH', '/support/');","define('ROOT_PATH', '".$vars['root_path']."');",$configFile);
}

if (!file_put_contents($installer->getConfigFile(), $configFile)) {
   err("Failed to write configuration file");
}

// Perform database installation if required
if (!$db_installed) {
  echo "Installing database. Please wait...\n";
  if (!$installer->install($vars)) {
    $errors=$installer->getErrors();
    echo "Database installation failed. Errors:\n";
    foreach($errors as $e) {
      echo "  $e\n";
    }
    exit(1);
  } else {
    echo "Database installation successful\n";
  }
}

// Update system language
echo "Setting system language to ".LANGUAGE."\n";
$sql = "INSERT INTO `".$vars['prefix']."config` (`id`, `namespace`, `key`, `value`, `updated`) VALUES (NULL, 'core', 'system_language', '".LANGUAGE."', CURRENT_TIMESTAMP);";
if(db_query($sql, false)) {
    echo "System Language Set\n";
}

// Update helpdesk_url
echo "Setting helpdesk url to ".$vars['host']."\n";
$sql = "UPDATE `".$vars['prefix']."config` SET `value`='".$vars['host'].$vars['root_path']."' WHERE `key`='helpdesk_url'";
if (db_query($sql, false)) {
    echo "helpdesk url Set\n";
}

// Install finished
echo "Install Script finished!\n";
