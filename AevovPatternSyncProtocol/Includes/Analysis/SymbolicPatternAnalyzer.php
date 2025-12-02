<?php 

namespace APS\Analysis;

class SymbolicPatternAnalyzer {
    private $confidence_threshold;

    public function __construct($pattern_analyzer = null, $confidence_threshold = null) {
        $this->pattern_analyzer = $pattern_analyzer ?? new PatternAnalyzer();
        $this->confidence_threshold = $confidence_threshold ?? get_option('aps_confidence_threshold', 0.75);
    }

    public function analyze_pattern($data) {
        // Extract symbolic elements
        $symbols = $this->extract_symbols($data);
        $relations = $this->identify_relations($data);
        $rules = $this->derive_rules($data);

        // Extract standard features from pattern analyzer
        $features = $this->extract_features($data);
        $metrics = $this->calculate_metrics($features, $symbols, $relations);
        $signature = $this->generate_signature($features, $symbols);

        $pattern = [
            'id' => wp_generate_uuid4(),
            'type' => 'symbolic_pattern',
            'features' => $features,
            'symbols' => $symbols,
            'relations' => $relations,
            'rules' => $rules,
            'metrics' => $metrics,
            'pattern_hash' => $signature,
            'confidence' => $this->calculate_confidence($metrics),
            'timestamp' => current_time('mysql', true)
        ];
        
        return $pattern;
    }

    private function extract_features($data) {
        $analyzer = new PatternAnalyzer();
        return $analyzer->extract_features($data);
    }

    private function extract_symbols($data) {
        $symbols = [];

        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                $symbols[$key] = [
                    'type' => $this->determine_symbol_type($key, $value),
                    'value' => is_scalar($value) ? $value : null,
                    'children' => !is_scalar($value) ? $this->extract_symbols($value) : null,
                    'attributes' => $this->extract_attributes($key, $value)
                ];
            }
        }
        // For text data, extract linguistic symbols
        else if (is_string($data)) {
            $symbols = $this->extract_linguistic_symbols($data);
        }
        
        return $symbols;
    }
    private function determine_symbol_type($key, $value) {
        if (is_numeric($key)) {
            return 'list_item';
        }
        if (is_string($key)) {
            return 'named_entity';
        }
        return 'generic_symbol';
    }

    private function extract_attributes($key, $value) {
        return [
            'is_numeric' => is_numeric($value),
            'is_string' => is_string($value),
            'is_empty' => empty($value),
            'length' => is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 0)
        ];
    }

    private function extract_linguistic_symbols($text) {
        // Advanced NLP implementation
        // Tokenize the text using a regex that handles words and punctuation.
        preg_match_all('/[a-zA-Z]+|\S/', strtolower($text), $matches);
        $tokens = $matches[0];

        // Rule-based POS tagger
        $symbols = [];
        foreach ($tokens as $token) {
            $pos = 'unknown';

            // Simple rules for POS tagging
            if (preg_match('/(ing|ed|es|s)$/', $token)) {
                $pos = 'verb';
            } elseif (preg_match('/(ly|able|ible|ful|less|ous)$/', $token)) {
                $pos = 'adjective';
            } elseif (in_array($token, ['the', 'a', 'an', 'this', 'that', 'these', 'those', 'my', 'your', 'his', 'her', 'its', 'our', 'their'])) {
                $pos = 'determiner';
            } elseif (in_array($token, ['and', 'but', 'or', 'so', 'for', 'nor', 'yet'])) {
                $pos = 'conjunction';
            } elseif (in_array($token, ['on', 'in', 'at', 'for', 'to', 'from', 'with', 'by', 'about'])) {
                $pos = 'preposition';
            } elseif (preg_match('/^[A-Z]/', $token)) {
                $pos = 'noun'; // Proper noun
            } elseif (count($symbols) > 0 && $symbols[count($symbols) - 1]['pos'] === 'determiner') {
                $pos = 'noun'; // Noun after a determiner
            }

            // Default to noun for unknown words
            if ($pos === 'unknown' && ctype_alpha($token)) {
                $pos = 'noun';
            }


            $symbols[] = [
                'type' => 'word',
                'value' => $token,
                'pos' => $pos
            ];
        }
        return $symbols;
    }

    private function identify_relations($data) {
        $relations = [];
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    foreach ($value as $child_key => $child_value) {
                        $relations[] = [
                            'source' => $key,
                            'target' => $child_key,
                            'type' => 'contains'
                        ];
                    }
                }
            }
        }
        return $relations;
    }

    private function derive_rules($data) {
        $rules = [];
        $symbols = $this->extract_symbols($data);

        // Count symbol co-occurrence
        $co_occurrence = [];
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $keys = array_keys((array)$value);
                    for ($i = 0; $i < count($keys); $i++) {
                        for ($j = $i + 1; $j < count($keys); $j++) {
                            $pair = [$keys[$i], $keys[$j]];
                            sort($pair);
                            $pair_key = implode('|', $pair);
                            if (!isset($co_occurrence[$pair_key])) {
                                $co_occurrence[$pair_key] = 0;
                            }
                            $co_occurrence[$pair_key]++;
                        }
                    }
                }
            }
        }

        // Derive rules from frequent co-occurrence
        foreach ($co_occurrence as $pair_key => $count) {
            if ($count > 1) { // Arbitrary threshold for "frequent"
                list($antecedent, $consequent) = explode('|', $pair_key);
                $rules[] = [
                    'antecedent' => $antecedent,
                    'consequent' => $consequent,
                    'confidence' => 1.0 // Simple confidence for co-occurrence
                ];
            }
        }

        return $rules;
    }

    private function calculate_metrics($features, $symbols, $relations) {
        return [
            'complexity' => count($symbols) + count($relations),
            'density' => count($features) > 0 ? count($symbols) / count($features) : 0,
            'uniqueness' => count(array_unique(array_column($symbols, 'value')))
        ];
    }

    private function generate_signature($features, $symbols) {
        return md5(json_encode($features) . json_encode($symbols));
    }

    private function calculate_confidence($metrics) {
        // Simple confidence calculation based on complexity
        return min(1.0, $metrics['complexity'] / 100.0);
    }

    /**
     * Compares two patterns and returns a similarity metric.
     *
     * @param mixed $pattern1 The first pattern.
     * @param mixed $pattern2 The second pattern.
     * @return float A similarity metric from 0 to 1.
     */
    public function comparePatterns($pattern1, $pattern2) {
        $analysis1 = $this->analyze_pattern($pattern1);
        $analysis2 = $this->analyze_pattern($pattern2);

        $featureSimilarity = $this->compareArrays($analysis1['features'], $analysis2['features']);
        $symbolSimilarity = $this->compareArrays($analysis1['symbols'], $analysis2['symbols']);
        $relationSimilarity = $this->compareArrays($analysis1['relations'], $analysis2['relations']);
        $ruleSimilarity = $this->compareArrays($analysis1['rules'], $analysis2['rules']);

        // Weighted average of the different similarity scores
        return ($featureSimilarity * 0.4) + ($symbolSimilarity * 0.3) + ($relationSimilarity * 0.2) + ($ruleSimilarity * 0.1);
    }

    /**
     * Compares two arrays and returns a similarity metric.
     *
     * @param array $arr1 The first array.
     * @param array $arr2 The second array.
     * @return float A similarity metric from 0 to 1.
     */
    private function compareArrays($arr1, $arr2) {
        $intersection = count(array_intersect_key($arr1, $arr2));
        $union = count(array_merge($arr1, $arr2));
        if ($union > 0) {
            return $intersection / $union;
        }
        return 1.0;
    }
}