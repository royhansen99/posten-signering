<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\DigipostAPI;
use royhansen\PostenSignering\SignaturePackage;
use royhansen\PostenSignering\Files;

class DigipostAPITest extends TestCase {
  const CONFIG_FILE = __DIR__ . '/../.test';
  const CONFIG_REQUIRED = ['certificate', 'key', 'ca', 'organization'];
  const PDF = __DIR__ . '/../resources/sample/test.pdf';

  /**
   * @return array<string, string|null>
   */
  private function getConfig() {
    if(!file_exists(self::CONFIG_FILE)) {
      throw new Exception("Could not find test config: {self::CONFIG_FILE}");
    }
    
    $config_file = file_get_contents(self::CONFIG_FILE);

    $this->assertNotEmpty($config_file, "config is empty");

    /**
     * @var array<string, string|null>
     */
    $config = [];

    $lines = explode("\n", $config_file);
    foreach($lines as $line) {
      $line = trim($line);

      if(empty($line)) {
        continue;
      }

      $line = explode('=', $line);

      if(count($line) !== 2) {
        continue;
      }

      $key = trim($line[0]);
      $value = trim($line[1]);

      if(empty($key) || empty($value)) {
        continue;
      }

      if(in_array($key, ['certificate', 'key', 'ca'])) {
        $value = __DIR__ . "/../$value";
      }

      $config[$key] = $value;
    }

    if($diff = array_diff(self::CONFIG_REQUIRED, array_keys($config))) {
      throw new Exception('Missing required config values: ' . implode(', ', $diff));
    }

    if(!isset($config['password'])) {
      $config['password'] = null;
    }

    return $config;
  }

  /**
   * @group api
   * @covers \royhansen\PostenSignering\DigipostAPI
   * @covers \royhansen\PostenSignering\DigipostAPI::createSignatureJob
   * @covers \royhansen\PostenSignering\DigipostAPI::getSignatureJobStatus
   */
  public function testCreateSignatureJob(): void {
    $config = $this->getConfig();
    $packageFile = tempnam(sys_get_temp_dir(), 'sigpkg');

    $this->assertFileExists(self::PDF);
    $pdf = file_get_contents(self::PDF);

    $this->assertNotEmpty($pdf, "content of pdf is empty");

    $files = new Files;
    $files->add('test.pdf', $pdf, 'Test PDF', 'A small description..');

    $package = new SignaturePackage((string)$config['key'], (string)$config['certificate'], $config['password']);
    $package->create($packageFile, (string)$config['organization'], 'reference', 'test', $files);

    $this->assertFileExists($packageFile);

    $packageFileContent = file_get_contents($packageFile);
    $this->assertNotEmpty($packageFileContent);

    $apiFiles = new Files;
    $apiFiles->add('sigfile.zip', $packageFileContent);

    unlink($packageFile);

    $api = new DigipostAPI((string)$config['organization'], (string)$config['certificate'], (string)$config['key'], (string)$config['ca'], $config['password'], 'test');
    $create = $api->createSignatureJob('referanse', 'https://test-completion', 'https://test-rejection', 'https://test-error', $apiFiles);

    $diff = array_diff(['signature-job-id', 'redirect-url', 'status-url'], array_keys($create));

    $this->assertTrue(!$diff, 'Missing required array keys: ' . implode(', ', $diff));
  }
}
