<?php

function get_setting($setting) {
  $settings = parse_ini_file('settings.ini');
  if (isset($settings[$setting])) {
    return $settings[$setting];
  }
  return '';
}