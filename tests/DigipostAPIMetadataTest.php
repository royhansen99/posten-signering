<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\DigipostAPIMetadata;

class DigipostAPIMetadataTest extends TestCase {
  /**
   * @group xmlValidation
   * @covers \royhansen\PostenSignering\DigipostAPIMetadata
   * @covers \royhansen\PostenSignering\DigipostAPIMetadata::create
   */
   public function testCreate(): void {
     $metadata = DigipostAPIMetadata::create('test', 'https://completion-url', 'https://rejection-url', 'https://error-url', 'test-queue..');
     $this->assertNotEmpty($metadata);
     $doc = new DOMDocument;
     $this->assertTrue($doc->loadXML($metadata), 'DOMDocument::loadXML did not return TRUE');
     $this->assertTrue($doc->schemaValidate(__DIR__ . '/../resources/xsd/manifest/direct.xsd'), 'DOMDocument::schemaValidate did not return TRUE');
   }

}
