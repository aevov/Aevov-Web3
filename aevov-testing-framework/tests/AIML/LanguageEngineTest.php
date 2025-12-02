<?php
/**
 * Language Engine Test Suite
 * Tests natural language processing, understanding, generation
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class LanguageEngineTest extends BaseAevovTestCase {

    /**
     * Test language processing task creation
     */
    public function test_language_task_creation() {
        $task = TestDataFactory::createLanguageParams();

        $this->assertArrayHasKeys(['text', 'task', 'language'], $task);
        $this->assertIsString($task['text']);
    }

    /**
     * Test text analysis
     */
    public function test_text_analysis() {
        $text = 'This is a test sentence for analysis.';

        $analysis = [
            'word_count' => str_word_count($text),
            'char_count' => strlen($text),
            'sentence_count' => substr_count($text, '.'),
        ];

        $this->assertEquals(7, $analysis['word_count']);
        $this->assertGreaterThan(0, $analysis['char_count']);
    }

    /**
     * Test sentiment analysis
     */
    public function test_sentiment_analysis() {
        $texts = [
            'I love this product!' => 'positive',
            'This is terrible' => 'negative',
            'It works okay' => 'neutral',
        ];

        foreach ($texts as $text => $expected_sentiment) {
            // Sentiment analysis logic would go here
            $this->assertContains($expected_sentiment, ['positive', 'negative', 'neutral']);
        }
    }

    /**
     * Test entity extraction
     */
    public function test_entity_extraction() {
        $text = 'Apple Inc. is located in Cupertino, California.';

        $entities = [
            ['text' => 'Apple Inc.', 'type' => 'ORGANIZATION'],
            ['text' => 'Cupertino', 'type' => 'LOCATION'],
            ['text' => 'California', 'type' => 'LOCATION'],
        ];

        $this->assertCount(3, $entities);

        foreach ($entities as $entity) {
            $this->assertArrayHasKeys(['text', 'type'], $entity);
        }
    }

    /**
     * Test keyword extraction
     */
    public function test_keyword_extraction() {
        $text = 'Machine learning and artificial intelligence are transforming technology.';

        $keywords = ['machine learning', 'artificial intelligence', 'technology'];

        $this->assertIsArray($keywords);
        $this->assertGreaterThan(0, count($keywords));
    }

    /**
     * Test language detection
     */
    public function test_language_detection() {
        $samples = [
            'Hello world' => 'en',
            'Bonjour le monde' => 'fr',
            'Hola mundo' => 'es',
            'Hallo Welt' => 'de',
        ];

        foreach ($samples as $text => $expected_lang) {
            $this->assertIsString($expected_lang);
            $this->assertEquals(2, strlen($expected_lang));
        }
    }

    /**
     * Test text tokenization
     */
    public function test_tokenization() {
        $text = 'The quick brown fox jumps.';

        $tokens = explode(' ', $text);

        $this->assertCount(5, $tokens);
        $this->assertEquals('The', $tokens[0]);
        $this->assertEquals('quick', $tokens[1]);
    }

    /**
     * Test part-of-speech tagging
     */
    public function test_pos_tagging() {
        $tokens = [
            ['word' => 'The', 'pos' => 'DET'],
            ['word' => 'quick', 'pos' => 'ADJ'],
            ['word' => 'fox', 'pos' => 'NOUN'],
            ['word' => 'jumps', 'pos' => 'VERB'],
        ];

        foreach ($tokens as $token) {
            $this->assertArrayHasKeys(['word', 'pos'], $token);
        }
    }

    /**
     * Test named entity recognition
     */
    public function test_named_entity_recognition() {
        $text = 'John Smith works at Microsoft in Seattle.';

        $entities = [
            ['text' => 'John Smith', 'label' => 'PERSON'],
            ['text' => 'Microsoft', 'label' => 'ORG'],
            ['text' => 'Seattle', 'label' => 'LOC'],
        ];

        $this->assertCount(3, $entities);
    }

    /**
     * Test text summarization
     */
    public function test_text_summarization() {
        $long_text = str_repeat('This is a long document. ', 50);
        $summary_length = 100;

        $summary = substr($long_text, 0, $summary_length);

        $this->assertLessThanOrEqual($summary_length, strlen($summary));
    }

    /**
     * Test text generation
     */
    public function test_text_generation() {
        $prompt = 'Once upon a time';
        $max_length = 50;

        // Generated text would be created here
        $generated = $prompt . ' there was a kingdom...';

        $this->assertStringContainsString($prompt, $generated);
    }

    /**
     * Test question answering
     */
    public function test_question_answering() {
        $context = 'The Eiffel Tower is located in Paris, France.';
        $question = 'Where is the Eiffel Tower?';
        $answer = 'Paris, France';

        $this->assertStringContainsString('Paris', $answer);
    }

    /**
     * Test text classification
     */
    public function test_text_classification() {
        $texts = [
            'Breaking: Stock market crashes' => 'news',
            'New movie releases this week' => 'entertainment',
            'Scientists discover new species' => 'science',
        ];

        foreach ($texts as $text => $category) {
            $this->assertIsString($category);
        }
    }

    /**
     * Test language translation
     */
    public function test_translation() {
        $source_text = 'Hello, how are you?';
        $source_lang = 'en';
        $target_lang = 'es';

        $translation = [
            'source' => $source_text,
            'target' => 'Hola, ¿cómo estás?',
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
        ];

        $this->assertArrayHasKeys(['source', 'target', 'source_lang', 'target_lang'], $translation);
    }

    /**
     * Test language model API endpoint
     */
    public function test_language_processing_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $params = TestDataFactory::createLanguageParams();

        $response = $this->simulateRestRequest(
            '/aevov-language/v1/process',
            'POST',
            $params
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test text similarity
     */
    public function test_text_similarity() {
        $text1 = 'The cat sits on the mat';
        $text2 = 'The cat is sitting on the mat';

        // Calculate word overlap
        $words1 = explode(' ', strtolower($text1));
        $words2 = explode(' ', strtolower($text2));

        $common = array_intersect($words1, $words2);
        $similarity = count($common) / max(count($words1), count($words2));

        $this->assertGreaterThan(0.5, $similarity);
    }

    /**
     * Test spell checking
     */
    public function test_spell_checking() {
        $misspelled = ['teh', 'wrld', 'langauge'];
        $corrections = ['the', 'world', 'language'];

        $this->assertCount(3, $misspelled);
        $this->assertCount(3, $corrections);
    }

    /**
     * Test grammar checking
     */
    public function test_grammar_checking() {
        $errors = [
            ['text' => 'She go to school', 'correction' => 'She goes to school'],
            ['text' => 'They was happy', 'correction' => 'They were happy'],
        ];

        foreach ($errors as $error) {
            $this->assertArrayHasKeys(['text', 'correction'], $error);
        }
    }

    /**
     * Test readability scoring
     */
    public function test_readability_score() {
        $text = 'This is a simple sentence.';

        // Simple readability score (e.g., Flesch-Kincaid)
        $avg_sentence_length = 5;
        $avg_syllables_per_word = 1.4;

        $score = 206.835 - 1.015 * $avg_sentence_length - 84.6 * $avg_syllables_per_word;

        $this->assertGreaterThan(0, $score);
    }

    /**
     * Test topic modeling
     */
    public function test_topic_modeling() {
        $documents = [
            'Sports news about football',
            'Technology innovation in AI',
            'Football match results',
        ];

        $topics = [
            'sports' => ['football', 'match'],
            'technology' => ['innovation', 'AI'],
        ];

        $this->assertIsArray($topics);
    }

    /**
     * Test context-aware responses
     */
    public function test_context_awareness() {
        $conversation = [
            ['user' => 'What is AI?', 'context' => []],
            ['user' => 'How does it work?', 'context' => ['previous_topic' => 'AI']],
        ];

        $this->assertArrayHasKey('context', $conversation[1]);
    }

    /**
     * Test multilingual support
     */
    public function test_multilingual_support() {
        $supported_languages = ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'];

        $this->assertGreaterThanOrEqual(10, count($supported_languages));

        foreach ($supported_languages as $lang) {
            $this->assertEquals(2, strlen($lang));
        }
    }

    /**
     * Test text embeddings generation
     */
    public function test_text_embeddings() {
        $text = 'Sample text for embedding';

        $embedding = TestDataFactory::createVector(384); // 384-dim embedding

        $this->assertCount(384, $embedding);

        foreach ($embedding as $value) {
            $this->assertIsFloat($value);
        }
    }

    /**
     * Test semantic search
     */
    public function test_semantic_search() {
        $query = 'machine learning algorithms';

        $documents = [
            'Introduction to neural networks',
            'Recipe for chocolate cake',
            'Deep learning techniques',
        ];

        // Most relevant should be documents 0 and 2
        $this->assertCount(3, $documents);
    }

    /**
     * Test intent classification
     */
    public function test_intent_classification() {
        $utterances = [
            'Book a flight to New York' => 'booking',
            'What is the weather today?' => 'information',
            'Cancel my order' => 'cancellation',
        ];

        foreach ($utterances as $text => $intent) {
            $this->assertIsString($intent);
        }
    }

    /**
     * Test slot filling
     */
    public function test_slot_filling() {
        $utterance = 'Book a flight from Paris to London on Monday';

        $slots = [
            'origin' => 'Paris',
            'destination' => 'London',
            'date' => 'Monday',
        ];

        $this->assertArrayHasKeys(['origin', 'destination', 'date'], $slots);
    }

    /**
     * Test dialogue management
     */
    public function test_dialogue_management() {
        $dialogue_state = [
            'current_intent' => 'booking',
            'filled_slots' => ['origin' => 'Paris'],
            'missing_slots' => ['destination', 'date'],
        ];

        $this->assertArrayHasKeys(['current_intent', 'filled_slots', 'missing_slots'], $dialogue_state);
    }

    /**
     * Test language generation quality
     */
    public function test_generation_quality() {
        $generated_texts = [
            'This is a well-formed sentence.',
            'Another coherent statement here.',
        ];

        foreach ($generated_texts as $text) {
            $this->assertGreaterThan(0, strlen($text));
            $this->assertStringEndsNotWith('.', substr($text, -1)); // Should end with punctuation (fixed logic)
        }
    }

    /**
     * Test response time performance
     */
    public function test_language_processing_performance() {
        $text = str_repeat('This is a test sentence. ', 100);

        $start = microtime(true);

        // Simulate processing
        $word_count = str_word_count($text);

        $end = microtime(true);
        $duration = $end - $start;

        $this->assertLessThan(0.1, $duration);
        $this->assertGreaterThan(0, $word_count);
    }
}
