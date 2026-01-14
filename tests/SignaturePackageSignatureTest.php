<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\SignaturePackageSignature;
use royhansen\PostenSignering\Files;

class SignaturePackageSignatureTest extends TestCase {
  /**
   * @group xmlValidation
   * @covers \royhansen\PostenSignering\SignaturePackageSignature
   */
  public function testCreate(): void {
    $key = file_get_contents(__DIR__ . '/../resources/sample/key.pem');
    $certificate = file_get_contents(__DIR__ . '/../resources/sample/certificate.pem');
    $pdf = file_get_contents(__DIR__ . '/../resources/sample/test.pdf');

    $this->assertNotEmpty($key, "Could not get sample key.pem content");
    $this->assertNotEmpty($certificate, "Could not get sample certificate.pem content");
    $this->assertNotEmpty($pdf, "Could not get sample test.pdf content");

    $signature = new SignaturePackageSignature($key, $certificate);
    $files = new Files();
    $files->add('test.pdf', $pdf, 'Test PDF', 'A small description..');

    $createSignature = $signature->create($files);
    $this->assertNotEmpty($createSignature, 'XML is empty.');
    $doc = new DOMDocument;
    $this->assertTrue($doc->loadXML($createSignature), 'DOMDocument::loadXML did not return TRUE');
    $this->assertTrue($doc->schemaValidate(__DIR__ . '/../resources/xsd/signatures/XAdES.xsd'), 'DOMDocument::schemaValidate did not return TRUE');
  }

  /**
   * @covers \royhansen\PostenSignering\SignaturePackageSignature::testKeyAndCertificate
   */
  public function testInvalidKey(): void {
    $certificate = file_get_contents(__DIR__ . '/../resources/sample/certificate.pem');
    $this->assertNotEmpty($certificate, "Could not get sample certificate.pem content");

    $this->expectException(Exception::class);
    new SignaturePackageSignature('Invalid key here..', $certificate);
  }
}
