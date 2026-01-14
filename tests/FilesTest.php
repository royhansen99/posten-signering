<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use royhansen\PostenSignering\Files;

class FilesTest extends TestCase {
  /**
   * @covers \royhansen\PostenSignering\Files
   */
  public function testAdd(): void {
    $files = new Files;

    $filenames = ['test.pdf', 'test.xml', 'test.txt', 'test.zip'];
    $mimes = ['application/pdf', 'application/xml', 'text/plain', 'application/octet-stream'];
    $keys = [
      'content' => 'content..',
      'title' => 'A title..',
      'description' => 'A description..',
    ];

    foreach($filenames as $f) {
      $files->add($f, $keys['content'], $keys['title'], $keys['description']);
    }

    $this->assertEquals(count($filenames), count($files));

    foreach($files as $key => $file) {
      $this->assertArrayHasKey('name', $file);
      $this->assertEquals($filenames[$key], $file['name']);
      $this->assertArrayHasKey('mime', $file);
      $this->assertEquals($mimes[$key], $file['mime']);

      foreach($keys as $key => $value) {
        $this->assertArrayHasKey($key, $file);
        $this->assertEquals($value, $file[$key]);
      }
    }
  }

  /**
   * @covers \royhansen\PostenSignering\Files::add
   */
  public function testAddInvalidFileType(): void {
    $files = new Files;
    $this->expectException(Exception::class);
    $files->add('test.exe', 'content');
  }
}
