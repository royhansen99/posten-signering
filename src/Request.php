<?php

declare(strict_types=1);

namespace royhansen\PostenSignering;

use Exception;

enum Method: string {
  case POST = "POST";
  case GET = "GET";
}

class Request {
  private ?string $ca_path = null;
  private ?string $cert_path = null;
  private ?string $key_path = null;
  private ?string $key_password = null;
  private ?string $common_name = null;

  public function __construct(?string $ca_path = null, ?string $cert_path = null, ?string $key_path = null, ?string $common_name = null, ?string $key_password = null) {
    $this->ca_path = $ca_path;
    $this->cert_path = $cert_path;
    $this->key_path = $key_path;
    $this->key_password = $key_password;
    $this->common_name = $common_name;
  }

  private function parseChunks(string $data): string
  {
    $body = '';
    $offset = 0;

    while (true) {
      // Find the next CRLF (end of chunk size line)
      $pos = strpos($data, "\r\n", $offset);

      if ($pos === false) break; // malformed

      // Read chunk size (hex)
      $hex = substr($data, $offset, $pos - $offset);
      $hex = explode(';', $hex, 2)[0];
      $chunkSize = (int)hexdec(trim($hex));

      $offset = $pos + 2;

      // Final chunk
      if ($chunkSize === 0) break;

      // Read chunk data
      $body .= substr($data, $offset, $chunkSize);
      $offset += $chunkSize + 2; // skip data + CRLF
    }

    return $body;
  }

  /**
   * @param ?array<string, string> $headers
   */
  private function request(Method $method, string $url, string $requireContentType = 'application/xml', ?array $headers = null, ?string $body = null): string {
    $is_https = str_starts_with($url, "https://");

    preg_match("/http(s|):\/\/([^\/\?\#]*)(.*)$/", $url, $matches);

    $hostname = $matches[2];
    $pathAndParams = $matches[3];

    if(!$pathAndParams) $pathAndParams = '/';

    // 1. Establish the connection (Handshake happens here)
    $context = stream_context_create([
        "ssl" => [
            "capture_peer_cert" => true,
            ...($this->ca_path ? [
              "verify_peer" => true,
              "cafile" => $this->ca_path,
            ] : []),
            ...($this->cert_path && $this->key_path ? [
            "local_cert" => $this->cert_path,
            "local_pk"   => $this->key_path,
            "passphrase" => $this->key_password,
            ] : [])
        ]
    ]);

    $fp = stream_socket_client(($is_https ? 'ssl' : 'tcp') . "://$hostname:443", $errno,
      $errstr, 30, STREAM_CLIENT_CONNECT, $context);

    if (!$fp) die("Connection failed: $errstr");

    /*
     * @var array{options: array{ssl: array{peer_certificate: string}}} $params
     */
    $params = stream_context_get_params($fp);

    if($is_https) {
      /** @var array{peer_certificate: string} $sslOptions */
      $sslOptions = $params['options']['ssl'];

      $parseCert = openssl_x509_parse($sslOptions['peer_certificate']);

      if(!$parseCert) {
        fclose($fp);
        throw new Exception('Unable to parse certificate');
      }

      // Verify server certificate common name 

      /** 
       * @var array{
       *   subject: array{CN: string},
       *   extensions: array{
       *     subjectAltName: ?string 
       *   }
       * } $certDetails
       */
      $certDetails = $parseCert;

      $commonName =  $certDetails['subject']['CN'];
      if(!$this->common_name) {
        $found = false;

        if($commonName == $hostname) {
          $found = true;
        } elseif(isset($certDetails['extensions']['subjectAltName'])) {
          $altNames = explode(',', $certDetails['extensions']['subjectAltName']);
          foreach($altNames as $value) {
            $value = trim($value);
            if(!str_starts_with($value, 'DNS:')) continue;

            $name = trim(substr($value, 4)); 
            if($name == $hostname) {
              $found = true;
              break;
            }
          }
        }

        if(!$found) {
          fclose($fp);
          throw new Exception("SSL: Common Name mismatch \"{$hostname}\"");
        }
      } else {
        if ($commonName !== $this->common_name) {
          fclose($fp);
          throw new Exception("SSL: Common Name mismatch \"{$this->common_name}\"");
        }
      }
    }

    if($requireContentType)
      $headers['Accept'] = $requireContentType;

    $request = "{$method->name} {$pathAndParams} HTTP/1.1\r\n";
    $request .= "Host: {$hostname}\r\n";

    if($headers) {
      foreach($headers as $key => $value)
        $request .= "{$key}: {$value}\r\n";
    }

    if ($method == Method::POST && $body) {
      if($headers && !isset($headers['Content-Type']))
        $request .= "Content-Type: text/plain\r\n";

      $request .= "Content-Length: " . strlen($body) . "\r\n";
      $request .= "Connection: close\r\n\r\n";
      $request .= $body;
    } else {
      $request .= "Connection: close\r\n\r\n";
    }

    fwrite($fp, $request);

    $statusChecked = false;
    $contentTypeChecked = false;
    $chunked = false;

    while (!feof($fp)) {
      $line = trim(fgets($fp) ?: '');

      // End of headers
      if($line == "") break;

      $line = strtolower($line);

      // Capture Status Code from first line
      if (!$statusChecked && str_starts_with($line, 'http/')) {
        $statusChecked = true;
        $parts = explode(' ', $line, 3);
        $responseCode = $parts[1];

        if($responseCode !== '200') {
          fclose($fp);
          throw new Exception("Invalid HTTP response code: \"$responseCode\"");
        }
      } elseif (!$contentTypeChecked && str_starts_with($line, 'content-type:')) {
        $contentTypeChecked = true;
        $contentType = trim(explode(';', explode(':', $line, 2)[1])[0]);

        if($contentType !== $requireContentType) {
          fclose($fp);
          throw new Exception("Invalid Content-Type: \"$contentType\"");
        }
      } elseif (str_starts_with($line, 'transfer-encoding: chunked')) {
        $chunked = true;
      }
    }

    if(!$statusChecked)
      throw new Exception("HTTP response code is missing");

    if(!$contentTypeChecked)
      throw new Exception("Content-Type is missing");

    // Headers not included in $body, since using fgets()
    // above to move pointer past header lines.
    $body = stream_get_contents($fp);
    fclose($fp);

    if(!$body) throw new Exception("Invalid response");

    return $chunked ? $this->parseChunks($body) : $body;
  }

  public function get(string $url, string $requireContentType): string {
    return $this->request(Method::GET, $url, $requireContentType);
  }

  /**
   * @param ?array<string, string> $headers
   */
  public function post(string $url, string $requireContentType, ?array $headers = null, ?string $body = null): string {
    return $this->request(Method::POST, $url, $requireContentType, $headers, $body);
  }
}
