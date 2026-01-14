<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\SignaturePackage;
use royhansen\PostenSignering\Files;

class SignaturePackageTest extends TestCase {
  /**
   * @covers \royhansen\PostenSignering\SignaturePackage
   */
  public function testCreate(): void {
    $key = __DIR__ . '/../resources/sample/key.pem';
    $certificate = __DIR__ . '/../resources/sample/certificate.pem';
    $out = tempnam(sys_get_temp_dir(), 'sigpkg');

    $content = file_get_contents(__DIR__ . '/../resources/sample/test.pdf');

    $this->assertIsString($content, "Could not get content from sample test.pdf");

    $files = new Files();
    $files->add('test.pdf', $content, 'Test PDF', 'A small description..');

    $package = new SignaturePackage($key, $certificate);
    $package->create($out, '123456789', 'reference', 'Test', $files);

    $this->assertFileExists($out);

    $zip = new ZipArchive;
    $this->assertTrue($zip->open($out), 'ZipArchive::open did not return TRUE');
    $this->assertNotFalse($zip->locateName('manifest.xml'), 'ZipArchive::locateName returned FALSE');
    $this->assertNotFalse($zip->locateName('META-INF/signatures.xml'), 'ZipArchive::locateName returned FALSE');
    $this->assertGreaterThanOrEqual(4, $zip->numFiles, 'Zip archive contains less than 4 files and folders.');

    unlink($out);
  }
}
