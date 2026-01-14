<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\SignaturePackageManifest;
use royhansen\PostenSignering\Files;

class SignaturePackageManifestTest extends TestCase {
  /**
   * @group xmlValidation
   * @covers \royhansen\PostenSignering\SignaturePackageManifest
   * @covers \royhansen\PostenSignering\SignaturePackageManifest::create
   */
  public function testCreate(): void {
    $pdf = file_get_contents(__DIR__ . '/../resources/sample/test.pdf');
    $this->assertNotEmpty($pdf, "Could not get sample test.pdf content");

    $files = new Files();
    $files->add('test.pdf', $pdf, 'Test PDF', 'A small description..');

    $manifests = [
      ['reference', 'test', '123456789', $files], // Test with identifier value 'reference'
      ['personalid', '11111122222', '123456789', $files] // Test with identifier value 'personalid'
    ];

    foreach($manifests as $args) {
      $manifest = call_user_func_array(array('royhansen\PostenSignering\SignaturePackageManifest', 'create'), $args);
      $this->assertNotEmpty($manifest);
      $this->assertIsString($manifest);
      $doc = new DOMDocument;
      $this->assertTrue($doc->loadXML($manifest), 'DOMDocument::loadXML did not return TRUE');
      $this->assertTrue($doc->schemaValidate(__DIR__ . '/../resources/xsd/manifest/direct.xsd'), 'DOMDocument::schemaValidate did not return TRUE');
    }
  }

  /**
   * @covers \royhansen\PostenSignering\SignaturePackageManifest::create
   */
  public function testCreateInvalidIdentifierType(): void {
    $pdf = file_get_contents(__DIR__ . '/../resources/sample/test.pdf');
    $this->assertNotEmpty($pdf, "Could not get sample test.pdf content");

    $files = new Files();
    $files->add('test.pdf', $pdf, 'Test PDF', 'A small description..');

    $this->expectException(Exception::class);

    SignaturePackageManifest::create('invalidReference', 'value', '123456789', $files);
  }
}
