<?php
/**
 * Watermark Manager - Visual and invisible watermarking for content protection
 *
 * Provides visible and forensic watermarking for images and video frames.
 *
 * @package AevovStream
 * @since 1.0.0
 */

namespace AevovStream;

if (!defined('ABSPATH')) {
    exit;
}

class WatermarkManager {

    /**
     * Watermark settings
     *
     * @var array
     */
    private $settings;

    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Watermarks table
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aevov_watermarks';

        $this->settings = $this->get_default_settings();

        add_action('init', [$this, 'create_tables']);
    }

    /**
     * Get default watermark settings
     *
     * @return array Settings
     */
    private function get_default_settings() {
        return wp_parse_args(
            get_option('aevov_watermark_settings', []),
            [
                'enabled' => true,
                'text' => get_bloginfo('name'),
                'font_size' => 14,
                'font_color' => '#ffffff',
                'opacity' => 0.5,
                'position' => 'bottom-right',
                'margin' => 10,
                'image_url' => '',
                'forensic_enabled' => true
            ]
        );
    }

    /**
     * Create database tables
     *
     * @return void
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            watermark_id VARCHAR(64) NOT NULL UNIQUE,
            content_id VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            watermark_type VARCHAR(50) NOT NULL DEFAULT 'visible',
            fingerprint VARCHAR(255) NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX content_id (content_id),
            INDEX user_id (user_id),
            INDEX fingerprint (fingerprint)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add watermark to image content
     *
     * @param string $image_path Path to image file or image data
     * @param array $options Watermark options
     * @return string|WP_Error Watermarked image data or path
     */
    public function add_watermark($image_path, $options = []) {
        $options = wp_parse_args($options, $this->settings);

        // Load image
        $image = $this->load_image($image_path);

        if (is_wp_error($image)) {
            return $image;
        }

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Apply visible watermark
        if ($options['enabled'] && !empty($options['text'])) {
            $this->apply_text_watermark($image, $width, $height, $options);
        }

        // Apply image watermark if specified
        if (!empty($options['image_url'])) {
            $this->apply_image_watermark($image, $width, $height, $options);
        }

        // Apply forensic watermark for tracking
        if ($options['forensic_enabled']) {
            $fingerprint = $this->apply_forensic_watermark($image, $options);
            $this->record_watermark($options['content_id'] ?? '', $fingerprint, $options);
        }

        // Output watermarked image
        return $this->output_image($image, $image_path, $options);
    }

    /**
     * Load image from path or data
     *
     * @param string $source Image path or raw data
     * @return resource|GdImage|WP_Error GD image resource
     */
    private function load_image($source) {
        // Check if it's raw image data
        if (strlen($source) > 1000 || !file_exists($source)) {
            $image = @imagecreatefromstring($source);
        } else {
            $mime = mime_content_type($source);

            switch ($mime) {
                case 'image/jpeg':
                    $image = @imagecreatefromjpeg($source);
                    break;
                case 'image/png':
                    $image = @imagecreatefrompng($source);
                    break;
                case 'image/gif':
                    $image = @imagecreatefromgif($source);
                    break;
                case 'image/webp':
                    $image = @imagecreatefromwebp($source);
                    break;
                default:
                    return new \WP_Error('unsupported_format', 'Unsupported image format: ' . $mime);
            }
        }

        if (!$image) {
            return new \WP_Error('image_load_failed', 'Failed to load image');
        }

        // Preserve transparency for PNG
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Apply text watermark
     *
     * @param resource|GdImage $image Image resource
     * @param int $width Image width
     * @param int $height Image height
     * @param array $options Watermark options
     * @return void
     */
    private function apply_text_watermark(&$image, $width, $height, $options) {
        $text = $options['text'];
        $font_size = $options['font_size'];
        $margin = $options['margin'];

        // Parse color
        $color = $this->parse_color($options['font_color'], $options['opacity']);

        // Create watermark color with alpha
        $watermark_color = imagecolorallocatealpha(
            $image,
            $color['r'],
            $color['g'],
            $color['b'],
            127 - (int)(127 * $color['a'])
        );

        // Calculate text position
        $font_file = $this->get_font_path();
        $bbox = imagettfbbox($font_size, 0, $font_file, $text);
        $text_width = abs($bbox[2] - $bbox[0]);
        $text_height = abs($bbox[7] - $bbox[1]);

        $position = $this->calculate_position(
            $options['position'],
            $width,
            $height,
            $text_width,
            $text_height,
            $margin
        );

        // Add shadow for readability
        $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 60);
        imagettftext($image, $font_size, 0, $position['x'] + 1, $position['y'] + 1, $shadow_color, $font_file, $text);

        // Add watermark text
        imagettftext($image, $font_size, 0, $position['x'], $position['y'], $watermark_color, $font_file, $text);
    }

    /**
     * Apply image watermark
     *
     * @param resource|GdImage $image Image resource
     * @param int $width Image width
     * @param int $height Image height
     * @param array $options Watermark options
     * @return void
     */
    private function apply_image_watermark(&$image, $width, $height, $options) {
        $watermark_path = $options['image_url'];

        // Download if URL
        if (filter_var($watermark_path, FILTER_VALIDATE_URL)) {
            $watermark_path = download_url($watermark_path);
            if (is_wp_error($watermark_path)) {
                return;
            }
        }

        $watermark = $this->load_image($watermark_path);
        if (is_wp_error($watermark)) {
            return;
        }

        $wm_width = imagesx($watermark);
        $wm_height = imagesy($watermark);

        // Scale watermark if needed (max 20% of image)
        $max_size = min($width, $height) * 0.2;
        if ($wm_width > $max_size || $wm_height > $max_size) {
            $scale = $max_size / max($wm_width, $wm_height);
            $new_width = (int)($wm_width * $scale);
            $new_height = (int)($wm_height * $scale);

            $scaled = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagecopyresampled($scaled, $watermark, 0, 0, 0, 0, $new_width, $new_height, $wm_width, $wm_height);
            imagedestroy($watermark);
            $watermark = $scaled;
            $wm_width = $new_width;
            $wm_height = $new_height;
        }

        $position = $this->calculate_position(
            $options['position'],
            $width,
            $height,
            $wm_width,
            $wm_height,
            $options['margin']
        );

        // Merge watermark with opacity
        $this->image_copy_merge_alpha(
            $image,
            $watermark,
            $position['x'],
            $position['y'] - $wm_height,
            0,
            0,
            $wm_width,
            $wm_height,
            (int)($options['opacity'] * 100)
        );

        imagedestroy($watermark);
    }

    /**
     * Apply forensic (invisible) watermark for tracking
     *
     * @param resource|GdImage $image Image resource
     * @param array $options Watermark options
     * @return string Fingerprint hash
     */
    private function apply_forensic_watermark(&$image, $options) {
        // Generate unique fingerprint
        $fingerprint = wp_generate_uuid4();
        $fingerprint_binary = $this->string_to_binary($fingerprint);

        $width = imagesx($image);
        $height = imagesy($image);

        // Embed fingerprint in LSB of pixels
        $bit_index = 0;
        $total_bits = strlen($fingerprint_binary);

        for ($y = 0; $y < $height && $bit_index < $total_bits; $y++) {
            for ($x = 0; $x < $width && $bit_index < $total_bits; $x++) {
                // Only modify every 10th pixel to reduce visibility
                if (($x + $y) % 10 !== 0) {
                    continue;
                }

                $rgb = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $rgb);

                // Modify blue channel LSB
                $bit = (int)$fingerprint_binary[$bit_index];
                $new_blue = ($colors['blue'] & 0xFE) | $bit;

                $new_color = imagecolorallocatealpha(
                    $image,
                    $colors['red'],
                    $colors['green'],
                    $new_blue,
                    $colors['alpha']
                );

                imagesetpixel($image, $x, $y, $new_color);
                $bit_index++;
            }
        }

        return hash('sha256', $fingerprint);
    }

    /**
     * Record watermark in database
     *
     * @param string $content_id Content identifier
     * @param string $fingerprint Watermark fingerprint
     * @param array $options Watermark options
     * @return void
     */
    private function record_watermark($content_id, $fingerprint, $options) {
        if (empty($content_id)) {
            return;
        }

        $this->wpdb->insert(
            $this->table_name,
            [
                'watermark_id' => wp_generate_uuid4(),
                'content_id' => $content_id,
                'user_id' => $options['user_id'] ?? get_current_user_id(),
                'watermark_type' => 'forensic',
                'fingerprint' => $fingerprint,
                'metadata' => json_encode([
                    'created_at' => current_time('mysql'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ])
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Calculate watermark position
     *
     * @param string $position Position name
     * @param int $img_width Image width
     * @param int $img_height Image height
     * @param int $wm_width Watermark width
     * @param int $wm_height Watermark height
     * @param int $margin Margin from edge
     * @return array X and Y coordinates
     */
    private function calculate_position($position, $img_width, $img_height, $wm_width, $wm_height, $margin) {
        switch ($position) {
            case 'top-left':
                return ['x' => $margin, 'y' => $margin + $wm_height];

            case 'top-center':
                return ['x' => ($img_width - $wm_width) / 2, 'y' => $margin + $wm_height];

            case 'top-right':
                return ['x' => $img_width - $wm_width - $margin, 'y' => $margin + $wm_height];

            case 'center':
                return ['x' => ($img_width - $wm_width) / 2, 'y' => ($img_height + $wm_height) / 2];

            case 'bottom-left':
                return ['x' => $margin, 'y' => $img_height - $margin];

            case 'bottom-center':
                return ['x' => ($img_width - $wm_width) / 2, 'y' => $img_height - $margin];

            case 'bottom-right':
            default:
                return ['x' => $img_width - $wm_width - $margin, 'y' => $img_height - $margin];
        }
    }

    /**
     * Parse color string to RGB array
     *
     * @param string $color Hex color string
     * @param float $alpha Alpha value (0-1)
     * @return array RGB values
     */
    private function parse_color($color, $alpha = 1) {
        $color = ltrim($color, '#');

        if (strlen($color) === 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        return [
            'r' => hexdec(substr($color, 0, 2)),
            'g' => hexdec(substr($color, 2, 2)),
            'b' => hexdec(substr($color, 4, 2)),
            'a' => $alpha
        ];
    }

    /**
     * Get font file path
     *
     * @return string Font file path
     */
    private function get_font_path() {
        $custom_font = get_option('aevov_watermark_font');

        if ($custom_font && file_exists($custom_font)) {
            return $custom_font;
        }

        // Use system font or bundled font
        $font_paths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            ABSPATH . 'wp-includes/fonts/open-sans.ttf',
            __DIR__ . '/fonts/OpenSans-Regular.ttf'
        ];

        foreach ($font_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback to GD built-in font (limited)
        return '';
    }

    /**
     * Convert string to binary representation
     *
     * @param string $string Input string
     * @return string Binary string
     */
    private function string_to_binary($string) {
        $binary = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $binary .= sprintf('%08b', ord($string[$i]));
        }
        return $binary;
    }

    /**
     * Image copy merge with alpha support
     *
     * @param resource $dst Destination image
     * @param resource $src Source image
     * @param int $dst_x Destination X
     * @param int $dst_y Destination Y
     * @param int $src_x Source X
     * @param int $src_y Source Y
     * @param int $src_w Source width
     * @param int $src_h Source height
     * @param int $pct Percentage
     * @return bool Success
     */
    private function image_copy_merge_alpha($dst, $src, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) {
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagealphablending($cut, false);
        imagesavealpha($cut, true);

        imagecopy($cut, $dst, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        imagecopy($cut, $src, 0, 0, $src_x, $src_y, $src_w, $src_h);
        imagecopymerge($dst, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);

        imagedestroy($cut);

        return true;
    }

    /**
     * Output watermarked image
     *
     * @param resource|GdImage $image Image resource
     * @param string $original_path Original image path
     * @param array $options Options
     * @return string Image data or path
     */
    private function output_image($image, $original_path, $options) {
        $output_format = $options['output_format'] ?? 'png';

        ob_start();

        switch ($output_format) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($image, null, $options['quality'] ?? 90);
                break;
            case 'gif':
                imagegif($image);
                break;
            case 'webp':
                imagewebp($image, null, $options['quality'] ?? 90);
                break;
            case 'png':
            default:
                imagepng($image, null, 9);
                break;
        }

        $data = ob_get_clean();
        imagedestroy($image);

        return $data;
    }

    /**
     * Detect watermark fingerprint from image
     *
     * @param string $image_path Image path or data
     * @return string|null Fingerprint hash or null if not found
     */
    public function detect_watermark($image_path) {
        $image = $this->load_image($image_path);

        if (is_wp_error($image)) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $binary = '';

        // Extract LSB from same pattern used for embedding
        for ($y = 0; $y < $height && strlen($binary) < 288; $y++) { // 36 chars * 8 bits
            for ($x = 0; $x < $width && strlen($binary) < 288; $x++) {
                if (($x + $y) % 10 !== 0) {
                    continue;
                }

                $rgb = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $rgb);
                $binary .= $colors['blue'] & 1;
            }
        }

        imagedestroy($image);

        // Convert binary back to string
        $fingerprint = '';
        for ($i = 0; $i < strlen($binary); $i += 8) {
            $byte = substr($binary, $i, 8);
            $fingerprint .= chr(bindec($byte));
        }

        // Look up fingerprint in database
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT watermark_id FROM {$this->table_name} WHERE fingerprint = %s",
            hash('sha256', $fingerprint)
        ));
    }

    /**
     * Get watermark info by fingerprint
     *
     * @param string $fingerprint Fingerprint hash
     * @return object|null Watermark record
     */
    public function get_watermark_info($fingerprint) {
        $record = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE fingerprint = %s",
            $fingerprint
        ));

        if ($record) {
            $record->metadata = json_decode($record->metadata, true);
        }

        return $record;
    }
}
