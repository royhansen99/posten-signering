<?php

declare(strict_types=1);

namespace royhansen\PostenSignering;

use DOMDocument;

class DigipostAPIMetadata {
  private const TEMPLATE = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<direct-signature-job-request xmlns="http://signering.posten.no/schema/v1">
    <reference>%s</reference>
    <exit-urls>
        <completion-url>%s</completion-url>
        <rejection-url>%s</rejection-url>
        <error-url>%s</error-url>
    </exit-urls>
</direct-signature-job-request>';


  public static function create(string $reference, string $completion, string $rejection, string $error, ?string $queue = null): string|false {
    $xmlContent = sprintf(self::TEMPLATE, $reference, $completion, $rejection, $error);
    $doc = new DOMDocument;
    $doc->loadXML($xmlContent);

    if ($queue) {
      $pollingQueue = $doc->createElement('polling-queue', $queue);
      $doc->documentElement->appendChild($pollingQueue);
    }

    return $doc->saveXML();
  }
}
