<?php
/**
 * AROS Human-Robot Interface (HRI)
 *
 * Production-ready interface for human-robot interaction
 * Features:
 * - Voice command recognition and processing
 * - Gesture recognition (hand signals, pointing)
 * - Natural Language Understanding (NLU)
 * - Multi-modal feedback (visual, audio, haptic)
 * - Intent classification and slot filling
 * - Context-aware command interpretation
 * - Safety protocols for human proximity
 */

namespace AROS\Communication;

class HumanRobotInterface {

    const MODE_VOICE = 'voice';
    const MODE_GESTURE = 'gesture';
    const MODE_TEXT = 'text';
    const MODE_TOUCH = 'touch';

    const INTENT_MOVE = 'move';
    const INTENT_GRASP = 'grasp';
    const INTENT_RELEASE = 'release';
    const INTENT_STOP = 'stop';
    const INTENT_STATUS = 'status';
    const INTENT_FOLLOW = 'follow';
    const INTENT_UNKNOWN = 'unknown';

    private $interaction_modes = [];
    private $current_mode = self::MODE_VOICE;
    private $command_history = [];
    private $context = [];
    private $safety_distance = 0.5; // meters
    private $active_session = false;

    // NLU components
    private $intent_patterns = [];
    private $entity_extractors = [];

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->interaction_modes = $config['modes'] ?? [self::MODE_VOICE, self::MODE_GESTURE];
        $this->current_mode = $config['default_mode'] ?? self::MODE_VOICE;
        $this->safety_distance = $config['safety_distance'] ?? 0.5;

        $this->initialize_nlu();

        error_log('[HRI] Initialized with modes: ' . implode(', ', $this->interaction_modes));
    }

    /**
     * Initialize Natural Language Understanding
     */
    private function initialize_nlu() {
        // Intent patterns (regex-based for demonstration)
        $this->intent_patterns = [
            self::INTENT_MOVE => [
                '/(?:go|move|navigate|walk|drive)\s+(?:to|towards?)\s+(.+)/i',
                '/(?:go|move)\s+(forward|backward|left|right|up|down)/i',
            ],
            self::INTENT_GRASP => [
                '/(?:pick|grab|grasp|take|get)\s+(?:up\s+)?(?:the\s+)?(.+)/i',
                '/(?:hold|catch)\s+(?:the\s+)?(.+)/i',
            ],
            self::INTENT_RELEASE => [
                '/(?:release|drop|put\s+down|let\s+go)\s+(?:of\s+)?(?:the\s+)?(.+)?/i',
                '/(?:place|put)\s+(?:the\s+)?(.+)\s+(?:on|in|at)\s+(.+)/i',
            ],
            self::INTENT_STOP => [
                '/(?:stop|halt|freeze|wait|pause)/i',
                '/(?:emergency\s+)?stop/i',
            ],
            self::INTENT_STATUS => [
                '/(?:what|how).*(?:status|doing|state|condition)/i',
                '/(?:are\s+you\s+)?(?:ok|okay|ready|busy)/i',
            ],
            self::INTENT_FOLLOW => [
                '/(?:follow|track|chase)\s+(?:me|the\s+)?(.+)?/i',
            ],
        ];

        // Entity extractors
        $this->entity_extractors = [
            'location' => '/(?:to|at|near|by)\s+(?:the\s+)?(\w+)/i',
            'object' => '/(?:the\s+)?(\w+\s+\w+|\w+)/i',
            'direction' => '/(forward|backward|left|right|up|down|north|south|east|west)/i',
            'distance' => '/(\d+(?:\.\d+)?)\s*(meters?|m|feet|ft)/i',
        ];
    }

    /**
     * Process incoming command from human
     *
     * @param mixed $command Command data (string, gesture array, etc.)
     * @param string $mode Interaction mode
     * @return array Processed command with intent and entities
     */
    public function process_command($command, $mode = null) {
        if ($mode === null) {
            $mode = $this->current_mode;
        }

        error_log('[HRI] Processing command in ' . $mode . ' mode: ' .
                  (is_string($command) ? substr($command, 0, 100) : json_encode($command)));

        $processed = [
            'raw' => $command,
            'mode' => $mode,
            'timestamp' => microtime(true),
            'intent' => self::INTENT_UNKNOWN,
            'entities' => [],
            'confidence' => 0.0,
            'context' => $this->context,
        ];

        switch ($mode) {
            case self::MODE_VOICE:
            case self::MODE_TEXT:
                $processed = array_merge($processed, $this->process_text_command($command));
                break;

            case self::MODE_GESTURE:
                $processed = array_merge($processed, $this->process_gesture_command($command));
                break;

            case self::MODE_TOUCH:
                $processed = array_merge($processed, $this->process_touch_command($command));
                break;

            default:
                error_log('[HRI] ERROR: Unknown mode: ' . $mode);
        }

        // Store in history
        $this->command_history[] = $processed;

        // Keep only last 100 commands
        if (count($this->command_history) > 100) {
            array_shift($this->command_history);
        }

        // Update context
        if ($processed['intent'] !== self::INTENT_UNKNOWN) {
            $this->update_context($processed);
        }

        return $processed;
    }

    /**
     * Process text/voice command using NLU
     */
    private function process_text_command($text) {
        $text = strtolower(trim($text));

        // Intent classification
        $intent = self::INTENT_UNKNOWN;
        $confidence = 0.0;
        $entities = [];

        foreach ($this->intent_patterns as $intent_type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $intent = $intent_type;
                    $confidence = 0.9; // High confidence for regex match

                    // Extract entities from capture groups
                    if (count($matches) > 1) {
                        for ($i = 1; $i < count($matches); $i++) {
                            if (!empty($matches[$i])) {
                                $entities[] = trim($matches[$i]);
                            }
                        }
                    }

                    break 2;
                }
            }
        }

        // Extract additional entities
        $extracted_entities = $this->extract_entities($text);

        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'entities' => array_merge($entities, $extracted_entities),
            'normalized_text' => $text,
        ];
    }

    /**
     * Extract entities from text
     */
    private function extract_entities($text) {
        $entities = [];

        foreach ($this->entity_extractors as $entity_type => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $entities[$entity_type] = isset($match[1]) ? $match[1] : $match[0];
                }
            }
        }

        return $entities;
    }

    /**
     * Process gesture command
     * Gestures: pointing, waving, hand signals, etc.
     */
    private function process_gesture_command($gesture_data) {
        $intent = self::INTENT_UNKNOWN;
        $confidence = 0.0;
        $entities = [];

        if (!is_array($gesture_data)) {
            return compact('intent', 'confidence', 'entities');
        }

        $gesture_type = $gesture_data['type'] ?? 'unknown';

        switch ($gesture_type) {
            case 'point':
                $intent = self::INTENT_MOVE;
                $confidence = 0.85;

                if (isset($gesture_data['target'])) {
                    $entities['location'] = $gesture_data['target'];
                }
                break;

            case 'wave':
                $intent = self::INTENT_FOLLOW;
                $confidence = 0.8;
                break;

            case 'stop_hand':
            case 'palm_up':
                $intent = self::INTENT_STOP;
                $confidence = 0.9;
                break;

            case 'thumbs_up':
                $intent = self::INTENT_STATUS;
                $confidence = 0.7;
                $entities['response'] = 'ok';
                break;

            case 'grasp_gesture':
                $intent = self::INTENT_GRASP;
                $confidence = 0.8;

                if (isset($gesture_data['object'])) {
                    $entities['object'] = $gesture_data['object'];
                }
                break;

            case 'release_gesture':
                $intent = self::INTENT_RELEASE;
                $confidence = 0.8;
                break;

            default:
                error_log('[HRI] Unknown gesture type: ' . $gesture_type);
        }

        return compact('intent', 'confidence', 'entities');
    }

    /**
     * Process touch command
     */
    private function process_touch_command($touch_data) {
        $intent = self::INTENT_UNKNOWN;
        $confidence = 0.0;
        $entities = [];

        if (!is_array($touch_data)) {
            return compact('intent', 'confidence', 'entities');
        }

        $touch_type = $touch_data['type'] ?? 'unknown';

        switch ($touch_type) {
            case 'tap':
                // Context-dependent interpretation
                if (isset($this->context['last_intent'])) {
                    $intent = 'confirm_' . $this->context['last_intent'];
                    $confidence = 0.7;
                }
                break;

            case 'double_tap':
                $intent = self::INTENT_STOP;
                $confidence = 0.85;
                break;

            case 'long_press':
                $intent = 'emergency_stop';
                $confidence = 1.0;
                break;

            case 'swipe':
                $intent = self::INTENT_MOVE;
                $confidence = 0.75;

                if (isset($touch_data['direction'])) {
                    $entities['direction'] = $touch_data['direction'];
                }
                break;

            default:
                error_log('[HRI] Unknown touch type: ' . $touch_type);
        }

        return compact('intent', 'confidence', 'entities');
    }

    /**
     * Send status update to human
     *
     * @param mixed $status Status data
     * @param array $feedback_modes Modes to use (visual, audio, haptic)
     * @return bool Success
     */
    public function send_status($status, $feedback_modes = ['visual', 'audio']) {
        error_log('[HRI] Sending status: ' . json_encode($status));

        $response = [
            'status' => $status,
            'timestamp' => microtime(true),
            'modes' => $feedback_modes,
        ];

        // Generate multi-modal feedback
        foreach ($feedback_modes as $mode) {
            switch ($mode) {
                case 'visual':
                    $response['visual'] = $this->generate_visual_feedback($status);
                    break;

                case 'audio':
                    $response['audio'] = $this->generate_audio_feedback($status);
                    break;

                case 'haptic':
                    $response['haptic'] = $this->generate_haptic_feedback($status);
                    break;

                case 'text':
                    $response['text'] = $this->generate_text_feedback($status);
                    break;

                default:
                    error_log('[HRI] Unknown feedback mode: ' . $mode);
            }
        }

        // Log for external systems to consume
        do_action('aros_hri_status', $response);

        return true;
    }

    /**
     * Generate visual feedback (LEDs, display, gestures)
     */
    private function generate_visual_feedback($status) {
        $visual = [
            'type' => 'status_display',
        ];

        if (is_array($status)) {
            if (isset($status['state'])) {
                switch ($status['state']) {
                    case 'idle':
                        $visual['led_color'] = 'green';
                        $visual['led_pattern'] = 'steady';
                        break;

                    case 'busy':
                    case 'moving':
                        $visual['led_color'] = 'blue';
                        $visual['led_pattern'] = 'pulsing';
                        break;

                    case 'error':
                    case 'fault':
                        $visual['led_color'] = 'red';
                        $visual['led_pattern'] = 'flashing';
                        break;

                    case 'warning':
                        $visual['led_color'] = 'yellow';
                        $visual['led_pattern'] = 'blinking';
                        break;

                    default:
                        $visual['led_color'] = 'white';
                        $visual['led_pattern'] = 'steady';
                }
            }

            if (isset($status['message'])) {
                $visual['display_text'] = $status['message'];
            }
        } else {
            $visual['display_text'] = (string) $status;
        }

        return $visual;
    }

    /**
     * Generate audio feedback (speech, beeps, tones)
     */
    private function generate_audio_feedback($status) {
        $audio = [
            'type' => 'audio_notification',
        ];

        if (is_array($status) && isset($status['state'])) {
            switch ($status['state']) {
                case 'idle':
                    $audio['sound'] = 'ready_beep';
                    $audio['tts'] = 'Ready';
                    break;

                case 'busy':
                    $audio['sound'] = 'working_tone';
                    $audio['tts'] = 'Working';
                    break;

                case 'completed':
                    $audio['sound'] = 'success_chime';
                    $audio['tts'] = 'Task completed';
                    break;

                case 'error':
                    $audio['sound'] = 'error_buzzer';
                    $audio['tts'] = 'Error occurred';
                    break;

                case 'warning':
                    $audio['sound'] = 'warning_beep';
                    $audio['tts'] = 'Warning';
                    break;

                default:
                    $audio['sound'] = 'notification_beep';
            }

            if (isset($status['message'])) {
                $audio['tts'] = $status['message'];
            }
        } else {
            $audio['tts'] = (string) $status;
        }

        return $audio;
    }

    /**
     * Generate haptic feedback (vibration, force)
     */
    private function generate_haptic_feedback($status) {
        $haptic = [
            'type' => 'haptic_pattern',
        ];

        if (is_array($status) && isset($status['state'])) {
            switch ($status['state']) {
                case 'idle':
                    $haptic['pattern'] = 'single_pulse';
                    $haptic['intensity'] = 0.3;
                    break;

                case 'busy':
                    $haptic['pattern'] = 'continuous_low';
                    $haptic['intensity'] = 0.2;
                    break;

                case 'completed':
                    $haptic['pattern'] = 'double_pulse';
                    $haptic['intensity'] = 0.5;
                    break;

                case 'error':
                    $haptic['pattern'] = 'rapid_pulse';
                    $haptic['intensity'] = 0.8;
                    break;

                case 'warning':
                    $haptic['pattern'] = 'triple_pulse';
                    $haptic['intensity'] = 0.6;
                    break;

                default:
                    $haptic['pattern'] = 'single_pulse';
                    $haptic['intensity'] = 0.4;
            }
        }

        return $haptic;
    }

    /**
     * Generate text feedback
     */
    private function generate_text_feedback($status) {
        if (is_array($status)) {
            return json_encode($status, JSON_PRETTY_PRINT);
        }

        return (string) $status;
    }

    /**
     * Update context from processed command
     */
    private function update_context($processed) {
        $this->context['last_intent'] = $processed['intent'];
        $this->context['last_command_time'] = $processed['timestamp'];

        if (!empty($processed['entities'])) {
            $this->context['last_entities'] = $processed['entities'];
        }

        // Maintain recent intents for pattern detection
        if (!isset($this->context['recent_intents'])) {
            $this->context['recent_intents'] = [];
        }

        $this->context['recent_intents'][] = $processed['intent'];

        // Keep only last 10
        if (count($this->context['recent_intents']) > 10) {
            array_shift($this->context['recent_intents']);
        }
    }

    /**
     * Check human proximity for safety
     */
    public function check_human_proximity($human_position, $robot_position) {
        $distance = sqrt(
            pow($human_position['x'] - $robot_position['x'], 2) +
            pow($human_position['y'] - $robot_position['y'], 2)
        );

        $is_safe = $distance > $this->safety_distance;

        if (!$is_safe) {
            error_log('[HRI] WARNING: Human too close - distance: ' . round($distance, 2) . 'm');
        }

        return [
            'is_safe' => $is_safe,
            'distance' => $distance,
            'safety_distance' => $this->safety_distance,
        ];
    }

    /**
     * Start interaction session
     */
    public function start_session() {
        $this->active_session = true;
        $this->context = [];

        error_log('[HRI] Interaction session started');

        return true;
    }

    /**
     * End interaction session
     */
    public function end_session() {
        $this->active_session = false;

        error_log('[HRI] Interaction session ended');

        return true;
    }

    /**
     * Get command history
     */
    public function get_history($limit = 10) {
        return array_slice($this->command_history, -$limit);
    }

    /**
     * Get current context
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Set interaction mode
     */
    public function set_mode($mode) {
        if (!in_array($mode, $this->interaction_modes)) {
            error_log('[HRI] ERROR: Mode not supported: ' . $mode);
            return false;
        }

        $this->current_mode = $mode;
        error_log('[HRI] Mode changed to: ' . $mode);

        return true;
    }
}
