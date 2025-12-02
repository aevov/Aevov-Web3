<?php

namespace APS\Analysis;

class APS_Embedding_Generator {
    private $cache;
    private $pattern_analyzer;
    private $bloom_integration;
    
    public function __construct(APS_Cache $cache, PatternAnalyzer $pattern_analyzer, APS_BLOOM_Integration $bloom_integration) {
        $this->cache = $cache;
        $this->pattern_analyzer = $pattern_analyzer;
        $this->bloom_integration = $bloom_integration;
    }

    public function generate_embeddings($data, $options = []) {
        $cache_key = 'embedding_' . md5(serialize($data));
        
        if ($cached = $this->cache->get($cache_key)) {
            return $cached;
        }

        $embedding_type = $options['type'] ?? 'default';
        
        switch($embedding_type) {
            case 'text':
                $embedding = $this->generate_text_embedding($data);
                break;
            case 'image':
                $embedding = $this->generate_image_embedding($data);
                break;
            case 'tensor':
                $embedding = $this->generate_tensor_embedding($data);
                break;
            default:
                $features = $this->pattern_analyzer->extract_features($data);
                $embedding = $features['vector'];
        }

        $this->cache->set($cache_key, $embedding);
        return $embedding;
    }

    private function generate_text_embedding($text) {
        try {
            if (!$this->bloom_integration->is_available()) {
                throw new \Exception('BLOOM integration not available');
            }
            
            $response = $this->bloom_integration->analyze_pattern([
                'type' => 'text_embedding',
                'content' => $text,
                'metadata' => [
                    'source' => 'aps',
                    'embedding_type' => 'text',
                    'dimensions' => 768
                ]
            ]);
            
            if (!empty($response['features']['vector'])) {
                $bloom_confidence = $this->calculate_bloom_confidence($response);
                return $this->combine_embeddings($response['features']['vector'], $this->pattern_analyzer->extract_features($text)['vector'], $bloom_confidence);
            }
            
            throw new \Exception('Empty embedding returned from BLOOM');
        } catch (\Exception $e) {
            error_log('BLOOM text embedding generation failed: ' . $e->getMessage());
            $features = $this->pattern_analyzer->extract_features($text);
            return $features['vector'];
        }
    }

    private function generate_image_embedding($image_data) {
        try {
            if (!$this->bloom_integration->is_available()) {
                throw new \Exception('BLOOM integration not available');
            }
            
            $response = $this->bloom_integration->analyze_pattern([
                'type' => 'image_embedding',
                'content' => $image_data,
                'metadata' => [
                    'source' => 'aps',
                    'embedding_type' => 'image',
                    'dimensions' => 768
                ]
            ]);
            
            if (!empty($response['features']['vector'])) {
                $bloom_confidence = $this->calculate_bloom_confidence($response);
                return $this->combine_embeddings($response['features']['vector'], $this->pattern_analyzer->extract_features($image_data)['vector'], $bloom_confidence);
            }
            
            throw new \Exception('Empty embedding returned from BLOOM');
        } catch (\Exception $e) {
            error_log('BLOOM image embedding generation failed: ' . $e->getMessage());
            $features = $this->pattern_analyzer->extract_features($image_data);
            return $features['vector'];
        }
    }

    private function generate_tensor_embedding($tensor) {
        $flattened = $this->flatten_tensor($tensor);
        return $this->normalize_vector($flattened);
    }

    private function normalize_vector($vector) {
        $magnitude = sqrt(array_sum(array_map(function($x) {
            return $x * $x;
        }, $vector)));

        if ($magnitude == 0) {
            return array_fill(0, count($vector), 0);
        }

        return array_map(function($x) use ($magnitude) {
            return $x / $magnitude;
        }, $vector);
    }

    private function flatten_tensor($tensor) {
        $flattened = [];
        $stack = [$tensor];
    
        while (!empty($stack)) {
            $current = array_pop($stack);
    
            if (is_array($current)) {
                foreach ($current as $value) { 
                    $stack[] = $value;
                }
            } else {
                $flattened[] = $current;
            }
        }
        return array_reverse($flattened); 
    }

    // New Method for Combining Embeddings
    private function combine_embeddings($neural_embedding, $symbolic_embedding, $neural_confidence) {
        $symbolic_confidence = $this->pattern_analyzer->get_confidence_score($symbolic_embedding);

        // Calculate the weighted average of both embeddings based on their confidence scores
        $weighted_neural = array_map(function($x) use ($neural_confidence) {
            return $x * $neural_confidence;
        }, $neural_embedding);
        
        $weighted_symbolic = array_map(function($x) use ($symbolic_confidence) {
            return $x * $symbolic_confidence;
        }, $symbolic_embedding);

        // Combine the weighted embeddings
        $combined_embedding = array_map(function($neural, $symbolic) {
            return $neural + $symbolic;
        }, $weighted_neural, $weighted_symbolic);

        // Normalize the combined embedding
        return $this->normalize_vector($combined_embedding);
    }
}