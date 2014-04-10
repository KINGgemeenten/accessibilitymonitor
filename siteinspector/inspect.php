<?php
define('STATUS_SCHEDULED', 0);
define('STATUS_TESTING', 1);
define('STATUS_TESTED', 2);

main();

function main() {
  // Main controller for the script.
  updateWebsiteEntries();
}


function updateWebsiteEntries() {
  $newWebsites = readWebsitesFile();


  // Get the database connection.
  $pdo = getDatabaseConnection();

  // Loop through the websites and check if there are new items.
  foreach ($newWebsites as $url) {
    // First try to load the website.
    $query = $pdo->prepare("SELECT * FROM website WHERE url=':url'");
    $query-execute(array('url' => $url));
    if ($row = $query->fetchObject()) {
      // If the website is already present, update it.
      $update = $pdo->prepare("UPDATE website SET status=:status WHERE wid=:wid");
      $update->execute(array(
          'status' => STATUS_SCHEDULED,
          'wid' => $row->wid
        ));
    }
    else {
      // Insert a new entry.
      $sql = "INSERT INTO website (url,status) VALUES (:url,:status)";
      $insert = $pdo->prepare($sql);
      $insert->execute(array(
          ':url' => $url,
          ':status' => STATUS_SCHEDULED
        ));
    }

  }

}

/**
 * Read the websites file.
 *
 * @return array
 */
function readWebsitesFile() {
// Get the new websites.
  $newWebsites = array();

  $handle = fopen("websites.txt", "r+");
  if ($handle) {
    while (($line = fgets($handle)) !== FALSE) {
      $newWebsites[] = $line;
    }
    // After reading the files, truncate it.
    ftruncate($handle, 0);
    fclose($handle);
  }
  else {
    // error opening the file.
  }

  return $newWebsites;
}

/**
 * Get a database connection.
 *
 * TODO: This is not very nice, but for now it works.
 *
 * @return PDO
 */
function getDatabaseConnection() {
  static $pdo_object;
  if (! isset($pdo_object)) {
    $database = get_setting('mysql_database');
    $username = get_setting('mysql_username');
    $password = get_setting('mysql_password');
    $dsn = 'mysql:host=localhost;dbname=' . $database;
    $pdo_object = new PDO($dsn, $username, $password);
  }
  return $pdo_object;
}


/**
 * Get settings parsed from the ini file.
 *
 * @param $setting
 *
 * @return string
 */
function get_setting($setting) {
  static $global_settings;
  if (! isset($global_settings)) {
    $global_settings = parse_ini_file('settings.ini');
  }
  if (isset($global_settings[$setting])) {
    return $global_settings[$setting];
  }
  return '';
}