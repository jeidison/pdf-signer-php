# PHP-SIGNER

Esse é um pacote escrito inteiramente em PHP para assinar PDF's com suporte a multiplas assinaturas.

## INSTALAÇÃO:
```bash
composer require jeidison/pdf-signer
```

## EXEMPLOS:

Assinando documentos:
```php
$pathFileCertificate = 'path_do_seu_arquivo.pfx';
$passwordCertificate = 'senha do seu pfx';
$fileContent = file_get_contents('path_do_seu_certificado.pdf');

$fileContentSigned = Signer::new()
        ->withCertificate($pathFileCertificate, $passwordCertificate)
        ->withContent($fileContent)
        ->sign();

file_put_contents('path_onde_vc_quer_salvar_o_seu_pdf.pdf', $fileContentSigned);
```

Assinando documentos com metadados(Razão, Nome, Local, Informações de contato):
```php
$pathFileCertificate = 'path_do_seu_arquivo.pfx';
$passwordCertificate = 'senha do seu pfx';
$fileContent = file_get_contents('path_do_seu_certificado.pdf');

$fileContentSigned = Signer::new()
        ->withCertificate($pathFileCertificate, $passwordCertificate)
        ->withContent($fileContent)
        ->withMetadata(
            Metadata::new()
                ->withReason('ASSINATURA DE DOCUMENTOS PARA TESTES.')
                ->withName('JEIDISON SANTOS FARIAS')
                ->withLocation('Araras/SP')
                ->withContactInfo('Jeidison Farias <jeidison.farias@gmail.com>')
        )
        ->sign();

file_put_contents('path_onde_vc_quer_salvar_o_seu_pdf.pdf', $fileContentSigned);
```

Assinando documentos com assinatura visível:
```php
$pathFileCertificate = 'path_do_seu_arquivo.pfx';
$passwordCertificate = 'senha do seu pfx';
$fileContent = file_get_contents('path_do_seu_certificado.pdf');

$fileContentSigned = Signer::new()
        ->withCertificate($pathFileCertificate, $passwordCertificate)
        ->withContent($fileContent)
        ->withSignatureAppearance(
            SignatureAppearance::new()
                ->withImage('/path_do_seu_icone.png')
                ->withRect([350, 770, 400, 820])
        )
        ->sign();

file_put_contents('path_onde_vc_quer_salvar_o_seu_pdf.pdf', $fileContentSigned);
```

Adicionando carimbo de tempo na assinatura:
```php
// Ainda não implementado.
```

## Credits
- [Jeidison Farias](https://github.com/jeidison)
- [All Contributors](../../contributors)