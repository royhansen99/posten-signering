<?php

declare(strict_types=1);

namespace royhansen\PostenSignering;

use IteratorAggregate, ArrayIterator, Countable, Exception;

/**
 * @implements IteratorAggregate<int, array{
 *   name: string, mime: string, content: string,
 *   title: ?string, description: ?string
 * }>
 */
class Files implements IteratorAggregate, Countable {
  const FILE_TYPES = [
    'pdf' => 'application/pdf',
    'xml' => 'application/xml',
    'txt' => 'text/plain',
    'zip' => 'application/octet-stream'
  ];

  /**
   * @var array<int, array{
   *   name: string, mime: string, content: string,
   *   title: ?string, description: ?string
   * }>
   */
  private array $files = [];

  public function count(): int {
    return count($this->files);
  }

  /**
    * @return ArrayIterator<int, array{
    *   name: string, mime: string, content: string,
    *   title: ?string, description: ?string
    * }>
   */
  public function getIterator(): ArrayIterator {
    $files = $this->files;
    return new ArrayIterator($files);
  }

  public function add(string $name, string $content, ?string $title = null,
    ?string $description = null
  ): void {
    $extension = explode('.', $name);
    $extension = strtolower(end($extension));

    if(!in_array($extension, array_keys(self::FILE_TYPES))) {
      throw new Exception('Invalid document');
    }

    $mime = self::FILE_TYPES[$extension];

    $file = [
      'name' => $name,
      'mime' => $mime,
      'content' => $content,
      'title' => $title,
      'description' => $description
    ];

    array_push($this->files, $file);
  }
}
