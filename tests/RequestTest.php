<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\Request;

class RequestTest extends TestCase {
  /**
   * @var resource|false $process
   */
  private static $process;

  public static function setUpBeforeClass(): void {
    // Start the built-in server as a background process
    // Uses the mock-router.php script we discussed earlier
    $command = sprintf('php -S localhost:8080 %s', __DIR__ . '/mock-api.php');

    // proc_open provides cross-platform backgrounding
    self::$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (!is_resource(self::$process)) {
        throw new \RuntimeException("Could not start mock server");
    }

    // Give the server a small window to bind to the port
    usleep(150000);
  }

  public static function tearDownAfterClass(): void
  {
    // Ensure the server is terminated after tests finish
    if (is_resource(self::$process)) {
      proc_terminate(self::$process);
      proc_close(self::$process);
    }
  }

  /**
   * A basic request.
   * @group http 
   * @covers \royhansen\PostenSignering\Request::get
   */
  public function testGet(): void {
    $api = new Request();
    $result = $api->get('http://localhost:8080/some/path', "text/html");

    $this->assertEquals($result, '/some/path');
  }

  /**
   * Test with invalid content-type.
   * @group http 
   * @covers \royhansen\PostenSignering\Request::get
   */
  public function testGetErrorContentType(): void {
    $api = new Request();

    $this->expectException('Exception');

    $api->get('http://localhost:8080', 'application/pdf');
  }

  /**
   * Test with invalid path which gives 404. 
   * @group http 
   * @covers \royhansen\PostenSignering\Request::get
   */
  public function testNotFound(): void {
    $api = new Request();

    $this->expectException('Exception');

    $api->get('http://localhost:8080/not-found', "text/html");
  }

  /**
   * Test a POST request.
   * @group http 
   * @covers \royhansen\PostenSignering\Request::post
   */
  public function testPost(): void {
    $api = new Request();

    $result = $api->post(
      'http://localhost:8080', 'text/html', null,
      'something-to-post!'
    );

    $this->assertEquals('something-to-post!', $result);
  }
}
