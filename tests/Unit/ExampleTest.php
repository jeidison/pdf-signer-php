<?php

use Jeidison\PdfSigner\Signer;

test('example2', function () {
    $certs = [
        ['path' => __DIR__ . '/../../file-tests/.pfx', 'password' => ''],
        ['path' => __DIR__ . '/../../file-tests/.pfx', 'password' => ''],
        ['path' => __DIR__ . '/../../file-tests/.pfx', 'password' => ''],
        ['path' => __DIR__ . '/../../file-tests/.pfx', 'password' => ''],
    ];

    $fileContent = file_get_contents(__DIR__ . '/../../file-tests/pdf.pdf');
    foreach ($certs as $cert) {
        $fileContent = Signer::new()
            ->withCertificate($cert['path'], $cert['password'])
            ->withContent($fileContent)
            ->sign();
    }

    file_put_contents(__DIR__ . '/../../file-tests/signed-1.pdf', $fileContent);
    expect(true)->toBeTrue();
});


