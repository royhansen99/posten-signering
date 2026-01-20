<?php

declare(strict_types=1);

namespace royhansen\PostenSignering;

use DOMDocument, Exception;

class SignaturePackageManifest {
  const VERSION = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
  const NAMESPACE = 'http://signering.posten.no/schema/v1';
  const TEMPLATE = self::VERSION . '<direct-signature-job-manifest xmlns="' . self::NAMESPACE . '"></direct-signature-job-manifest>';
  const IDENTIFIER_PERSONAL_ID = 'personalid';
  const IDENTIFIER_REFERENCE = 'reference';
  const REQUIRED_AUTHENTICATION = 3;
  const IDENTIFICATION_IN_SIGNED_DOCUMENTS = 'DATE_OF_BIRTH_AND_NAME';
  const SIGNATURE_TYPE = 'ADVANCED_ELECTRONIC_SIGNATURE';

  private DOMDocument $doc;

  private function __construct() {
    $doc = new DOMDocument;
    $doc->loadXML(self::TEMPLATE);

    $this->doc = $doc;
  }

  private function addSigner(string $identifierType, string $identifier): void {
    $signer = $this->doc->createElementNS(self::NAMESPACE, 'signer');
    $this->doc->documentElement->appendChild($signer);

    switch($identifierType) {
      case self::IDENTIFIER_PERSONAL_ID:
        $id = $this->doc->createElementNS(self::NAMESPACE, 'personal-identification-number', $identifier);
        break;
      case self::IDENTIFIER_REFERENCE:
      default:
        $id = $this->doc->createElementNS(self::NAMESPACE, 'signer-identifier', $identifier);
    }

    $signer->appendChild($id);
    $signatureType = $this->doc->createElementNS(self::NAMESPACE, 'signature-type', self::SIGNATURE_TYPE);
    $signer->appendChild($signatureType);
  }

  private function addSender(string $organizationNumber): void {
    $sender = $this->doc->createElementNS(self::NAMESPACE, 'sender');
    $this->doc->documentElement->appendChild($sender);

    $organization = $this->doc->createElementNS(self::NAMESPACE, 'organization-number', $organizationNumber);
    $sender->appendChild($organization);
  }

  private function addFile(string $name, string $mime, ?string $title = null, ?string $description = null): void {
    $document = $this->doc->createElementNS(self::NAMESPACE, 'document');
    $this->doc->documentElement->appendChild($document);
    $hrefAttribute = $this->doc->createAttribute('href');
    $hrefAttribute->value = $name;
    $document->appendChild($hrefAttribute);
    $mimeAttribute = $this->doc->createAttribute('mime');
    $mimeAttribute->value = $mime;
    $document->appendChild($mimeAttribute);

    if($title) {
      $title = $this->doc->createElementNS(self::NAMESPACE, 'title', $title);
      $document->appendChild($title);
    }

    if($description) {
      $description = $this->doc->createElementNS(self::NAMESPACE, 'description', $description);
      $document->appendChild($description);
    }
  }

  private function addFiles(Files $files): void {
    foreach($files as $file) {
      $this->addFile($file['name'], $file['mime'], $file['title'], $file['description']);
    }
  }

  private function addRequiredAuthAndSigningIdentifier(): void {
    $requiredAuth = $this->doc->createElementNS(self::NAMESPACE, 'required-authentication', (string)self::REQUIRED_AUTHENTICATION);
    $this->doc->documentElement->appendChild($requiredAuth);
    $signingIdentifier = $this->doc->createElementNS(self::NAMESPACE, 'identifier-in-signed-documents', self::IDENTIFICATION_IN_SIGNED_DOCUMENTS);
    $this->doc->documentElement->appendChild($signingIdentifier);
  }

  public static function create(string $identifierType, string $identifier,
    string $senderOrganizationNumber, Files $files
  ): string {
    if(!in_array($identifierType, [self::IDENTIFIER_PERSONAL_ID, self::IDENTIFIER_REFERENCE])) {
      throw new Exception('Invalid value in $identifierType');
    }

    $manifest = new self;
    $manifest->addSigner($identifierType, $identifier);
    $manifest->addSender($senderOrganizationNumber);
    $manifest->addFiles($files);
    $manifest->addRequiredAuthAndSigningIdentifier();

    return self::VERSION . $manifest->doc->C14N(false, false);
  }
}
