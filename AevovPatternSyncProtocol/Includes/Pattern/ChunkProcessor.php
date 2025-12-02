<?php
/**
 * Chunk Processor for Pattern Generation
 */

namespace APS\Pattern;

class ChunkProcessor {
    private $chunk_size;
    private $overlap;
    private $logger;
    
    public function __construct($chunk_size = 512, $overlap = 50) {
        $this->chunk_size = $chunk_size;
        $this->overlap = $overlap;
        $this->logger = \APS\Core\Logger::get_instance();
    }
    
    /**
     * Process raw data into chunks for pattern analysis
     */
    public function process_data($data, $options = []) {
        try {
            if (empty($data) || !is_string($data)) {
                $this->logger->error('Invalid or empty data provided for chunk processing');
                return [];
            }
            
            $this->logger->info('Starting chunk processing', ['data_size' => strlen($data)]);
            
            $chunk_size = $options['chunk_size'] ?? $this->chunk_size;
            $overlap = $options['overlap'] ?? $this->overlap;
            
            // Validate chunk_size and overlap
            if ($chunk_size <= 0) {
                $this->logger->error('Invalid chunk size: must be greater than 0', ['chunk_size' => $chunk_size]);
                return [];
            }
            if ($overlap < 0 || $overlap >= $chunk_size) {
                $this->logger->error('Invalid overlap: must be non-negative and less than chunk size', ['overlap' => $overlap, 'chunk_size' => $chunk_size]);
                return [];
            }
            
            $chunks = [];
            $position = 0;
            $chunk_id = 0;
            
            while ($position < strlen($data)) {
                $chunk_data_segment = substr($data, $position, $chunk_size);
                
                if (empty($chunk_data_segment)) {
                    break;
                }
                
                $chunk = [
                    'chunk_id' => $chunk_id++,
                    'data' => $chunk_data_segment,
                    'position' => $position,
                    'size' => strlen($chunk_data_segment),
                    'overlap' => $overlap,
                    'sequence' => $this->tokenize($chunk_data_segment),
                    'attention_mask' => $this->generate_attention_mask($chunk_data_segment),
                    'position_ids' => $this->generate_position_ids($chunk_data_segment)
                ];
                
                // Validate generated chunk structure
                $validation_error = $this->validate_chunk($chunk);
                if ($validation_error !== true) {
                    $this->logger->error('Generated chunk failed validation', [
                        'chunk_id' => $chunk['chunk_id'],
                        'error' => $validation_error
                    ]);
                    // Decide whether to skip this chunk or stop processing
                    // For now, we'll skip and log
                    $position += $chunk_size - $overlap; // Move to next potential chunk
                    continue;
                }
                
                $chunks[] = $chunk;
                
                // Move position forward, accounting for overlap
                $position += $chunk_size - $overlap;
            }
            
            $this->logger->info('Chunk processing complete', ['chunks_created' => count($chunks)]);
            
            return $chunks;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during chunk processing', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_size' => strlen($data)
            ]);
            return [];
        }
    }
    
    /**
     * Tokenize chunk data into sequence
     */
    private function tokenize($data) {
        try {
            if (!is_string($data)) {
                $this->logger->error('Invalid data type for tokenization: not a string');
                return [];
            }
            
            // Simple tokenization - convert to array of character codes
            $tokens = [];
            for ($i = 0; $i < strlen($data); $i++) {
                $tokens[] = ord($data[$i]);
            }
            
            // Pad or truncate to standard size
            $target_length = 512;
            if (count($tokens) < $target_length) {
                $tokens = array_pad($tokens, $target_length, 0);
            } else {
                $tokens = array_slice($tokens, 0, $target_length);
            }
            
            $this->logger->debug('Data tokenized', ['original_length' => strlen($data), 'token_count' => count($tokens)]);
            
            return $tokens;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during tokenization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Generate attention mask for the chunk
     */
    private function generate_attention_mask($data) {
        try {
            if (!is_string($data)) {
                $this->logger->error('Invalid data type for attention mask generation: not a string');
                return [];
            }
            
            $length = min(strlen($data), 512);
            $mask = array_fill(0, $length, 1);
            
            // Pad to standard size
            if ($length < 512) {
                $mask = array_pad($mask, 512, 0);
            }
            
            $this->logger->debug('Attention mask generated', ['length' => count($mask)]);
            
            return $mask;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during attention mask generation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Generate position IDs for the chunk
     */
    private function generate_position_ids($data) {
        try {
            if (!is_string($data)) {
                $this->logger->error('Invalid data type for position IDs generation: not a string');
                return [];
            }
            
            $length = min(strlen($data), 512);
            $position_ids = range(0, $length - 1);
            
            // Pad to standard size
            if ($length < 512) {
                $position_ids = array_pad($position_ids, 512, 0);
            }
            
            $this->logger->debug('Position IDs generated', ['length' => count($position_ids)]);
            
            return $position_ids;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during position IDs generation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Merge overlapping chunks back into continuous data
     */
    public function merge_chunks($chunks) {
        try {
            if (empty($chunks) || !is_array($chunks)) {
                $this->logger->warning('No chunks provided for merging or invalid input');
                return '';
            }
            
            $this->logger->info('Merging chunks', ['chunk_count' => count($chunks)]);
            
            // Sort chunks by position
            usort($chunks, function($a, $b) {
                return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
            });
            
            $merged_data = '';
            $last_position = 0;
            
            foreach ($chunks as $index => $chunk) {
                if (!isset($chunk['data']) || !isset($chunk['position'])) {
                    $this->logger->error('Invalid chunk structure encountered during merge', ['chunk_index' => $index, 'chunk' => $chunk]);
                    continue; // Skip this invalid chunk
                }
                
                $chunk_data_segment = $chunk['data'];
                $position = $chunk['position'];
                $overlap = $chunk['overlap'] ?? 0;
                
                if ($position > $last_position) {
                    // No overlap, append directly
                    $merged_data .= $chunk_data_segment;
                } else {
                    // Handle overlap
                    $overlap_start = $last_position - $position;
                    if ($overlap_start < strlen($chunk_data_segment)) {
                        $merged_data .= substr($chunk_data_segment, $overlap_start);
                    } else {
                        $this->logger->warning('Full overlap detected, skipping chunk to avoid duplication', ['chunk_id' => $chunk['chunk_id'] ?? 'unknown']);
                    }
                }
                
                $last_position = $position + strlen($chunk_data_segment);
            }
            
            $this->logger->info('Chunk merging complete', ['merged_size' => strlen($merged_data)]);
            
            return $merged_data;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during chunk merging', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chunk_count' => count($chunks)
            ]);
            return '';
        }
    }
    
    /**
     * Validate chunk structure
     */
    public function validate_chunk($chunk) {
        try {
            if (!is_array($chunk)) {
                $this->logger->error('Invalid chunk data type for validation: not an array');
                return "Invalid chunk data type";
            }
            
            $required_fields = ['chunk_id', 'data', 'position', 'size', 'sequence', 'attention_mask', 'position_ids'];
            
            foreach ($required_fields as $field) {
                if (!isset($chunk[$field])) {
                    $this->logger->error("Missing required field in chunk: {$field}", ['chunk_id' => $chunk['chunk_id'] ?? 'unknown']);
                    return "Missing required field: {$field}";
                }
            }
            
            // Validate sequence, attention_mask, and position_ids are arrays of correct length
            if (!is_array($chunk['sequence']) || count($chunk['sequence']) !== 512) {
                $this->logger->error('Invalid sequence length or type', ['chunk_id' => $chunk['chunk_id'], 'length' => count($chunk['sequence'])]);
                return "Invalid sequence length or type";
            }
            
            if (!is_array($chunk['attention_mask']) || count($chunk['attention_mask']) !== 512) {
                $this->logger->error('Invalid attention mask length or type', ['chunk_id' => $chunk['chunk_id'], 'length' => count($chunk['attention_mask'])]);
                return "Invalid attention mask length or type";
            }
            
            if (!is_array($chunk['position_ids']) || count($chunk['position_ids']) !== 512) {
                $this->logger->error('Invalid position IDs length or type', ['chunk_id' => $chunk['chunk_id'], 'length' => count($chunk['position_ids'])]);
                return "Invalid position IDs length or type";
            }
            
            $this->logger->debug('Chunk validated successfully', ['chunk_id' => $chunk['chunk_id']]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during chunk validation', [
                'chunk_id' => $chunk['chunk_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return "Exception during validation: " . $e->getMessage();
        }
    }
    
    /**
     * Get chunk statistics
     */
    public function get_chunk_stats($chunks) {
        try {
            if (empty($chunks) || !is_array($chunks)) {
                $this->logger->warning('No chunks provided for stats calculation or invalid input');
                return [];
            }
            
            $total_size = array_sum(array_column($chunks, 'size'));
            $count = count($chunks);
            $avg_size = $count > 0 ? $total_size / $count : 0;
            
            $min_size = !empty($chunks) ? min(array_column($chunks, 'size')) : 0;
            $max_size = !empty($chunks) ? max(array_column($chunks, 'size')) : 0;
            
            $stats = [
                'total_chunks' => $count,
                'total_size' => $total_size,
                'average_size' => $avg_size,
                'min_size' => $min_size,
                'max_size' => $max_size
            ];
            
            $this->logger->info('Chunk statistics calculated', $stats);
            
            return $stats;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during chunk stats calculation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chunk_count' => count($chunks)
            ]);
            return [];
        }
    }
    
    /**
     * Process chunks by IDs (for integration with PatternGenerator)
     */
    public function process_chunks($chunk_ids) {
        try {
            if (empty($chunk_ids) || !is_array($chunk_ids)) {
                $this->logger->warning('No chunk IDs provided for processing chunks by IDs');
                return [];
            }
            
            $this->logger->info('Processing chunks by IDs', ['chunk_ids' => $chunk_ids]);
            
            $processed_chunks = [];
            
            foreach ($chunk_ids as $chunk_id) {
                // In a real scenario, you would fetch actual chunk data from a database or external source
                // For now, we'll continue with mock data for integration testing
                $mock_chunk_data = [
                    'id' => $chunk_id,
                    'tensor_name' => "tensor_chunk_{$chunk_id}",
                    'shape' => [10, 10],
                    'dtype' => 'float32',
                    'tensor_data' => [
                        'data' => array_fill(0, 100, 0.5) // Mock tensor data
                    ]
                ];
                
                // Validate the mock chunk data before adding
                $validation_error = $this->validate_chunk($mock_chunk_data);
                if ($validation_error !== true) {
                    $this->logger->error('Mock chunk data failed validation', [
                        'chunk_id' => $chunk_id,
                        'error' => $validation_error
                    ]);
                    continue; // Skip this invalid mock chunk
                }
                
                $processed_chunks[] = $mock_chunk_data;
            }
            
            $this->logger->info('Chunk processing by IDs complete', ['processed_count' => count($processed_chunks)]);
            
            return $processed_chunks;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred during processing chunks by IDs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chunk_ids' => $chunk_ids
            ]);
            return [];
        }
    }
}