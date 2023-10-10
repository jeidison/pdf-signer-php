<?php

use Jeidison\PdfSigner\Metadata;
use Jeidison\PdfSigner\Signer;

test('example2', function () {
    $certs = [
        ['path' => __DIR__.'/../../file-tests/9812.pfx', 'password' => '9812'],
        ['path' => __DIR__.'/../../file-tests/jeidison1809.pfx', 'password' => 'jeidison1809'],
        ['path' => __DIR__.'/../../file-tests/msvppsvtsd1234.pfx', 'password' => 'msvppsvtsd1234'],
        ['path' => __DIR__.'/../../file-tests/certificado-1234.pfx', 'password' => '1234'],
        //        ['path' => __DIR__ . '/../../file-tests/expirado-123456.pfx', 'password' => '123456'],
    ];

    $fileContent = file_get_contents(__DIR__.'/../../file-tests/FOR-52 Comunicado de Homologação.pdf');
    foreach ($certs as $cert) {
        $fileContent = Signer::new()
            ->withCertificate($cert['path'], $cert['password'])
            ->withContent($fileContent)
            ->withMetadata(
                Metadata::new()
                    ->withReason('ASSINATURA DE DOCUMENTOS PARA TESTES.')
                    ->withName('JEIDISON SANTOS FARIAS')
                    ->withLocation('Araras/SP')
                    ->withContactInfo('Jeidison Farias <jeidison.farias@gmail.com>')
            )
            ->sign();
    }

    file_put_contents(__DIR__.'/../../file-tests/signed-1.pdf', $fileContent);
    expect(true)->toBeTrue();
});
