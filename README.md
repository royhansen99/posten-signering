### posten-signering

A PHP library for sending documents to the `Posten Signering` digital signature service.  
The signature service is used by companies to request digital signatures from their customers.

This is a pretty old project that I decided to revive.  
I've added stronger typings but have not tested my changes against the Posten API.  

This library includes a customized fork of `rmccue/requests`.  
I could not find a http library that would allow you to manually specify a common-name to  
verify ssl against, so I decided to manually fork and modify an existing library.  
This is a special requirement for the posten API.

Read more about `Posten Signering` here:  
https://signering.posten.no/virksomhet/api

# Usage 

***Creating the signature package***
```php
<?php
use royhansen\PostenSignering\SignaturePackage;
use royhansen\PostenSignering\Files;

# Create 'Files' object containing the files to be signed.
$files = new Files();
$files->add('file-to-sign.pdf', file_get_contents('/path/to/file-to-sign.pdf'), 'Document Title', 'Document description..');

# Create signature package and write it to the specified file path.
$package = new SignaturePackage('/path/to/key.pem', '/path/to/cert.pem', 'key-password-optional');
$package->create('/path/to/signature-package-to-create.zip', 'sender-organization-number', 'reference', 'testing..', $files);
```
\
***Create signature job through (API)***
```php
<?php
use royhansen\PostenSignering\DigipostAPI;

# Initialize API object.
$api = new DigipostAPI('sender-organization-number', '/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca-root.pem', 'key-password-optional');

# Create siganture job
// `completion-url` is where the user will be redirected after completing the signature.
// `rejection-url` is where the user will be redirected when the signature was rejected.
// `error-url` is where the user will be redirected when there was an error when signing.
$create = $api->createSignatureJob('reference', 'https://completion-url', 'https://rejection-url', 'https://error-url', $files);

# $create will contain array converted from XML values received from the signature API.
# ['signature-job-id' => 1, 'redirect-url' => 'https://posten-signature-url', 'status-url' => 'https://posten-status-url']
```
\
***Get status for signature job (API)***
```php
<?php
use royhansen\PostenSignering\DigipostAPI;

# Initialize API object.
$api = new DigipostAPI('sender-organization-number', '/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca-root.pem', 'key-password-optional');

# Get status
$status = $api->getSignatureJobStatus('https://posten-status-url', 'status-token-value');

# $status will contain array converted from XML values received from the signature API.
# ['signature-job-id' => 1, 'signature-job-status' => 'COMPLETED_SUCCESSFULLY', 'status' => 'SIGNED', 'confirmation-url' => 'https://posten-confirmation-url', 'xades-url' => 'https://posten-xades-url', 'pades-url' => 'https://posten-pades-url']
```
\
***Download a signed PAdES and XAdES (API)***
```php
<?php
use royhansen\PostenSignering\DigipostAPI;

# Initialize API object.
$api = new DigipostAPI('sender-organization-number', '/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca-root.pem', 'key-password-optional');

# Get XAdES
$xades = $api->getXades('https://posten-xades-url');

# Get PAdES
$pades = $api->getPades('https://posten-pades-url');

# $xades will contain the XAdES .xml-document as a string.
# $pades will contain the PAdES .pdf-document as a string.
```
\
***Complete the signature job (API)***
```php
<?php
use royhansen\PostenSignering\DigipostAPI;

# Initialize API object.
$api = new DigipostAPI('sender-organization-number', '/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca-root.pem', 'key-password-optional');

# Confirm signature job
$api->confirmJob('https://posten-confirmation-url');
```

# Testing 
The library contains a number of tests (phpunit) that can be run to ensure that everything works as expected.

***In order to test against API, a file named '.test' must be created in the root directory of the library with the following content:***
```
.test:
certificate=resources/private/cert.pem
key=resources/private/key.pem
ca=resources/private/ca.pem
password=key-password
organization=organization-number
```
The paths must point to existing certificate files that can for example be placed in the folder "resources/private/".

***Run tests with composer:***
```
#####
# Test creation of the signature package, without validation
composer test

# Test creation of signatures.xml and manifest.xml and validate against XML schema.
composer test-xml

# Test calls to the Posten signing API.
composer test-api

# Run all tests
composer test-all

# Run all tests and generate test statistics
composer test-coverage

# Run phpstan
composer phpstan
#####
```
