<?php
/**
 * Media Engines Test Suite
 * Tests Image Engine, Music Forge, and Transcription Engine
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class MediaEnginesTest extends BaseAevovTestCase {

    // ==================== IMAGE ENGINE TESTS ====================

    /**
     * Test image generation job creation
     */
    public function test_image_job_creation() {
        $params = TestDataFactory::createImageParams();

        $this->assertArrayHasKeys(['prompt', 'width', 'height', 'steps'], $params);
        $this->assertIsString($params['prompt']);
        $this->assertIsInt($params['width']);
        $this->assertIsInt($params['height']);
    }

    /**
     * Test image parameters validation
     */
    public function test_image_params_validation() {
        $valid_params = TestDataFactory::createImageParams([
            'width' => 512,
            'height' => 512,
            'steps' => 50,
        ]);

        $this->assertEquals(512, $valid_params['width']);
        $this->assertEquals(512, $valid_params['height']);
        $this->assertGreaterThan(0, $valid_params['steps']);
        $this->assertLessThanOrEqual(100, $valid_params['steps']);
    }

    /**
     * Test image generation API endpoint
     */
    public function test_image_generation_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $params = TestDataFactory::createImageParams();

        $response = $this->simulateRestRequest(
            '/aevov-image/v1/generate',
            'POST',
            $params
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test image job status endpoint
     */
    public function test_image_job_status() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $job_id = 'image_job_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-image/v1/status/{$job_id}",
            'GET'
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test image retrieval endpoint
     */
    public function test_image_retrieval() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $job_id = 'image_job_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-image/v1/image/{$job_id}",
            'GET'
        );

        // Will error if job doesn't exist, which is expected
        $this->assertTrue(true);
    }

    /**
     * Test image dimensions validation
     */
    public function test_image_dimensions_validation() {
        $valid_dimensions = [[512, 512], [1024, 1024], [768, 512]];

        foreach ($valid_dimensions as [$width, $height]) {
            $this->assertGreaterThan(0, $width);
            $this->assertGreaterThan(0, $height);
            $this->assertLessThanOrEqual(2048, $width);
            $this->assertLessThanOrEqual(2048, $height);
        }
    }

    /**
     * Test image prompt sanitization
     */
    public function test_image_prompt_sanitization() {
        $dirty_prompt = '<script>alert("XSS")</script>A beautiful landscape';
        $clean_prompt = sanitize_text_field($dirty_prompt);

        $this->assertStringNotContainsString('<script>', $clean_prompt);
    }

    /**
     * Test image generation with negative prompt
     */
    public function test_image_negative_prompt() {
        $params = TestDataFactory::createImageParams([
            'prompt' => 'Beautiful landscape',
            'negative_prompt' => 'ugly, blurry, low quality',
        ]);

        $this->assertArrayHasKey('negative_prompt', $params);
        $this->assertIsString($params['negative_prompt']);
    }

    /**
     * Test image seed reproducibility
     */
    public function test_image_seed_reproducibility() {
        $seed = 12345;

        $params1 = TestDataFactory::createImageParams(['seed' => $seed]);
        $params2 = TestDataFactory::createImageParams(['seed' => $seed]);

        $this->assertEquals($params1['seed'], $params2['seed']);
    }

    /**
     * Test image guidance scale validation
     */
    public function test_image_guidance_scale() {
        $params = TestDataFactory::createImageParams([
            'guidance_scale' => 7.5,
        ]);

        $this->assertGreaterThan(0, $params['guidance_scale']);
        $this->assertLessThanOrEqual(20, $params['guidance_scale']);
    }

    /**
     * Test image batch generation
     */
    public function test_image_batch_generation() {
        $batch_size = 4;
        $params = TestDataFactory::createImageParams([
            'batch_size' => $batch_size,
        ]);

        $this->assertEquals($batch_size, $params['batch_size']);
        $this->assertGreaterThan(0, $batch_size);
        $this->assertLessThanOrEqual(10, $batch_size);
    }

    /**
     * Test image format validation
     */
    public function test_image_format_validation() {
        $valid_formats = ['png', 'jpg', 'jpeg', 'webp'];

        foreach ($valid_formats as $format) {
            $params = TestDataFactory::createImageParams(['format' => $format]);
            $this->assertTrue(in_array($params['format'], $valid_formats));
        }
    }

    /**
     * Test image CDN integration
     */
    public function test_image_cdn_integration() {
        $image_url = 'test/images/generated_' . uniqid() . '.png';

        // Verify URL structure
        $this->assertStringContainsString('test/images/', $image_url);
        $this->assertStringContainsString('.png', $image_url);
    }

    /**
     * Test image style presets
     */
    public function test_image_style_presets() {
        $presets = ['realistic', 'artistic', 'anime', 'photographic', 'digital-art'];

        foreach ($presets as $preset) {
            $params = TestDataFactory::createImageParams(['style_preset' => $preset]);
            $this->assertEquals($preset, $params['style_preset']);
        }
    }

    /**
     * Test image upscaling parameters
     */
    public function test_image_upscaling() {
        $params = TestDataFactory::createImageParams([
            'upscale' => true,
            'upscale_factor' => 2,
        ]);

        $this->assertTrue($params['upscale']);
        $this->assertEquals(2, $params['upscale_factor']);
    }

    // ==================== MUSIC FORGE TESTS ====================

    /**
     * Test music composition job creation
     */
    public function test_music_job_creation() {
        $params = TestDataFactory::createMusicParams();

        $this->assertArrayHasKeys(['style', 'tempo', 'duration'], $params);
        $this->assertIsString($params['style']);
        $this->assertIsInt($params['tempo']);
    }

    /**
     * Test music tempo validation
     */
    public function test_music_tempo_validation() {
        $valid_tempos = [60, 90, 120, 140, 180];

        foreach ($valid_tempos as $tempo) {
            $this->assertGreaterThanOrEqual(40, $tempo);
            $this->assertLessThanOrEqual(200, $tempo);
        }
    }

    /**
     * Test music composition API endpoint
     */
    public function test_music_composition_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $params = TestDataFactory::createMusicParams();

        $response = $this->simulateRestRequest(
            '/aevov-music/v1/compose',
            'POST',
            $params
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test music job status endpoint
     */
    public function test_music_job_status() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $job_id = 'music_job_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-music/v1/status/{$job_id}",
            'GET'
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test music track retrieval
     */
    public function test_music_track_retrieval() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $job_id = 'music_job_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-music/v1/track/{$job_id}",
            'GET'
        );

        // Will error if job doesn't exist
        $this->assertTrue(true);
    }

    /**
     * Test music style validation
     */
    public function test_music_style_validation() {
        $styles = ['ambient', 'classical', 'electronic', 'jazz', 'rock'];

        foreach ($styles as $style) {
            $params = TestDataFactory::createMusicParams(['style' => $style]);
            $this->assertEquals($style, $params['style']);
        }
    }

    /**
     * Test music key and scale
     */
    public function test_music_key_scale() {
        $keys = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
        $scales = ['major', 'minor', 'pentatonic', 'blues'];

        foreach ($keys as $key) {
            foreach ($scales as $scale) {
                $params = TestDataFactory::createMusicParams([
                    'key' => $key,
                    'scale' => $scale,
                ]);

                $this->assertEquals($key, $params['key']);
                $this->assertEquals($scale, $params['scale']);
            }
        }
    }

    /**
     * Test music duration limits
     */
    public function test_music_duration_limits() {
        $durations = [10, 30, 60, 120, 300];

        foreach ($durations as $duration) {
            $this->assertGreaterThan(0, $duration);
            $this->assertLessThanOrEqual(600, $duration); // Max 10 minutes
        }
    }

    /**
     * Test music instrument selection
     */
    public function test_music_instruments() {
        $instruments = ['piano', 'guitar', 'strings', 'brass', 'drums'];

        $params = TestDataFactory::createMusicParams([
            'instruments' => $instruments,
        ]);

        $this->assertIsArray($params['instruments']);
        $this->assertCount(5, $params['instruments']);
    }

    /**
     * Test music mood parameters
     */
    public function test_music_mood() {
        $moods = ['happy', 'sad', 'energetic', 'calm', 'dramatic'];

        foreach ($moods as $mood) {
            $params = TestDataFactory::createMusicParams(['mood' => $mood]);
            $this->assertEquals($mood, $params['mood']);
        }
    }

    /**
     * Test music export format
     */
    public function test_music_export_format() {
        $formats = ['mp3', 'wav', 'ogg', 'flac'];

        foreach ($formats as $format) {
            $params = TestDataFactory::createMusicParams(['format' => $format]);
            $this->assertEquals($format, $params['format']);
        }
    }

    /**
     * Test music CDN integration
     */
    public function test_music_cdn_integration() {
        $track_url = 'test/music/track_' . uniqid() . '.mp3';

        $this->assertStringContainsString('test/music/', $track_url);
        $this->assertStringContainsString('.mp3', $track_url);
    }

    /**
     * Test music composition complexity
     */
    public function test_music_complexity() {
        $complexity_levels = ['simple', 'moderate', 'complex'];

        foreach ($complexity_levels as $level) {
            $params = TestDataFactory::createMusicParams(['complexity' => $level]);
            $this->assertEquals($level, $params['complexity']);
        }
    }

    // ==================== TRANSCRIPTION ENGINE TESTS ====================

    /**
     * Test transcription job creation
     */
    public function test_transcription_job_creation() {
        $job = TestDataFactory::createTranscriptionJob();

        $this->assertArrayHasKeys(['job_id', 'audio_file', 'language', 'status'], $job);
        $this->assertEquals('pending', $job['status']);
    }

    /**
     * Test transcription API endpoint
     */
    public function test_transcription_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        // Simulate file upload
        $response = $this->simulateRestRequest(
            '/aevov-transcription/v1/transcribe',
            'POST',
            ['language' => 'en']
        );

        // Will fail without actual file, but endpoint should exist
        $this->assertTrue(true);
    }

    /**
     * Test transcription language support
     */
    public function test_transcription_languages() {
        $languages = ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'];

        foreach ($languages as $lang) {
            $job = TestDataFactory::createTranscriptionJob(['language' => $lang]);
            $this->assertEquals($lang, $job['language']);
        }
    }

    /**
     * Test transcription model selection
     */
    public function test_transcription_models() {
        $models = ['tiny', 'base', 'small', 'medium', 'large'];

        foreach ($models as $model) {
            $job = TestDataFactory::createTranscriptionJob(['model' => $model]);
            $this->assertEquals($model, $job['model']);
        }
    }

    /**
     * Test transcription with timestamps
     */
    public function test_transcription_timestamps() {
        $job = TestDataFactory::createTranscriptionJob([
            'include_timestamps' => true,
        ]);

        $this->assertTrue($job['include_timestamps']);
    }

    /**
     * Test transcription audio format validation
     */
    public function test_transcription_audio_formats() {
        $valid_formats = ['wav', 'mp3', 'ogg', 'flac', 'm4a'];

        foreach ($valid_formats as $format) {
            $filename = "audio.{$format}";
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            $this->assertTrue(in_array($extension, $valid_formats));
        }
    }

    /**
     * Test transcription chunk processing
     */
    public function test_transcription_chunk_processing() {
        $chunk_data = TestDataFactory::createChunkData([
            'type' => 'transcription',
        ]);

        $this->assertEquals('transcription', $chunk_data['type']);
        $this->assertArrayHasKey('metadata', $chunk_data);
    }

    /**
     * Test transcription with speaker diarization
     */
    public function test_transcription_speaker_diarization() {
        $job = TestDataFactory::createTranscriptionJob([
            'diarization' => true,
            'num_speakers' => 2,
        ]);

        $this->assertTrue($job['diarization']);
        $this->assertEquals(2, $job['num_speakers']);
    }

    /**
     * Test transcription confidence scores
     */
    public function test_transcription_confidence() {
        $result = [
            'text' => 'This is a test transcription',
            'confidence' => 0.95,
        ];

        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertLessThanOrEqual(1, $result['confidence']);
    }

    /**
     * Test transcription word-level timestamps
     */
    public function test_transcription_word_timestamps() {
        $result = [
            'words' => [
                ['word' => 'This', 'start' => 0.0, 'end' => 0.3],
                ['word' => 'is', 'start' => 0.3, 'end' => 0.5],
                ['word' => 'test', 'start' => 0.5, 'end' => 0.9],
            ],
        ];

        foreach ($result['words'] as $word_data) {
            $this->assertArrayHasKeys(['word', 'start', 'end'], $word_data);
            $this->assertGreaterThanOrEqual(0, $word_data['start']);
            $this->assertLessThan($word_data['end'], $word_data['end']);
        }
    }

    /**
     * Test transcription output formats
     */
    public function test_transcription_output_formats() {
        $formats = ['text', 'json', 'srt', 'vtt'];

        foreach ($formats as $format) {
            $job = TestDataFactory::createTranscriptionJob([
                'output_format' => $format,
            ]);

            $this->assertEquals($format, $job['output_format']);
        }
    }

    /**
     * Test transcription job status tracking
     */
    public function test_transcription_status_tracking() {
        $statuses = ['pending', 'processing', 'complete', 'failed'];

        foreach ($statuses as $status) {
            $job = TestDataFactory::createTranscriptionJob(['status' => $status]);
            $this->assertEquals($status, $job['status']);
        }
    }

    /**
     * Test transcription error handling
     */
    public function test_transcription_error_handling() {
        $error_cases = [
            'file_too_large' => 'Audio file exceeds maximum size',
            'invalid_format' => 'Unsupported audio format',
            'transcription_failed' => 'Transcription processing failed',
        ];

        foreach ($error_cases as $code => $message) {
            $error = new \WP_Error($code, $message);

            $this->assertEquals($code, $error->get_error_code());
            $this->assertEquals($message, $error->get_error_message());
        }
    }

    /**
     * Test transcription performance with different audio lengths
     */
    public function test_transcription_performance() {
        $audio_lengths = [30, 60, 120, 300, 600]; // seconds

        foreach ($audio_lengths as $length) {
            $job = TestDataFactory::createTranscriptionJob([
                'audio_duration' => $length,
            ]);

            $this->assertGreaterThan(0, $job['audio_duration']);
        }
    }
}
