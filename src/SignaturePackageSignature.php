<?php

declare(strict_types=1);

namespace royhansen\PostenSignering;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use DOMDocument, DOMElement, Exception;

class SignaturePackageSignature {
  const VERSION = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
  const NAMESPACE = 'http://uri.etsi.org/2918/v1.2.1';
  const PROPERTIES_NAMESPACE = 'http://uri.etsi.org/01903/v1.3.2#';
  CONST PROPERTIES_NAMESPACE2 = 'http://www.w3.org/2000/09/xmldsig#';
  const TEMPLATE = self::VERSION . '<XAdESSignatures xmlns="http://uri.etsi.org/2918/v1.2.1#"></XAdESSignatures>';

  private XMLSecurityKey $objKey;
  private string $certificate;
  private string $certDigest;
  private string $signingCertificate;

  public function __construct(string $key, string $certificate, ?string $password = null) {
    $this->certificate = $certificate;

    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
    if($password) {
      $objKey->passphrase = $password;
    }
    $objKey->loadKey($key);
    $this->objKey = $objKey;

    $this->testKeyAndCertificate();
  }

  private function testKeyAndCertificate(): void {
    if(!$this->objKey->key) {
      throw new Exception('Invalid key.');
    }

    $testCertificate = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'public'));
    $testCertificate->loadKey($this->certificate, false, true);
    if(!$testCertificate->getX509Certificate()) {
      throw new Exception('Invalid certificate.');
    }

    $this->certDigest = base64_encode((string)hex2bin($testCertificate->getX509Thumbprint()));
    $this->signingCertificate = $testCertificate->getX509Certificate();
  }

  private function newDoc(): DOMDocument {
    $doc = new DOMDocument;
    $doc->loadXML(self::TEMPLATE);

    return $doc;
  }

  private function newObjDSig(): XMLSecurityDSig {
    $objDSig = new XMLSecurityDSig('');
    // @phpstan-ignore method.nonObject 
    $objDSig->sigNode->setAttribute('Id', 'Signature');
    $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);
    $objDSig->add509Cert($this->certificate);

    return $objDSig;
  }

  private function addFileReference(XMLSecurityDSig $objDSig, string $name, string $content, int $id): void {
    $reference = $objDSig->createNewSignNode('Reference');
    $reference->setAttribute('URI', $name);
    $reference->setAttribute('Id', "ID_$id");

    $digestMethod = $objDSig->createNewSignNode('DigestMethod');
    $digestMethod->setAttribute('Algorithm', XMLSecurityDSig::SHA256);
    $reference->appendChild($digestMethod);

    $value = $objDSig->calculateDigest(XMLSecurityDSig::SHA256, $content);
    $digestValue = $objDSig->createNewSignNode('DigestValue', $value);

    $reference->appendChild($digestValue);

    // @phpstan-ignore method.nonObject, method.nonObject
    $objDSig->sigNode->getElementsByTagName('SignedInfo')[0]->appendChild($reference);
  }

  private function addInternalReference(XMLSecurityDSig $objDSig, DOMElement $signedProperties): void {
    // Wrong definition in upstream library, it expects $signedProperties
    // to be 'DOMDocument', but the correct type is 'DOMElement'. So
    // ignoring next line.
    // @phpstan-ignore argument.type 
    $objDSig->addReference($signedProperties, XMLSecurityDSig::SHA256, ['http://www.w3.org/TR/2001/REC-xml-c14n-20010315'], ['overwrite' => false]);
    // @phpstan-ignore property.nonObject, method.nonObject, method.nonObject 
    $objDSig->sigNode->getElementsByTagName('SignedInfo')[0]->lastChild->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
  }

  private function addFiles(XMLSecurityDSig $objDSig, Files $files): void {
    $id = 0;
    foreach($files as $file) {
      $this->addFileReference($objDSig, $file['name'], $file['content'], $id++);
    }
  }

  private function addSignedProperties(XMLSecurityDSig $objDSig, Files $files): void {
    $object = $objDSig->createNewSignNode('Object');
    $objDSig->sigNode->appendChild($object);

    $qualifyingProperties = $objDSig->sigNode->ownerDocument->createElementNS(null, 'QualifyingProperties');
    $object->appendChild($qualifyingProperties);
    $qualifyingProperties->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://uri.etsi.org/01903/v1.3.2#');
    $qualifyingProperties->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns2', 'http://www.w3.org/2000/09/xmldsig#');
    $qualifyingProperties->setAttribute('Target', '#Signature');

    $signedProperties = $objDSig->sigNode->ownerDocument->createElement('SignedProperties');
    $signedProperties->setAttribute('Id', 'SignedProperties');
    $qualifyingProperties->appendChild($signedProperties);

    $signedSignatureProperties = $objDSig->sigNode->ownerDocument->createElementNS(null, 'SignedSignatureProperties');
    $signedProperties->appendChild($signedSignatureProperties);

    $milliseconds = substr(explode(".", (string)microtime(true))[1], 0, 3);
    $dateTime = date("Y-m-d\TH:i:s.{$milliseconds}P", time());
    $signingTime = $objDSig->sigNode->ownerDocument->createElementNS(null, 'SigningTime', $dateTime);
    $signedSignatureProperties->appendChild($signingTime);

    ### Add Certificate information

    $signingCertificate = $objDSig->sigNode->ownerDocument->createElementNS(null, 'SigningCertificate');
    $signedSignatureProperties->appendChild($signingCertificate);

    $cert = $objDSig->sigNode->ownerDocument->createElementNS(null, 'Cert');
    $signingCertificate->appendChild($cert);

    $certDigest = $objDSig->sigNode->ownerDocument->createElementNS(null, 'CertDigest');
    $cert->appendChild($certDigest);

    $digestMethod = $objDSig->sigNode->ownerDocument->createElement('ns2:DigestMethod');
    $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
    $certDigest->appendChild($digestMethod);

    $digestValue = $objDSig->sigNode->ownerDocument->createElement('ns2:DigestValue', $this->certDigest);
    $certDigest->appendChild($digestValue);
    $parseCert = openssl_x509_parse($this->signingCertificate);

    /** 
     * @var array{
     *   serialNumber: string,
     *   issuer: string | array<string, string>
     * } $certInfo
     */
    $certInfo = $parseCert;

    $serialNumberValue = $certInfo['serialNumber'];
    if(is_array($certInfo['issuer'])) {
      $issuer = [];
      foreach($certInfo['issuer'] as $key => $value) {
        array_unshift($issuer, "$key=$value");
      }
      $issuer = implode(',', $issuer);
    } else {
      $issuer = $certInfo['issuer'];
    }

    $issuerSerial = $objDSig->sigNode->ownerDocument->createElementNS(null, 'IssuerSerial');
    $cert->appendChild($issuerSerial);

    $issuerName = $objDSig->sigNode->ownerDocument->createElement('ns2:X509IssuerName', $issuer);
    $issuerSerial->appendChild($issuerName);

    $serialNumber = $objDSig->sigNode->ownerDocument->createElement('ns2:X509SerialNumber', $serialNumberValue);
    $issuerSerial->appendChild($serialNumber);

    ### Add metadata for file references

    $signedDataObjectProperties = $objDSig->sigNode->ownerDocument->createElementNS(null, 'SignedDataObjectProperties');
    $signedProperties->appendChild($signedDataObjectProperties);

    $id = 0;
    foreach($files as $file) {
      $dataObjectFormat = $objDSig->sigNode->ownerDocument->createElementNS(null, 'DataObjectFormat');
      $dataObjectFormat->setAttribute('ObjectReference', "#ID_$id");
      $signedDataObjectProperties->appendChild($dataObjectFormat);

      $mime = $objDSig->sigNode->ownerDocument->createElementNS(null, 'MimeType', $file['mime']);
      $dataObjectFormat->appendChild($mime);
      $id++;
    }

    $this->addInternalReference($objDSig, $signedProperties);
  }

  private function sign(XMLSecurityDSig $objDSig, DOMDocument $doc): void {
    $objDSig->sign($this->objKey);
    assert($doc->documentElement instanceof DOMElement);
    $objDSig->appendSignature($doc->documentElement);
  }

  public function create(Files $files): string {
    $doc = $this->newDoc();
    $objDSig = $this->newObjDSig();

    $this->addFiles($objDSig, $files);
    $this->addSignedProperties($objDSig, $files);
    $this->sign($objDSig, $doc);

    return self::VERSION . $doc->C14N(false, false);
  }
}
