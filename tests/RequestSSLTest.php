<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\Request;
use function royhansen\PostenSignering\Tests\getConfig;

class RequestSSLTest extends TestCase {
  /**
   * A basic SSL request.
   * @group ssl 
   * @covers \royhansen\PostenSignering\Request::get
   */
  public function testGet(): void {
    $api = new Request();
    $result = $api->get(getConfig()['url'], "text/html");

    $this->assertNotEmpty($result);
  }

  /**
   * Test SSL with a valid common name for the target site.
   * @group ssl 
   * @covers \royhansen\PostenSignering\Request::get
   */
  public function testGetWithCommonName(): void {
    $api = new Request(null, null, null, getConfig()['url_common_name']);
    $result = $api->get(getConfig()['url'], "text/html");

    $this->assertNotEmpty($result);
  }

  /**
   * Test SSL with invalid common name.
   * @group ssl 
   * @covers \royhansen\PostenSignering\Request::get
   */
  public function testGetCommonNameError(): void {
    $api = new Request(null, null, null, "invalid-common-name.com");

    $this->expectException('Exception');

    $api->get(getConfig()['url'], "text/html");
  }

  /**
   * Test with client certificates and a CA to verify server
   * certificate.
   *
   * @group ssl 
   * @covers \royhansen\PostenSignering\Request::get
   */
  public function testGetWithCertificates(): void {
    $config = getConfig();
    $api = new Request(
      $config['ca'],
      $config['certificate'],
      $config['key'],
      $config['url_common_name'],
      $config['password']
    );
    
    $result = $api->get($config['url'], "text/html");

    $this->assertNotEmpty($result);
  }
}
