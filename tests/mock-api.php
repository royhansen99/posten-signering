<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

if($method === 'POST') {
  $body = file_get_contents('php://input');
  echo $body;
} elseif($path == '/not-found') {
  http_response_code(404); 
  echo '404 Not found';
} else {
  if(is_string($path)) echo $path;
}
