<?php

declare(strict_types=1);

namespace royhansen\PostenSignering;

use ZipArchive, Error;

class SignaturePackage {
  private SignaturePackageSignature $signatureXML;

  const SIGNATURE_FILENAME = 'signatures.xml';
  const MANIFEST_FILENAME = 'manifest.xml';

  public function __construct(string $key, string $certificate, ?string $password = null) {
    $key = file_get_contents($key);
    $certificate = file_get_contents($certificate);

    if(!$key) throw new Error("$key is empty");
    if(!$certificate) throw new Error("$certificate is empty");

    $this->signatureXML = new SignaturePackageSignature($key, $certificate, $password);
  }

  private function createZip(string $outputFile, Files $files): void {
    $zip = new ZipArchive;
    $zip->open($outputFile, ZipArchive::OVERWRITE);
    $zip->addEmptyDir('META-INF');

    foreach($files as $file) {
      $zip->addFromString($file['name'], $file['content']);
    }

    $zip->close();
  }

  public function create(string $outputFile, string $senderOrganizationNumber, string $referenceType, string $reference, Files $files): void {
    $files = clone $files;

    $manifestFile = SignaturePackageManifest::create($referenceType, $reference, $senderOrganizationNumber, $files);

    $files->add(self::MANIFEST_FILENAME, $manifestFile);

    $signatureFile = $this->signatureXML->create($files);

    $files->add('META-INF/' . self::SIGNATURE_FILENAME, $signatureFile);

    $this->createZip($outputFile, $files);
  }
}
