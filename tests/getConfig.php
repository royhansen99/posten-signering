<?php

declare(strict_types=1);

namespace royhansen\PostenSignering\Tests;

use Exception;

const CONFIG_FILE = __DIR__ . '/../.test';
const CONFIG_REQUIRED = ['certificate', 'key', 'ca', 'organization',
  'url', 'url_common_name'];

/**
 * @return array{
 *   certificate: string,
 *   key: string,
 *   ca: string,
 *   organization: string,
 *   password: ?string, 
 *   url: string,
 *   url_common_name: string
 * }
 */
function getConfig() {
  if(!file_exists(CONFIG_FILE))
    throw new Exception("Could not find test config: {CONFIG_FILE}");
  
  $config_file = file_get_contents(CONFIG_FILE);

  if(empty($config_file))
    throw new Exception('config is empty');

  $config = [];

  $lines = explode("\n", $config_file);
  foreach($lines as $line) {
    $line = trim($line);

    if(empty($line) || $line[0] === '#') continue;

    $line = explode('=', $line);

    if(count($line) !== 2) continue;

    $key = trim($line[0]);
    $value = trim($line[1]);

    if(empty($key) || empty($value)) continue;

    if(in_array($key, ['certificate', 'key', 'ca']))
      $value = __DIR__ . "/../$value";

    $config[$key] = $value;
  }

  foreach (CONFIG_REQUIRED as $key) {
    if (!isset($config[$key]))
      throw new Exception("Missing required config value: $key");
  }

  return [
    'certificate' => $config['certificate'],
    'key' => $config['key'],
    'ca' => $config['ca'],
    'organization' => $config['organization'],
    'password' => $config['password'] ?? null,
    'url' => $config['url'],
    'url_common_name' => $config['url_common_name']
  ];
}
