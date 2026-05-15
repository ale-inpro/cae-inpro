<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cliente SOAP mínimo para CotejoInternetV1 (AEAT).
 * Documentación: CotejoInternetV1.pdf — soapAction vacío, TLS mutuo con certificado cliente.
 */
final class AeatCotejoInternetService
{

    /**
     * @param array{
     *   endpoint: string,
     *   client_cert_path: string,
     *   client_cert_password: string,
     *   ca_bundle?: string,
     * } $config
     * @return array{
     *   ok: bool,
     *   http_code: int,
     *   raw_response?: string,
     *   curl_error?: string,
     *   codigo?: string,
     *   descripcion?: string,
     *   csv_sustituto?: string|null,
     *   binario_base64?: string|null,
     *   algoritmo?: string|null,
     *   huella?: string|null,
     * }
     */
    public function cotejar(string $csv16, bool $eni = false, array $config = []): array
    {
        $csv16 = strtoupper(trim($csv16));
        if (strlen($csv16) !== 16 || !ctype_alnum($csv16)) {
            return ['ok' => false, 'http_code' => 0, 'curl_error' => 'CSV debe ser 16 caracteres alfanuméricos'];
        }

        $useMock = !empty($config['use_mock']);
        if ($useMock) {
            $scenario = (string) ($config['mock_scenario'] ?? 'success');
            $mockSha = strtoupper((string) ($config['mock_sha1_for_file'] ?? ''));
            $raw = $this->buildMockSoapResponse($csv16, $scenario, $mockSha);
            $http = 200;
            $parsed = $this->parseCotejoResponse($raw);
            return array_merge([
                'ok' => ($parsed['codigo'] ?? '') === '1',
                'http_code' => $http,
                'raw_response' => $raw,
            ], $parsed);
        }

        $endpoint = (string) ($config['endpoint'] ?? '');
        $certPath = (string) ($config['client_cert_path'] ?? '');
        $certPass = (string) ($config['client_cert_password'] ?? '');
        $caBundle = isset($config['ca_bundle']) ? (string) $config['ca_bundle'] : '';

        if ($endpoint === '' || $certPath === '' || !is_file($certPath)) {
            return ['ok' => false, 'http_code' => 0, 'curl_error' => 'Falta endpoint o certificado (ruta inválida)'];
        }

        $eniXml = $eni ? '<cot:ENI>S</cot:ENI>' : '';
        $body = $this->dedentXml(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:cot="https://www3.agenciatributaria.gob.es/static_files/common/internet/dep/aduanas/es/aeat/kata/apli/ws/cotejo_request_int_V1.xsd">
<soapenv:Header/>
<soapenv:Body>
<cot:peticionDocumento>
<cot:CSV>{$this->xmlText($csv16)}</cot:CSV>
{$eniXml}
</cot:peticionDocumento>
</soapenv:Body>
</soapenv:Envelope>
XML);

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'http_code' => 0, 'curl_error' => 'curl_init falló'];
        }

        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ""',
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_KEYPASSWD => $certPass,
        ]);

        if ($caBundle !== '' && is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'http_code' => $http, 'curl_error' => $cerr !== '' ? $cerr : 'curl_exec falló'];
        }

        $parsed = $this->parseCotejoResponse($raw);

        return array_merge([
            'ok' => $http >= 200 && $http < 300 && ($parsed['codigo'] ?? '') === '1',
            'http_code' => $http,
            'raw_response' => $raw,
        ], $parsed);
    }

    private function xmlText(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * @return array{
     *   codigo?: string,
     *   descripcion?: string,
     *   csv_sustituto?: string|null,
     *   binario_base64?: string|null,
     *   algoritmo?: string|null,
     *   huella?: string|null,
     * }
     */
    private function parseCotejoResponse(string $xml): array
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return ['codigo' => '0', 'descripcion' => 'Respuesta no es XML válido'];
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        $codigo = $this->firstTextContent($xp, '//*[local-name()="mensajeSalida"]/*[local-name()="codigo"]');
        $desc = $this->firstTextContent($xp, '//*[local-name()="mensajeSalida"]/*[local-name()="descripcion"]');
        $csvSust = $this->firstTextContent($xp, '//*[local-name()="mensajeSalida"]/*[local-name()="csvSustituto"]');

        $huella = $this->firstTextContent($xp, '//*[local-name()="cotejoResponse"]/*[local-name()="documento"]/*[local-name()="huella"]');
        $algo = $this->firstTextContent($xp, '//*[local-name()="cotejoResponse"]/*[local-name()="documento"]/*[local-name()="algoritmo"]');
        $bin = $this->firstTextContent($xp, '//*[local-name()="cotejoResponse"]/*[local-name()="documento"]/*[local-name()="binario"]');

        $out = [];
        if ($codigo !== null) {
            $out['codigo'] = $codigo;
        }
        if ($desc !== null) {
            $out['descripcion'] = $desc;
        }
        if ($csvSust !== null && $csvSust !== '') {
            $out['csv_sustituto'] = $csvSust;
        }
        if ($bin !== null && $bin !== '') {
            $out['binario_base64'] = $bin;
        }
        if ($algo !== null && $algo !== '') {
            $out['algoritmo'] = $algo;
        }
        if ($huella !== null && $huella !== '') {
            $out['huella'] = $huella;
        }

        return $out;
    }

    private function firstTextContent(\DOMXPath $xp, string $query): ?string
    {
        $n = $xp->query($query)->item(0);
        if ($n === null) {
            return null;
        }
        $t = trim($n->textContent);
        return $t === '' ? null : $t;
    }

    private function buildMockSoapResponse(string $csv16, string $scenario, string $sha1Upper): string
    {
        $ns = 'https://www3.agenciatributaria.gob.es/static_files/common/internet/dep/aduanas/es/aeat/kata/apli/ws/cotejo_response_int_V1.xsd';
        switch ($scenario) {
            case 'not_found':
                return $this->mockEnvelope($ns, '3', 'No se ha encontrado el CSV indicado', null, null, null);
            case 'revoked':
                return $this->mockEnvelope($ns, '4', 'Documento anulado', 'TA5KHH2FQB9SDTZA', null, null);
            case 'not_cotejable':
                return $this->mockEnvelope($ns, '5', 'Documento no cotejable', null, null, null);
            case 'transport_error':
                return $this->mockEnvelope($ns, '100', 'Error al recuperar el documento, reinténtelo más tarde', null, null, null);
            case 'success':
            default:
                $huella = $sha1Upper !== '' ? $sha1Upper : '0' . str_repeat('A', 39);
                return $this->mockEnvelope($ns, '1', 'Correcto', null, $huella, 'SHA-1');
        }
    }

    private function mockEnvelope(
        string $ns,
        string $codigo,
        string $descripcion,
        ?string $csvSust,
        ?string $huella,
        ?string $algoritmo
    ): string {
        $extraSust = $csvSust !== null && $csvSust !== ''
            ? "<cot:csvSustituto>{$this->xmlText($csvSust)}</cot:csvSustituto>"
            : '';
        $docBlock = ($huella !== null && $huella !== '')
            ? "<cot:documento><cot:huella>{$this->xmlText($huella)}</cot:huella><cot:algoritmo>{$this->xmlText((string) $algoritmo)}</cot:algoritmo><cot:binario></cot:binario></cot:documento>"
            : '';
        return $this->dedentXml(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
<env:Body>
<cot:cotejoResponse xmlns:cot="{$ns}">
<cot:mensajeSalida>
<cot:codigo>{$this->xmlText($codigo)}</cot:codigo>
<cot:descripcion>{$this->xmlText($descripcion)}</cot:descripcion>
{$extraSust}
</cot:mensajeSalida>
{$docBlock}
</cot:cotejoResponse>
</env:Body>
</env:Envelope>
XML);
    }

    /** Quita sangría accidental en heredocs (XML debe empezar limpio en cada línea). */
    private function dedentXml(string $xml): string
    {
        return preg_replace('/^[ \t]+/m', '', trim($xml));
    }
}