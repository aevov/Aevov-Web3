<?php
namespace AevovLanguageEngineV2\Core;

use AevovCubbitCDN\AevovCubbitCDN;
use AevovChunkRegistry\ChunkRegistry;
use Exception;
use WP_Error;

require_once dirname(__FILE__) . '/../../../aevov-cubbit-cdn/aevov-cubbit-cdn.php';
require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';

/**
 * A Safetensors parser.
 */
class SafetensorsParser {
    public function load_file($path) {
        if (!file_exists($path)) {
            throw new Exception("File not found: {$path}");
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new Exception("Could not open file: {$path}");
        }

        $header_size_bytes = fread($handle, 8);
        if (strlen($header_size_bytes) < 8) {
            fclose($handle);
            throw new Exception('Invalid safetensors file: could not read header size.');
        }
        $header_size = unpack('P', $header_size_bytes)[1];

        $header_json = fread($handle, $header_size);
        if (strlen($header_json) < $header_size) {
            fclose($handle);
            throw new Exception('Invalid safetensors file: could not read header.');
        }
        $header = json_decode($header_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            fclose($handle);
            throw new Exception('Invalid safetensors file: could not parse header JSON.');
        }

        $tensors = [];
        foreach ($header as $name => $info) {
            if (!isset($info['data_offsets']) || !is_array($info['data_offsets']) || count($info['data_offsets']) !== 2) {
                continue; // Skip metadata entries like __metadata__
            }
            fseek($handle, $info['data_offsets'][0] + $header_size + 8);
            $data = fread($handle, $info['data_offsets'][1] - $info['data_offsets'][0]);
            $tensors[$name] = [
                'shape' => $info['shape'],
                'dtype' => $info['dtype'],
                'data' => $data
            ];
        }

        fclose($handle);
        return $tensors;
    }
}

class LLMIngestionEngine {

    private $chunk_size;
    private $logger;

    public function __construct($chunk_size_mb = 100) {
        $this->chunk_size = $chunk_size_mb * 1024 * 1024; // Convert to bytes
        $this->logger = new class {
            public function info($message, $context = []) { error_log('[INFO] ' . $message . ' ' . json_encode($context)); }
            public function error($message, $context = []) { error_log('[ERROR] ' . $message . ' ' . json_encode($context)); }
        };
    }

    public function ingest_from_path( $model_path, $output_dir ) {
        $this->logger->info('Starting model ingestion', ['path' => $model_path, 'output_dir' => $output_dir]);

        if ( ! file_exists( $model_path ) ) {
            $this->logger->error('Model file not found', ['path' => $model_path]);
            return new WP_Error( 'model_not_found', 'Model file not found.' );
        }

        try {
            $this->chunk_model( $model_path, $output_dir );
        } catch ( Exception $e ) {
            $this->logger->error('Model ingestion failed', ['error' => $e->getMessage()]);
            return new WP_Error( 'ingestion_failed', $e->getMessage() );
        }

        $this->logger->info('Model ingestion completed successfully', ['path' => $model_path]);
        return true;
    }

    private function chunk_model( $model_path, $output_dir ) {
        wp_mkdir_p( $output_dir );

        $parser = new SafetensorsParser();
        $tensors = $parser->load_file( $model_path );

        $model_keys = array_keys( $tensors );
        $total_keys = count( $model_keys );
        $current_chunk = [];
        $current_chunk_size = 0;
        $chunk_number = 0;
        $metadata = [];

        foreach ( $model_keys as $i => $key ) {
            $tensor = $tensors[$key];
            $tensor_dict = [
                'dtype' => $tensor['dtype'],
                'shape' => $tensor['shape'],
                'data' => base64_encode( $tensor['data'] )
            ];
            $tensor_size = strlen( json_encode( $tensor_dict ) );

            if ( $current_chunk_size + $tensor_size > $this->chunk_size && ! empty( $current_chunk ) ) {
                $chunk_filename = $this->save_chunk( $current_chunk, $chunk_number, $output_dir );
                $metadata[] = [
                    'chunk_number' => $chunk_number,
                    'filename' => $chunk_filename,
                    'size' => $current_chunk_size,
                    'keys' => array_keys( $current_chunk )
                ];
                $chunk_number++;
                $current_chunk = [];
                $current_chunk_size = 0;
            }

            $current_chunk[$key] = $tensor_dict;
            $current_chunk_size += $tensor_size;
        }

        if ( ! empty( $current_chunk ) ) {
            $chunk_filename = $this->save_chunk( $current_chunk, $chunk_number, $output_dir );
            $metadata[] = [
                'chunk_number' => $chunk_number,
                'filename' => $chunk_filename,
                'size' => $current_chunk_size,
                'keys' => array_keys( $current_chunk )
            ];
        }

        $this->save_metadata( $metadata, $output_dir );
    }

    private function save_chunk( $chunk, $chunk_number, $output_dir ) {
        $filename = "bloom_chunk_{$chunk_number}.json";
        $filepath = $output_dir . '/' . $filename;
        if (file_put_contents( $filepath, json_encode( $chunk ) ) === false) {
            throw new Exception("Could not save chunk to file: {$filepath}");
        }

        // Upload to Cubbit
        $cdn = new AevovCubbitCDN();
        $cubbit_key = "models/" . basename($output_dir) . "/{$filename}";
        $upload_result = $cdn->upload_object( $cubbit_key, json_encode( $chunk ) );
        if (is_wp_error($upload_result)) {
            throw new Exception('Failed to upload chunk to Cubbit: ' . $upload_result->get_error_message());
        }

        return $filename;
    }

    private function save_metadata( $metadata, $output_dir ) {
        $metadata_filename = 'bloom_chunks_metadata.json';
        $filepath = $output_dir . '/' . $metadata_filename;
        if (file_put_contents( $filepath, json_encode( $metadata, JSON_PRETTY_PRINT ) ) === false) {
            throw new Exception("Could not save metadata to file: {$filepath}");
        }

        // Upload to Cubbit
        $cdn = new AevovCubbitCDN();
        $cubbit_key = "models/" . basename($output_dir) . "/{$metadata_filename}";
        $upload_result = $cdn->upload_object( $cubbit_key, json_encode( $metadata, JSON_PRETTY_PRINT ) );
        if (is_wp_error($upload_result)) {
            throw new Exception('Failed to upload metadata to Cubbit: ' . $upload_result->get_error_message());
        }
    }
}
