<?php
// Add global vars.
global $global_vars;
$global_vars = array();
// Database settings.
$global_vars['mysql_database'] = 'inspector';
$global_vars['mysql_username'] = 'inspector';
$global_vars['mysql_password'] = 'z31lb00t';
$global_vars['urls_per_sample'] = 10;
$global_vars['max_execution_time'] = 100;

$global_vars['phantomjs_executable'] = '/usr/bin/phantomjs';
$global_vars['phantomjs_timeout'] = 10;

$global_vars['debug'] = FALSE;
// Solr settings.
$global_vars['solr_phantom'] = array(
  'endpoint' => array(
    'localhost' => array(
      'host' => '127.0.0.1',
      'port' => 8080,
      'path' => '/solr/phatnomcore',
      'timeout' => 30,
    )
  )
);
$global_vars['solr_nutch'] = array(
  'endpoint' => array(
    'localhost' => array(
      'host' => 'vps38899.public.cloudvps.com',
      'port' => 8080,
      'path' => '/solr/nutch',
    )
  )
);

 