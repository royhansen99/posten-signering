<?php

declare(strict_types=1);

namespace royhansen\PostenSignering;

use Exception, SimpleXMLElement;

enum RequestMethod: string {
  case POST = "POST";
  case GET = "GET";
}

class DigipostAPI {
  private const DIRECT_SIGNATURE_URL_PATH = '/direct/signature-jobs';
  private const API_URL = 'https://api.signering.posten.no/api';
  private const TEST_API_URL = 'https://api.difitest.signering.posten.no/api';
  private const DIGIPOST_ORGANIZATION = 'POSTEN NORGE AS';
  private const TEST_DIGIPOST_ORGANIZATION = 'POSTEN NORGE AS';
  private const XML_RESPONSE_NAMESPACE = 'http://signering.posten.no/schema/v1';

  private string $organization;
  private string $environment;
  private Request $request;

  public function __construct(string $organization, string $certificate, string $key,
    string $ca, ?string $passphrase = null, string $environment = 'production'
  ) {
    if(!in_array($environment, ['production', 'test'])) {
      throw new Exception('Invalid environment value \"$environment\".');
    }

    $this->environment = $environment;
    $this->organization = $organization;
    $this->request = new Request($ca, $certificate, $key, $this->getDigipostOrganization(), $passphrase);
  }

  private function getDigipostOrganization(): string {
    switch($this->environment) {
      case 'production':
        return self::DIGIPOST_ORGANIZATION;
      case 'test':
      default:
        return self::TEST_DIGIPOST_ORGANIZATION;
    }
  }

  private function getDigipostAPIUrl(): string {
    $url = "";

    switch($this->environment) {
      case 'production':
        $url = self::API_URL;
        break;
      case 'test':
      default:
        $url = self::TEST_API_URL;
    }

    return $url . "/" . $this->organization;
  }

  /**
   * @param string[] $keys 
   * @param string[] $optionalKeys 
   * @return array<string, string>
   */
  private function getValuesFromXml(string $xml, array $keys = [], array $optionalKeys = []): array {
    $load = new SimpleXMLElement($xml, 0, false, self::XML_RESPONSE_NAMESPACE);

    $data = [];

    if(!$load) {
      throw new Exception("SimpleXMLElement failed to load XML: \"$xml\"");
    }

    foreach($load as $key => $value) {
      if(in_array($key, $keys) || in_array($key, $optionalKeys)) {
        $value = trim($value->__toString());
        if(!empty($value)) {
          $data[$key] = $value;
        }
      }
    }

    if($diff = array_diff($keys, array_keys($data))) {
      throw new Exception('Could not find required keys: "' . implode(', ', $diff) . '", XML: "' . $xml . '"');
    }

    return $data;
  }

  /**
   * @return array<string, string>
   */
  public function createSignatureJob(string $reference, string $completionUrl, string $rejectionUrl, string $errorUrl, Files $files, ?string $queue = null): array {
    $metadata = DigipostAPIMetadata::create($reference, $completionUrl, $rejectionUrl, $errorUrl, $queue);

    if(!$metadata) throw new Exception("Failed to create metadata");

    $files->add('metadata.xml', $metadata);

    /**
     * @var array<string, string>
     */
    $headers = [];
    $body = '';
    $boundary = sha1((string)time());
		$headers['Content-Type'] = "multipart/mixed; boundary=$boundary";

		foreach ($files as $file) {
			$body .= "--$boundary\r\n";
			$body .= "Content-Disposition: attachment\r\n";
			$body .= "Content-Type: {$file['mime']}";
			$body .= "\r\n\r\n" . $file['content'] . "\r\n";
		}

		$body .= "--$boundary--\r\n\r\n";
		$headers['Content-Length'] = (string)strlen($body);

    $post = $this->request->post($this->getDigipostAPIUrl() . self::DIRECT_SIGNATURE_URL_PATH, 'application/xml', $headers, $body);

    $data = $this->getValuesFromXml($post, ['signature-job-id', 'redirect-url', 'status-url']);

    return $data;
  }

  /**
   * @return array<string, string>
   */
  public function getSignatureJobStatus(string $url, string $statusToken): array {
    $get = $this->request->get("$url?status_query_token=$statusToken", 'application/xml');
    $data = $this->getValuesFromXml($get, ['signature-job-id', 'signature-job-status', 'status', 'confirmation-url'], ['pades-url', 'xades-url']);

    return $data;
  }

  public function confirmJob(string $url): bool {
    $this->request->post($url, 'application/xml');

    return true;
  }

  public function getXades(string $url): string {
    return $this->request->get($url, 'application/xml');
  }

  public function getPades(string $url): string {
    return $this->request->get($url, 'application/octet-stream');
  }

  public function getSigningTimeFromXades(string $content): ?string {
    $xml = new SimpleXMLElement($content);
    $signingTime = $xml->xpath('ltv:Description/ltv:SignatureDescription/xades:SigningTime');

    if($signingTime && $signingTime[0]) {
        return (string)$signingTime[0];
    } else {
        return null;
    }
  }
}
