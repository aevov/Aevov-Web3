<?php
namespace APS\Integration;

use APS\Core\Logger;

class CubbitIntegration {
    private $endpoint = 'https://s3.cubbit.eu';
    private $region = 'eu-central-1';
    private $bucket_name;
    private $access_key;
    private $secret_key;
    private $logger;

    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->bucket_name = get_option('cubbit_bucket_name');
        $this->access_key = get_option('cubbit_access_key');
        $this->secret_key = get_option('cubbit_secret_key');

        if (empty($this->bucket_name) || empty($this->access_key) || empty($this->secret_key)) {
            $this->logger->log('error', 'Cubbit credentials are not configured.');
            // Optionally, throw an exception or set an error state
        }
    }

    /**
     * Uploads data to Cubbit.
     *
     * @param string $key The object key (path in Cubbit).
     * @param string $data The data to upload.
     * @param string $content_type The content type of the data (e.g., 'application/json').
     * @param string $acl The ACL for the object (e.g., 'private', 'public-read').
     * @return bool True on success, false on failure.
     */
    public function upload_object($key, $data, $content_type = 'application/octet-stream', $acl = 'private') {
        if (!$this->is_configured()) {
            return false;
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $data);

        $headers = [
            'Host' => 's3.cubbit.eu',
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
            'x-amz-acl' => $acl
        ];

        $canonicalUri = "/{$this->bucket_name}/" . $this->urlencode_path($key);
        $canonicalQueryString = ''; // No query string for PUT object

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeaders = '';
        foreach ($headers as $header_key => $value) {
            $canonicalHeaders .= strtolower($header_key) . ':' . trim($value) . "\n";
            $signedHeaders .= strtolower($header_key) . ';';
        }
        $signedHeaders = rtrim($signedHeaders, ';');

        $canonicalRequest = "PUT\n" .
                            $canonicalUri . "\n" .
                            $canonicalQueryString . "\n" .
                            $canonicalHeaders . "\n" .
                            $signedHeaders . "\n" .
                            $payloadHash;

        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->derive_signature_key($this->secret_key, $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "{$algorithm} " .
                               "Credential={$this->access_key}/{$credentialScope}, " .
                               "SignedHeaders={$signedHeaders}, " .
                               "Signature={$signature}";

        $url = "{$this->endpoint}/{$this->bucket_name}/" . $this->urlencode_path($key);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $curlHeaders = [];
        foreach ($headers as $header_key => $value) {
            $curlHeaders[] = "{$header_key}: {$value}";
        }
        $curlHeaders[] = "Authorization: {$authorizationHeader}";
        $curlHeaders[] = "Content-Type: {$content_type}";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logger->log('info', 'Object uploaded to Cubbit successfully', ['key' => $key]);
            return true;
        } else {
            $this->logger->log('error', 'Failed to upload object to Cubbit', ['key' => $key, 'http_code' => $httpCode, 'response' => $result]);
            return false;
        }
    }

    /**
     * Downloads data from Cubbit.
     *
     * @param string $key The object key (path in Cubbit).
     * @return string|false The downloaded data on success, false on failure.
     */
    public function download_object($key) {
        if (!$this->is_configured()) {
            return false;
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        $emptyPayloadHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $headers = [
            'Host' => 's3.cubbit.eu',
            'x-amz-content-sha256' => $emptyPayloadHash,
            'x-amz-date' => $amzDate
        ];

        $canonicalUri = "/{$this->bucket_name}/" . $this->urlencode_path($key);
        $canonicalQueryString = '';

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeaders = '';
        foreach ($headers as $header_key => $value) {
            $canonicalHeaders .= strtolower($header_key) . ':' . trim($value) . "\n";
            $signedHeaders .= strtolower($header_key) . ';';
        }
        $signedHeaders = rtrim($signedHeaders, ';');

        $canonicalRequest = "GET\n" .
                            $canonicalUri . "\n" .
                            $canonicalQueryString . "\n" .
                            $canonicalHeaders . "\n" .
                            $signedHeaders . "\n" .
                            $emptyPayloadHash;

        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->derive_signature_key($this->secret_key, $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "{$algorithm} " .
                               "Credential={$this->access_key}/{$credentialScope}, " .
                               "SignedHeaders={$signedHeaders}, " .
                               "Signature={$signature}";

        $url = "{$this->endpoint}/{$this->bucket_name}/" . $this->urlencode_path($key);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $curlHeaders = [];
        foreach ($headers as $header_key => $value) {
            $curlHeaders[] = "{$header_key}: {$value}";
        }
        $curlHeaders[] = "Authorization: {$authorizationHeader}";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logger->log('info', 'Object downloaded from Cubbit successfully', ['key' => $key]);
            return $result;
        } else {
            $this->logger->log('error', 'Failed to download object from Cubbit', ['key' => $key, 'http_code' => $httpCode, 'response' => $result]);
            return false;
        }
    }

    /**
     * Deletes an object from Cubbit.
     *
     * @param string $key The object key (path in Cubbit).
     * @return bool True on success, false on failure.
     */
    public function delete_object($key) {
        if (!$this->is_configured()) {
            return false;
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        $emptyPayloadHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $headers = [
            'Host' => 's3.cubbit.eu',
            'x-amz-content-sha256' => $emptyPayloadHash,
            'x-amz-date' => $amzDate
        ];

        $canonicalUri = "/{$this->bucket_name}/" . $this->urlencode_path($key);
        $canonicalQueryString = '';

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeaders = '';
        foreach ($headers as $header_key => $value) {
            $canonicalHeaders .= strtolower($header_key) . ':' . trim($value) . "\n";
            $signedHeaders .= strtolower($header_key) . ';';
        }
        $signedHeaders = rtrim($signedHeaders, ';');

        $canonicalRequest = "DELETE\n" .
                            $canonicalUri . "\n" .
                            $canonicalQueryString . "\n" .
                            $canonicalHeaders . "\n" .
                            $signedHeaders . "\n" .
                            $emptyPayloadHash;

        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->derive_signature_key($this->secret_key, $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "{$algorithm} " .
                               "Credential={$this->access_key}/{$credentialScope}, " .
                               "SignedHeaders={$signedHeaders}, " .
                               "Signature={$signature}";

        $url = "{$this->endpoint}/{$this->bucket_name}/" . $this->urlencode_path($key);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $curlHeaders = [];
        foreach ($headers as $header_key => $value) {
            $curlHeaders[] = "{$header_key}: {$value}";
        }
        $curlHeaders[] = "Authorization: {$authorizationHeader}";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logger->log('info', 'Object deleted from Cubbit successfully', ['key' => $key]);
            return true;
        } else {
            $this->logger->log('error', 'Failed to delete object from Cubbit', ['key' => $key, 'http_code' => $httpCode, 'response' => $result]);
            return false;
        }
    }

    /**
     * Lists objects in a Cubbit bucket with a given prefix.
     *
     * @param string $prefix The prefix to filter objects by.
     * @return array An array of object keys.
     */
    public function list_objects($prefix = '') {
        if (!$this->is_configured()) {
            return [];
        }

        $all_objects = [];
        $continuation_token = null;

        do {
            $amzDate = gmdate('Ymd\THis\Z');
            $datestamp = gmdate('Ymd');
            $emptyPayloadHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

            $queryParams = [
                'list-type' => '2',
                'prefix' => $prefix
            ];
            if ($continuation_token) {
                $queryParams['continuation-token'] = $continuation_token;
            }
            ksort($queryParams);
            $canonicalQueryString = http_build_query($queryParams);

            $headers = [
                'Host' => 's3.cubbit.eu',
                'x-amz-content-sha256' => $emptyPayloadHash,
                'x-amz-date' => $amzDate
            ];

            $canonicalUri = "/{$this->bucket_name}/";

            ksort($headers);
            $canonicalHeaders = '';
            $signedHeaders = '';
            foreach ($headers as $header_key => $value) {
                $canonicalHeaders .= strtolower($header_key) . ':' . trim($value) . "\n";
                $signedHeaders .= strtolower($header_key) . ';';
            }
            $signedHeaders = rtrim($signedHeaders, ';');

            $canonicalRequest = "GET\n" .
                                $canonicalUri . "\n" .
                                $canonicalQueryString . "\n" .
                                $canonicalHeaders . "\n" .
                                $signedHeaders . "\n" .
                                $emptyPayloadHash;

            $algorithm = "AWS4-HMAC-SHA256";
            $credentialScope = "{$datestamp}/{$this->region}/s3/aws4_request";
            $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

            $signingKey = $this->derive_signature_key($this->secret_key, $datestamp, $this->region, 's3');
            $signature = hash_hmac('sha256', $stringToSign, $signingKey);

            $authorizationHeader = "{$algorithm} " .
                                   "Credential={$this->access_key}/{$credentialScope}, " .
                                   "SignedHeaders={$signedHeaders}, " .
                                   "Signature={$signature}";

            $url = "{$this->endpoint}/{$this->bucket_name}/?" . $canonicalQueryString;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $curlHeaders = [];
            foreach ($headers as $header_key => $value) {
                $curlHeaders[] = "{$header_key}: {$value}";
            }
            $curlHeaders[] = "Authorization: {$authorizationHeader}";

            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->log('error', 'Failed to list objects from Cubbit', ['prefix' => $prefix, 'http_code' => $httpCode, 'response' => $result]);
                return $all_objects;
            }

            $xml = simplexml_load_string($result);
            if ($xml === false) {
                $this->logger->log('error', 'Failed to parse XML response for listing objects');
                return $all_objects;
            }

            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $object) {
                    $all_objects[] = (string)$object->Key;
                }
            }

            $continuation_token = isset($xml->NextContinuationToken) ? (string)$xml->NextContinuationToken : null;

        } while ($continuation_token);

        return $all_objects;
    }

    /**
     * Helper to URL-encode path components while preserving slashes.
     *
     * @param string $path The path to encode.
     * @return string The encoded path.
     */
    private function urlencode_path($path) {
        return str_replace('%2F', '/', rawurlencode($path));
    }

    /**
     * Derives the signing key for AWS Signature V4.
     * This function is copied from CubbitDirectoryManagerExtension.
     *
     * @param string $key The secret key.
     * @param string $date The date (YYYYMMDD).
     * @param string $region The AWS region.
     * @param string $service The AWS service.
     * @return string The derived signing key.
     */
    private function derive_signature_key($key, $date, $region, $service) {
        $kDate = hash_hmac('sha256', $date, "AWS4" . $key, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }

    /**
     * Checks if Cubbit integration is configured.
     *
     * @return bool
     */
    private function is_configured() {
        return !empty($this->bucket_name) && !empty($this->access_key) && !empty($this->secret_key);
    }
}