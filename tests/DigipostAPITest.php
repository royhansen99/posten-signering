<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\DigipostAPI;
use royhansen\PostenSignering\SignaturePackage;
use royhansen\PostenSignering\Files;
use function royhansen\PostenSignering\Tests\getConfig;

class DigipostAPITest extends TestCase {
  const PDF = __DIR__ . '/../resources/sample/test.pdf';

  /**
   * @group api
   * @covers \royhansen\PostenSignering\DigipostAPI
   * @covers \royhansen\PostenSignering\DigipostAPI::createSignatureJob
   * @covers \royhansen\PostenSignering\DigipostAPI::getSignatureJobStatus
   */
  public function testCreateSignatureJob(): void {
    $config = getConfig();
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

    $api = new DigipostAPI($config['organization'], $config['certificate'], $config['key'], $config['ca'], $config['password'], 'test');
    $create = $api->createSignatureJob('referanse', 'https://test-completion', 'https://test-rejection', 'https://test-error', $apiFiles);

    $diff = array_diff(['signature-job-id', 'redirect-url', 'status-url'], array_keys($create));

    $this->assertTrue(!$diff, 'Missing required array keys: ' . implode(', ', $diff));
  }
}
