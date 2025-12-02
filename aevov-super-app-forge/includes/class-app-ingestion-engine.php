<?php

namespace AevovSuperAppForge;

use Exception;

/**
 * App Ingestion Engine for Super App Forge
 *
 * Performs static and dynamic analysis of applications to extract
 * their structure, components, dependencies, and behavior patterns.
 * Generates Universal App Definition (UAD) for app reconstruction.
 */
class AppIngestionEngine {

    private $temp_dir;
    private $analysis_cache = [];

    /**
     * Initialize App Ingestion Engine
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->temp_dir = $upload_dir['basedir'] . '/aevov-forge-temp/';

        // Ensure temp directory exists
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }

        // Register hooks
        add_action('aevov_forge_cleanup', [$this, 'cleanup_temp_files']);
    }

    /**
     * Ingest and analyze an application
     *
     * @param string $url Application URL or local path.
     * @param array $options Ingestion options.
     * @return array|WP_Error Universal App Definition (UAD) or error.
     */
    public function ingest_app( $url, $options = [] ) {
        try {
            error_log("Starting app ingestion for: {$url}");

            // Validate URL
            if (!$this->validate_source($url)) {
                return new \WP_Error('invalid_source', 'Invalid application source');
            }

            // Download/clone source if needed
            $local_path = $this->acquire_source($url);

            if (is_wp_error($local_path)) {
                return $local_path;
            }

            // Detect app type
            $app_type = $this->detect_app_type($local_path);

            // Perform static analysis
            $components = $this->static_analysis($local_path, $app_type);

            // Perform dynamic analysis (if enabled)
            $logic = [];
            if (!empty($options['dynamic_analysis'])) {
                $logic = $this->dynamic_analysis($local_path, $app_type);
            }

            // Extract dependencies
            $dependencies = $this->extract_dependencies($local_path, $app_type);

            // Build Universal App Definition
            $uad = [
                'version' => '1.0',
                'metadata' => [
                    'source_url' => $url,
                    'app_type' => $app_type,
                    'ingestion_date' => current_time('mysql'),
                    'ingested_by' => get_current_user_id(),
                    'local_path' => $local_path,
                    'file_count' => $this->count_files($local_path),
                    'size_bytes' => $this->calculate_size($local_path)
                ],
                'components' => $components,
                'dependencies' => $dependencies,
                'logic' => $logic,
                'architecture' => $this->analyze_architecture($components),
                'api_endpoints' => $this->extract_api_endpoints($local_path, $app_type),
                'database_schema' => $this->extract_database_schema($local_path),
                'assets' => $this->catalog_assets($local_path),
                'security_analysis' => $this->perform_security_scan($local_path)
            ];

            error_log("App ingestion completed successfully");

            return $uad;

        } catch (Exception $e) {
            error_log("App ingestion failed: " . $e->getMessage());
            return new \WP_Error('ingestion_failed', $e->getMessage());
        }
    }

    /**
     * Perform static code analysis
     *
     * @param string $path Local path to application.
     * @param string $app_type Application type.
     * @return array Components found.
     */
    private function static_analysis( $path, $app_type ) {
        error_log("Starting static analysis for {$app_type} app");

        $components = [
            'entry_points' => $this->find_entry_points($path, $app_type),
            'modules' => $this->identify_modules($path, $app_type),
            'classes' => $this->extract_classes($path),
            'functions' => $this->extract_functions($path),
            'routes' => $this->extract_routes($path, $app_type),
            'views' => $this->find_views($path, $app_type),
            'controllers' => $this->find_controllers($path, $app_type),
            'models' => $this->find_models($path, $app_type),
            'configuration' => $this->extract_configuration($path)
        ];

        return $components;
    }

    /**
     * Perform dynamic runtime analysis
     *
     * @param string $path Local path to application.
     * @param string $app_type Application type.
     * @return array Behavioral data.
     */
    private function dynamic_analysis( $path, $app_type ) {
        error_log("Starting dynamic analysis for {$app_type} app");

        // In a real implementation, this would run the app in a sandbox
        // For now, we'll simulate behavioral analysis

        $logic = [
            'execution_flow' => $this->trace_execution_flow($path),
            'network_requests' => $this->analyze_network_behavior($path),
            'state_management' => $this->analyze_state($path, $app_type),
            'user_interactions' => $this->map_user_interactions($path),
            'data_flows' => $this->trace_data_flows($path),
            'event_handlers' => $this->extract_event_handlers($path),
            'lifecycle_hooks' => $this->extract_lifecycle_hooks($path, $app_type)
        ];

        return $logic;
    }

    /**
     * Detect application type from source code
     *
     * @param string $path Local path.
     * @return string Application type.
     */
    private function detect_app_type( $path ) {
        // Check for framework indicators
        if (file_exists($path . '/package.json')) {
            $package = json_decode(file_get_contents($path . '/package.json'), true);

            if (isset($package['dependencies']['react'])) {
                return 'react';
            } elseif (isset($package['dependencies']['vue'])) {
                return 'vue';
            } elseif (isset($package['dependencies']['@angular/core'])) {
                return 'angular';
            } elseif (isset($package['dependencies']['express'])) {
                return 'node-api';
            }
        }

        if (file_exists($path . '/composer.json')) {
            return 'php';
        }

        if (file_exists($path . '/requirements.txt') || file_exists($path . '/setup.py')) {
            return 'python';
        }

        if (file_exists($path . '/pubspec.yaml')) {
            return 'flutter';
        }

        if (file_exists($path . '/AndroidManifest.xml')) {
            return 'android';
        }

        if (file_exists($path . '/Info.plist')) {
            return 'ios';
        }

        return 'unknown';
    }

    /**
     * Validate application source
     *
     * @param string $url Source URL.
     * @return bool Valid status.
     */
    private function validate_source( $url ) {
        // Check if URL or local path
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }

        if (file_exists($url)) {
            return true;
        }

        return false;
    }

    /**
     * Acquire source code locally
     *
     * @param string $url Source URL.
     * @return string|WP_Error Local path or error.
     */
    private function acquire_source( $url ) {
        // If already a local path, use it
        if (file_exists($url) && is_dir($url)) {
            return $url;
        }

        // Download from URL
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $temp_path = $this->temp_dir . 'app_' . uniqid();
            wp_mkdir_p($temp_path);

            // Clone git repository or download archive
            if (strpos($url, '.git') !== false) {
                return $this->clone_repository($url, $temp_path);
            } else {
                return $this->download_archive($url, $temp_path);
            }
        }

        return new \WP_Error('source_unavailable', 'Unable to acquire application source');
    }

    /**
     * Clone git repository
     *
     * @param string $url Repository URL.
     * @param string $dest Destination path.
     * @return string|WP_Error Local path or error.
     */
    private function clone_repository( $url, $dest ) {
        // Simulate git clone (in production, would use actual git commands)
        error_log("Cloning repository: {$url}");

        // For simulation, create directory structure
        wp_mkdir_p($dest . '/src');
        wp_mkdir_p($dest . '/tests');

        return $dest;
    }

    /**
     * Download and extract archive
     *
     * @param string $url Archive URL.
     * @param string $dest Destination path.
     * @return string|WP_Error Local path or error.
     */
    private function download_archive( $url, $dest ) {
        error_log("Downloading archive: {$url}");

        // Simulate download (in production, would use wp_remote_get)
        wp_mkdir_p($dest);

        return $dest;
    }

    /**
     * Find application entry points
     *
     * @param string $path Application path.
     * @param string $app_type Application type.
     * @return array Entry points.
     */
    private function find_entry_points( $path, $app_type ) {
        $entry_points = [];

        $patterns = [
            'react' => ['src/index.js', 'src/index.jsx', 'src/App.js'],
            'vue' => ['src/main.js', 'src/main.ts'],
            'angular' => ['src/main.ts'],
            'php' => ['index.php', 'app.php', 'public/index.php'],
            'python' => ['main.py', 'app.py', '__init__.py']
        ];

        $check_patterns = $patterns[$app_type] ?? $patterns['php'];

        foreach ($check_patterns as $pattern) {
            $file_path = $path . '/' . $pattern;
            if (file_exists($file_path)) {
                $entry_points[] = [
                    'file' => $pattern,
                    'type' => 'main',
                    'size' => filesize($file_path)
                ];
            }
        }

        return $entry_points;
    }

    /**
     * Identify application modules
     *
     * @param string $path Application path.
     * @param string $app_type Application type.
     * @return array Modules.
     */
    private function identify_modules( $path, $app_type ) {
        $modules = [];

        // Scan for common module directories
        $module_dirs = ['src', 'app', 'lib', 'modules', 'components'];

        foreach ($module_dirs as $dir) {
            $full_path = $path . '/' . $dir;
            if (is_dir($full_path)) {
                $modules[$dir] = $this->scan_directory($full_path);
            }
        }

        return $modules;
    }

    /**
     * Extract classes from source code
     *
     * @param string $path Application path.
     * @return array Classes found.
     */
    private function extract_classes( $path ) {
        $classes = [];

        // Scan PHP files for class definitions
        $files = $this->find_files_by_extension($path, ['php']);

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Extract class names using regex
            preg_match_all('/class\s+(\w+)/', $content, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $class_name) {
                    $classes[] = [
                        'name' => $class_name,
                        'file' => str_replace($path . '/', '', $file),
                        'methods' => $this->extract_methods_from_file($file)
                    ];
                }
            }
        }

        return $classes;
    }

    /**
     * Extract functions from source code
     *
     * @param string $path Application path.
     * @return array Functions found.
     */
    private function extract_functions( $path ) {
        $functions = [];

        $files = $this->find_files_by_extension($path, ['php', 'js', 'py']);

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Extract function names
            preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $func_name) {
                    $functions[] = [
                        'name' => $func_name,
                        'file' => str_replace($path . '/', '', $file)
                    ];
                }
            }
        }

        return array_slice($functions, 0, 100); // Limit to first 100
    }

    /**
     * Extract routes/endpoints
     *
     * @param string $path Application path.
     * @param string $app_type Application type.
     * @return array Routes.
     */
    private function extract_routes( $path, $app_type ) {
        $routes = [];

        // Look for route definitions
        $route_files = $this->find_files_by_pattern($path, '*route*');

        foreach ($route_files as $file) {
            $content = file_get_contents($file);

            // Extract route patterns (simplified)
            preg_match_all('/[\'"]\/[\w\/-]+[\'"]/', $content, $matches);

            if (!empty($matches[0])) {
                foreach ($matches[0] as $route) {
                    $routes[] = trim($route, '\'"');
                }
            }
        }

        return array_unique($routes);
    }

    /**
     * Extract dependencies
     *
     * @param string $path Application path.
     * @param string $app_type Application type.
     * @return array Dependencies.
     */
    private function extract_dependencies( $path, $app_type ) {
        $dependencies = [];

        // Check package.json
        if (file_exists($path . '/package.json')) {
            $package = json_decode(file_get_contents($path . '/package.json'), true);
            $dependencies['npm'] = array_merge(
                $package['dependencies'] ?? [],
                $package['devDependencies'] ?? []
            );
        }

        // Check composer.json
        if (file_exists($path . '/composer.json')) {
            $composer = json_decode(file_get_contents($path . '/composer.json'), true);
            $dependencies['composer'] = $composer['require'] ?? [];
        }

        // Check requirements.txt
        if (file_exists($path . '/requirements.txt')) {
            $requirements = file($path . '/requirements.txt', FILE_IGNORE_NEW_LINES);
            $dependencies['pip'] = $requirements;
        }

        return $dependencies;
    }

    /**
     * Analyze application architecture
     *
     * @param array $components Components data.
     * @return array Architecture analysis.
     */
    private function analyze_architecture( $components ) {
        return [
            'pattern' => $this->detect_architecture_pattern($components),
            'layers' => $this->identify_layers($components),
            'complexity_score' => $this->calculate_complexity($components)
        ];
    }

    /**
     * Find files by extension
     *
     * @param string $path Directory path.
     * @param array $extensions Extensions to find.
     * @return array File paths.
     */
    private function find_files_by_extension( $path, $extensions ) {
        $files = [];

        if (!is_dir($path)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = $file->getExtension();
                if (in_array($ext, $extensions)) {
                    $files[] = $file->getPathname();
                }
            }

            // Limit to prevent memory issues
            if (count($files) >= 500) {
                break;
            }
        }

        return $files;
    }

    /**
     * Scan directory structure
     *
     * @param string $path Directory path.
     * @return array Directory structure.
     */
    private function scan_directory( $path ) {
        $structure = [];

        if (!is_dir($path)) {
            return $structure;
        }

        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $path . '/' . $item;
            $structure[$item] = is_dir($full_path) ? 'directory' : 'file';
        }

        return $structure;
    }

    /**
     * Find view files in application
     *
     * @param string $path Application path
     * @param string $app_type Application type
     * @return array View files with metadata
     */
    private function find_views( $path, $app_type ) {
        $views = [];

        // Define view patterns by framework
        $patterns = $this->get_view_patterns($app_type);

        foreach ($patterns as $pattern) {
            $files = $this->find_files_by_pattern($path, $pattern['pattern']);

            foreach ($files as $file) {
                $content = file_get_contents($file);
                $views[] = [
                    'file' => str_replace($path, '', $file),
                    'type' => $pattern['type'],
                    'framework' => $app_type,
                    'size' => filesize($file),
                    'variables' => $this->extract_template_variables($content, $pattern['type']),
                    'components' => $this->extract_template_components($content, $pattern['type']),
                ];
            }
        }

        return $views;
    }

    /**
     * Find controller files in application
     *
     * @param string $path Application path
     * @param string $app_type Application type
     * @return array Controller files with metadata
     */
    private function find_controllers( $path, $app_type ) {
        $controllers = [];

        $patterns = [
            'react' => ['*Controller.js', '*Controller.jsx', 'controllers/*.js'],
            'vue' => ['*Controller.js', 'controllers/*.js'],
            'angular' => ['*.controller.ts', '*.controller.js'],
            'laravel' => ['app/Http/Controllers/*.php'],
            'symfony' => ['src/Controller/*.php'],
            'rails' => ['app/controllers/*_controller.rb'],
        ];

        $search_patterns = $patterns[$app_type] ?? ['*Controller.*', 'controllers/*.*'];

        foreach ($search_patterns as $pattern) {
            $files = $this->find_files_by_pattern($path, $pattern);

            foreach ($files as $file) {
                $content = file_get_contents($file);
                $methods = $this->extract_methods_from_file($file);

                $controllers[] = [
                    'file' => str_replace($path, '', $file),
                    'class' => $this->extract_class_name($content),
                    'methods' => $methods,
                    'routes' => $this->extract_route_mappings($content, $app_type),
                    'dependencies' => $this->extract_dependencies($content),
                ];
            }
        }

        return $controllers;
    }

    /**
     * Find model/entity files in application
     *
     * @param string $path Application path
     * @param string $app_type Application type
     * @return array Model files with metadata
     */
    private function find_models( $path, $app_type ) {
        $models = [];

        $patterns = [
            'react' => ['models/*.js', 'models/*.ts', 'store/*.js'],
            'vue' => ['models/*.js', 'models/*.ts', 'store/*.js'],
            'angular' => ['*.model.ts', 'models/*.ts'],
            'laravel' => ['app/Models/*.php'],
            'symfony' => ['src/Entity/*.php'],
            'rails' => ['app/models/*.rb'],
        ];

        $search_patterns = $patterns[$app_type] ?? ['models/*.*', '*Model.*'];

        foreach ($search_patterns as $pattern) {
            $files = $this->find_files_by_pattern($path, $pattern);

            foreach ($files as $file) {
                $content = file_get_contents($file);

                $models[] = [
                    'file' => str_replace($path, '', $file),
                    'class' => $this->extract_class_name($content),
                    'properties' => $this->extract_properties($content),
                    'methods' => $this->extract_methods_from_file($file),
                    'relationships' => $this->extract_relationships($content, $app_type),
                    'validations' => $this->extract_validations($content, $app_type),
                ];
            }
        }

        return $models;
    }

    /**
     * Extract configuration files and settings
     *
     * @param string $path Application path
     * @return array Configuration data
     */
    private function extract_configuration( $path ) {
        $config = [];

        // Common config file patterns
        $config_files = [
            'package.json',
            'composer.json',
            '.env',
            '.env.example',
            'config.json',
            'app.config.js',
            'webpack.config.js',
            'tsconfig.json',
            'angular.json',
            'vue.config.js',
            'next.config.js',
            'nuxt.config.js',
            'config/*.php',
            'config/*.yml',
            'config/*.yaml',
        ];

        foreach ($config_files as $pattern) {
            $files = $this->find_files_by_pattern($path, $pattern);

            foreach ($files as $file) {
                $filename = basename($file);
                $content = file_get_contents($file);

                $config[$filename] = [
                    'file' => str_replace($path, '', $file),
                    'type' => $this->detect_config_type($filename),
                    'data' => $this->parse_config_file($content, $filename),
                ];
            }
        }

        return $config;
    }

    /**
     * Trace application execution flow
     *
     * @param string $path Application path
     * @return array Execution flow map
     */
    private function trace_execution_flow( $path ) {
        $flow = [];

        // Find entry points
        $entry_points = $this->find_entry_points($path);

        foreach ($entry_points as $entry) {
            $content = file_get_contents($entry);

            $flow[] = [
                'entry_point' => str_replace($path, '', $entry),
                'imports' => $this->extract_imports($content),
                'function_calls' => $this->extract_function_calls($content),
                'async_operations' => $this->extract_async_operations($content),
                'event_listeners' => $this->extract_event_listeners($content),
            ];
        }

        return $flow;
    }

    /**
     * Analyze network behavior and API calls
     *
     * @param string $path Application path
     * @return array Network behavior analysis
     */
    private function analyze_network_behavior( $path ) {
        $network = [
            'http_clients' => [],
            'api_calls' => [],
            'websockets' => [],
            'graphql' => [],
        ];

        // Scan all source files
        $files = $this->find_files_by_pattern($path, '*.{js,jsx,ts,tsx,php,py}');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Detect HTTP clients
            if (preg_match_all('/(fetch|axios|http|request|XMLHttpRequest)\s*\(/', $content, $matches)) {
                $network['http_clients'][] = [
                    'file' => str_replace($path, '', $file),
                    'type' => $matches[1][0] ?? 'unknown',
                ];
            }

            // Extract API endpoints
            if (preg_match_all('/[\'"]((https?:)?\/\/[^\'"\s]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $url) {
                    $network['api_calls'][] = [
                        'url' => $url,
                        'file' => str_replace($path, '', $file),
                    ];
                }
            }

            // Detect WebSocket usage
            if (preg_match('/new\s+WebSocket\s*\(|io\s*\(|socket\.io/', $content)) {
                $network['websockets'][] = str_replace($path, '', $file);
            }

            // Detect GraphQL
            if (preg_match('/(graphql|gql|Apollo|useQuery|useMutation)/', $content)) {
                $network['graphql'][] = str_replace($path, '', $file);
            }
        }

        return $network;
    }

    /**
     * Analyze state management
     *
     * @param string $path Application path
     * @param string $app_type Application type
     * @return array State management analysis
     */
    private function analyze_state( $path, $app_type ) {
        $state = [
            'type' => 'none',
            'stores' => [],
            'actions' => [],
            'reducers' => [],
        ];

        $files = $this->find_files_by_pattern($path, '*.{js,jsx,ts,tsx}');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Detect Redux
            if (preg_match('/(createStore|configureStore|useSelector|useDispatch)/', $content)) {
                $state['type'] = 'redux';
                $state['stores'][] = str_replace($path, '', $file);
            }

            // Detect Vuex
            if (preg_match('/(new\s+Vuex\.Store|createStore|mapState|mapGetters)/', $content)) {
                $state['type'] = 'vuex';
                $state['stores'][] = str_replace($path, '', $file);
            }

            // Detect MobX
            if (preg_match('/(observable|action|computed|makeObservable)/', $content)) {
                $state['type'] = 'mobx';
                $state['stores'][] = str_replace($path, '', $file);
            }

            // Extract actions/mutations
            if (preg_match_all('/export\s+(const|function)\s+(\w+)\s*=/', $content, $matches)) {
                $state['actions'] = array_merge($state['actions'], $matches[2]);
            }
        }

        return $state;
    }

    /**
     * Map user interaction patterns
     *
     * @param string $path Application path
     * @return array User interaction map
     */
    private function map_user_interactions( $path ) {
        $interactions = [
            'click_handlers' => [],
            'form_submissions' => [],
            'keyboard_events' => [],
            'touch_events' => [],
            'drag_drop' => [],
        ];

        $files = $this->find_files_by_pattern($path, '*.{js,jsx,ts,tsx,html,vue}');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative_file = str_replace($path, '', $file);

            // Click handlers
            if (preg_match_all('/(onClick|@click|on-click|click=)/', $content, $matches)) {
                $interactions['click_handlers'][] = [
                    'file' => $relative_file,
                    'count' => count($matches[0]),
                ];
            }

            // Form submissions
            if (preg_match_all('/(onSubmit|@submit|on-submit|submit=)/', $content, $matches)) {
                $interactions['form_submissions'][] = [
                    'file' => $relative_file,
                    'count' => count($matches[0]),
                ];
            }

            // Keyboard events
            if (preg_match_all('/(onKeyDown|onKeyUp|onKeyPress|@keydown|@keyup)/', $content, $matches)) {
                $interactions['keyboard_events'][] = [
                    'file' => $relative_file,
                    'count' => count($matches[0]),
                ];
            }

            // Drag and drop
            if (preg_match('/(onDrag|onDrop|draggable|@drag|@drop)/', $content)) {
                $interactions['drag_drop'][] = $relative_file;
            }
        }

        return $interactions;
    }

    /**
     * Trace data flow through application
     *
     * @param string $path Application path
     * @return array Data flow analysis
     */
    private function trace_data_flows( $path ) {
        $flows = [
            'prop_passing' => [],
            'context_usage' => [],
            'event_emitters' => [],
            'data_bindings' => [],
        ];

        $files = $this->find_files_by_pattern($path, '*.{js,jsx,ts,tsx,vue}');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Props passing
            if (preg_match_all('/(\w+)=\{([^}]+)\}/', $content, $matches)) {
                $flows['prop_passing'][] = [
                    'file' => str_replace($path, '', $file),
                    'props' => array_unique($matches[1]),
                ];
            }

            // Context usage
            if (preg_match('/(useContext|createContext|Provider|Consumer)/', $content)) {
                $flows['context_usage'][] = str_replace($path, '', $file);
            }

            // Event emitters
            if (preg_match_all('/(\$emit|\$dispatch|emit\(|dispatchEvent)/', $content, $matches)) {
                $flows['event_emitters'][] = str_replace($path, '', $file);
            }
        }

        return $flows;
    }

    /**
     * Extract event handlers
     *
     * @param string $path Application path
     * @return array Event handlers
     */
    private function extract_event_handlers( $path ) {
        $handlers = [];

        $files = $this->find_files_by_pattern($path, '*.{js,jsx,ts,tsx}');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // addEventListener patterns
            if (preg_match_all('/addEventListener\([\'"](\w+)[\'"]\s*,\s*(\w+)/', $content, $matches)) {
                foreach ($matches[1] as $i => $event) {
                    $handlers[] = [
                        'file' => str_replace($path, '', $file),
                        'event' => $event,
                        'handler' => $matches[2][$i] ?? 'anonymous',
                    ];
                }
            }

            // React event handlers
            if (preg_match_all('/on(\w+)=\{([^}]+)\}/', $content, $matches)) {
                foreach ($matches[1] as $i => $event) {
                    $handlers[] = [
                        'file' => str_replace($path, '', $file),
                        'event' => strtolower($event),
                        'handler' => trim($matches[2][$i]),
                        'framework' => 'react',
                    ];
                }
            }
        }

        return $handlers;
    }

    /**
     * Extract lifecycle hooks
     *
     * @param string $path Application path
     * @param string $app_type Application type
     * @return array Lifecycle hooks
     */
    private function extract_lifecycle_hooks( $path, $app_type ) {
        $hooks = [];

        $lifecycle_patterns = [
            'react' => ['useEffect', 'componentDidMount', 'componentWillUnmount', 'componentDidUpdate'],
            'vue' => ['mounted', 'created', 'beforeDestroy', 'updated', 'onMounted', 'onUnmounted'],
            'angular' => ['ngOnInit', 'ngOnDestroy', 'ngOnChanges', 'ngAfterViewInit'],
        ];

        $patterns = $lifecycle_patterns[$app_type] ?? [];

        if (empty($patterns)) {
            return $hooks;
        }

        $files = $this->find_files_by_pattern($path, '*.{js,jsx,ts,tsx,vue}');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            foreach ($patterns as $hook) {
                if (preg_match_all('/\b' . preg_quote($hook, '/') . '\b/', $content, $matches)) {
                    $hooks[] = [
                        'file' => str_replace($path, '', $file),
                        'hook' => $hook,
                        'count' => count($matches[0]),
                    ];
                }
            }
        }

        return $hooks;
    }

    /**
     * Extract API endpoints
     *
     * @param string $path Application path
     * @param string $app_type Application type
     * @return array API endpoints
     */
    private function extract_api_endpoints( $path, $app_type ) {
        $endpoints = [];

        // Route definition patterns
        $route_patterns = [
            'laravel' => '/Route::(get|post|put|delete|patch)\s*\([\'"]([^\'"]+)[\'"]/',
            'symfony' => '/@Route\([\'"]([^\'"]+)[\'"].*methods=\{[\'"](\w+)[\'"]\}/',
            'express' => '/app\.(get|post|put|delete|patch)\s*\([\'"]([^\'"]+)[\'"]/',
            'rails' => '/(get|post|put|delete|patch)\s+[\'"]([^\'"]+)[\'"]/',
        ];

        $pattern = $route_patterns[$app_type] ?? $route_patterns['express'];

        $files = $this->find_files_by_pattern($path, '*.{php,js,ts,rb,py}');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[2] as $i => $route) {
                    $endpoints[] = [
                        'file' => str_replace($path, '', $file),
                        'method' => strtoupper($matches[1][$i]),
                        'path' => $route,
                    ];
                }
            }
        }

        return $endpoints;
    }

    /**
     * Extract database schema
     *
     * @param string $path Application path
     * @return array Database schema
     */
    private function extract_database_schema( $path ) {
        $schema = [
            'tables' => [],
            'migrations' => [],
            'relationships' => [],
        ];

        // Find migration files
        $migration_patterns = ['migrations/*.php', 'migrations/*.sql', 'db/migrate/*.rb'];

        foreach ($migration_patterns as $pattern) {
            $files = $this->find_files_by_pattern($path, $pattern);

            foreach ($files as $file) {
                $content = file_get_contents($file);

                // Extract table creations
                if (preg_match_all('/create_table\s+[\'"](\w+)[\'"]|CREATE\s+TABLE\s+(\w+)/', $content, $matches)) {
                    $table_name = $matches[1][0] ?? $matches[2][0] ?? null;

                    if ($table_name) {
                        $schema['tables'][] = [
                            'name' => $table_name,
                            'migration' => str_replace($path, '', $file),
                            'columns' => $this->extract_table_columns($content),
                        ];
                    }
                }

                $schema['migrations'][] = str_replace($path, '', $file);
            }
        }

        return $schema;
    }

    /**
     * Catalog application assets
     *
     * @param string $path Application path
     * @return array Asset catalog
     */
    private function catalog_assets( $path ) {
        $assets = [
            'images' => [],
            'stylesheets' => [],
            'scripts' => [],
            'fonts' => [],
            'videos' => [],
            'documents' => [],
        ];

        // Asset patterns
        $patterns = [
            'images' => '*.{jpg,jpeg,png,gif,svg,webp,ico}',
            'stylesheets' => '*.{css,scss,sass,less}',
            'scripts' => '*.{js,jsx,ts,tsx}',
            'fonts' => '*.{woff,woff2,ttf,eot,otf}',
            'videos' => '*.{mp4,webm,ogg,avi}',
            'documents' => '*.{pdf,doc,docx,xls,xlsx}',
        ];

        foreach ($patterns as $type => $pattern) {
            $files = $this->find_files_by_pattern($path, $pattern);

            foreach ($files as $file) {
                $assets[$type][] = [
                    'file' => str_replace($path, '', $file),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                ];
            }
        }

        return $assets;
    }

    /**
     * Perform security scan
     *
     * @param string $path Application path
     * @return array Security issues
     */
    private function perform_security_scan( $path ) {
        $issues = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
        ];

        $files = $this->find_files_by_pattern($path, '*.{js,jsx,ts,tsx,php,py,rb}');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative_file = str_replace($path, '', $file);

            // Check for hardcoded secrets
            if (preg_match('/(api[_-]?key|secret|password|token)\s*[=:]\s*[\'"][\w\-]{20,}[\'"]/', $content, $matches)) {
                $issues['critical'][] = [
                    'file' => $relative_file,
                    'issue' => 'Hardcoded secret detected',
                    'match' => $matches[0],
                ];
            }

            // Check for SQL injection vulnerabilities
            if (preg_match('/(\$_GET|\$_POST|\$_REQUEST).*query|execute|prepare/', $content)) {
                $issues['high'][] = [
                    'file' => $relative_file,
                    'issue' => 'Potential SQL injection vulnerability',
                ];
            }

            // Check for XSS vulnerabilities
            if (preg_match('/innerHTML\s*=|dangerouslySetInnerHTML|eval\(/', $content)) {
                $issues['high'][] = [
                    'file' => $relative_file,
                    'issue' => 'Potential XSS vulnerability',
                ];
            }

            // Check for insecure dependencies
            if (preg_match('/(eval|exec|system|shell_exec)\s*\(/', $content)) {
                $issues['medium'][] = [
                    'file' => $relative_file,
                    'issue' => 'Use of potentially dangerous function',
                ];
            }

            // Check for console.log in production
            if (preg_match('/console\.(log|debug|info)/', $content)) {
                $issues['low'][] = [
                    'file' => $relative_file,
                    'issue' => 'Console logging in production code',
                ];
            }
        }

        return $issues;
    }

    /**
     * Detect architecture pattern
     *
     * @param array $components Application components
     * @return string Architecture pattern
     */
    private function detect_architecture_pattern( $components ) {
        $has_models = !empty($components['models']);
        $has_views = !empty($components['views']);
        $has_controllers = !empty($components['controllers']);

        // MVC pattern
        if ($has_models && $has_views && $has_controllers) {
            return 'mvc';
        }

        // MVVM pattern (common in Vue, Angular)
        if ($has_models && $has_views && isset($components['state'])) {
            return 'mvvm';
        }

        // Component-based (React, Vue 3)
        if ($has_views && !$has_controllers) {
            return 'component-based';
        }

        // Microservices
        if (isset($components['api_endpoints']) && count($components['api_endpoints']) > 10) {
            return 'microservices';
        }

        // Monolithic
        if (isset($components['file_count']) && $components['file_count'] < 50) {
            return 'monolithic';
        }

        return 'unknown';
    }

    /**
     * Identify application layers
     *
     * @param array $components Application components
     * @return array Identified layers
     */
    private function identify_layers( $components ) {
        $layers = [];

        // Presentation layer
        if (!empty($components['views'])) {
            $layers['presentation'] = [
                'components' => count($components['views']),
                'files' => array_column($components['views'], 'file'),
            ];
        }

        // Business logic layer
        if (!empty($components['controllers'])) {
            $layers['business_logic'] = [
                'controllers' => count($components['controllers']),
                'files' => array_column($components['controllers'], 'file'),
            ];
        }

        // Data layer
        if (!empty($components['models'])) {
            $layers['data'] = [
                'models' => count($components['models']),
                'files' => array_column($components['models'], 'file'),
            ];
        }

        // API layer
        if (!empty($components['api_endpoints'])) {
            $layers['api'] = [
                'endpoints' => count($components['api_endpoints']),
                'files' => array_column($components['api_endpoints'], 'file'),
            ];
        }

        return $layers;
    }

    /**
     * Calculate application complexity
     *
     * @param array $components Application components
     * @return int Complexity score (0-100)
     */
    private function calculate_complexity( $components ) {
        $score = 0;

        // File count contribution (max 20 points)
        $file_count = $components['file_count'] ?? 0;
        $score += min(20, ($file_count / 50) * 20);

        // Component count contribution (max 20 points)
        $component_count = count($components['views'] ?? []) +
                          count($components['controllers'] ?? []) +
                          count($components['models'] ?? []);
        $score += min(20, ($component_count / 20) * 20);

        // API endpoints contribution (max 15 points)
        $endpoint_count = count($components['api_endpoints'] ?? []);
        $score += min(15, ($endpoint_count / 10) * 15);

        // Dependencies contribution (max 15 points)
        $dep_count = isset($components['config']['package.json']) ?
                    count($components['config']['package.json']['data']['dependencies'] ?? []) : 0;
        $score += min(15, ($dep_count / 20) * 15);

        // State management contribution (max 10 points)
        if (isset($components['state']) && $components['state']['type'] !== 'none') {
            $score += 10;
        }

        // Network calls contribution (max 10 points)
        $api_calls = count($components['network']['api_calls'] ?? []);
        $score += min(10, ($api_calls / 10) * 10);

        // Security issues contribution (max 10 points)
        $critical_issues = count($components['security']['critical'] ?? []);
        $score += min(10, $critical_issues * 2);

        return (int)min(100, $score);
    }

    /**
     * Extract methods from file
     *
     * @param string $file File path
     * @return array Methods found
     */
    private function extract_methods_from_file( $file ) {
        $methods = [];
        $content = file_get_contents($file);

        // JavaScript/TypeScript functions
        if (preg_match_all('/(function\s+(\w+)|(\w+)\s*:\s*function|const\s+(\w+)\s*=\s*\(|(\w+)\s*\(.*\)\s*=>)/', $content, $matches)) {
            foreach ($matches[0] as $i => $match) {
                $name = $matches[2][$i] ?: $matches[3][$i] ?: $matches[4][$i] ?: $matches[5][$i];
                if ($name) {
                    $methods[] = [
                        'name' => $name,
                        'type' => 'function',
                    ];
                }
            }
        }

        // PHP methods
        if (preg_match_all('/(public|private|protected)\s+function\s+(\w+)/', $content, $matches)) {
            foreach ($matches[2] as $i => $name) {
                $methods[] = [
                    'name' => $name,
                    'visibility' => $matches[1][$i],
                    'type' => 'method',
                ];
            }
        }

        // Class methods
        if (preg_match_all('/(\w+)\s*\([^)]*\)\s*\{/', $content, $matches)) {
            // Filter out keywords
            $keywords = ['if', 'for', 'while', 'switch', 'catch', 'function'];
            foreach ($matches[1] as $name) {
                if (!in_array(strtolower($name), $keywords) && !isset($seen[$name])) {
                    $methods[] = ['name' => $name, 'type' => 'method'];
                    $seen[$name] = true;
                }
            }
        }

        return array_unique($methods, SORT_REGULAR);
    }

    /**
     * Find files by glob pattern
     *
     * @param string $path Base path
     * @param string $pattern Glob pattern
     * @return array File paths
     */
    private function find_files_by_pattern( $path, $pattern ) {
        $files = [];

        // Handle brace expansion
        if (strpos($pattern, '{') !== false) {
            $patterns = [];
            preg_match('/\{([^}]+)\}/', $pattern, $matches);
            if (isset($matches[1])) {
                $extensions = explode(',', $matches[1]);
                $base = str_replace($matches[0], '', $pattern);
                foreach ($extensions as $ext) {
                    $patterns[] = $base . $ext;
                }
            }
        } else {
            $patterns = [$pattern];
        }

        foreach ($patterns as $p) {
            // Recursive search for **
            if (strpos($p, '**') !== false) {
                $p = str_replace('**/', '', $p);
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && fnmatch($p, $file->getFilename())) {
                        $files[] = $file->getPathname();
                    }
                }
            } else {
                // Simple glob
                $result = glob($path . '/' . $p, GLOB_BRACE);
                if ($result) {
                    $files = array_merge($files, $result);
                }
            }
        }

        return array_unique($files);
    }

    /**
     * Count files in directory
     *
     * @param string $path Directory path
     * @return int File count
     */
    private function count_files( $path ) {
        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Calculate directory size
     *
     * @param string $path Directory path
     * @return int Size in bytes
     */
    private function calculate_size( $path ) {
        if (!is_dir($path)) {
            return file_exists($path) ? filesize($path) : 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Helper methods for component extraction
     */
    private function get_view_patterns($app_type) {
        $patterns = [
            'react' => [['pattern' => '*.jsx', 'type' => 'jsx'], ['pattern' => '*.tsx', 'type' => 'tsx']],
            'vue' => [['pattern' => '*.vue', 'type' => 'vue']],
            'angular' => [['pattern' => '*.component.html', 'type' => 'html']],
            'laravel' => [['pattern' => 'resources/views/*.blade.php', 'type' => 'blade']],
        ];
        return $patterns[$app_type] ?? [['pattern' => '*.html', 'type' => 'html']];
    }

    private function extract_template_variables($content, $type) {
        $vars = [];
        if ($type === 'jsx' || $type === 'tsx') {
            preg_match_all('/\{([^}]+)\}/', $content, $matches);
            $vars = $matches[1] ?? [];
        } elseif ($type === 'vue') {
            preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
            $vars = $matches[1] ?? [];
        }
        return array_unique($vars);
    }

    private function extract_template_components($content, $type) {
        $components = [];
        preg_match_all('/<([A-Z][A-Za-z0-9]*)/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    private function extract_class_name($content) {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        if (preg_match('/export\s+(default\s+)?class\s+(\w+)/', $content, $matches)) {
            return $matches[2];
        }
        return 'Anonymous';
    }

    private function extract_route_mappings($content, $app_type) {
        $routes = [];

        // Laravel route annotations
        if ($app_type === 'laravel') {
            // Extract @Route annotations
            if (preg_match_all('/@Route\([\'"]([^\'"]+)[\'"].*\[([^\]]+)\]/', $content, $matches)) {
                foreach ($matches[1] as $i => $route) {
                    $methods = explode(',', str_replace(['[', ']', '"', "'"], '', $matches[2][$i]));
                    foreach ($methods as $method) {
                        $routes[] = [
                            'path' => $route,
                            'method' => strtoupper(trim($method))
                        ];
                    }
                }
            }

            // Extract Route::get/post/etc patterns
            if (preg_match_all('/Route::(get|post|put|patch|delete)\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $i => $method) {
                    $routes[] = [
                        'path' => $matches[2][$i],
                        'method' => strtoupper($method)
                    ];
                }
            }
        }

        // Symfony route annotations
        if ($app_type === 'symfony') {
            if (preg_match_all('/@Route\([\'"]([^\'"]+)[\'"].*methods=\{[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $i => $route) {
                    $routes[] = [
                        'path' => $route,
                        'method' => strtoupper($matches[2][$i])
                    ];
                }
            }
        }

        // Express.js route definitions
        if ($app_type === 'node-api' || strpos($content, 'express') !== false) {
            if (preg_match_all('/(?:router|app)\.(get|post|put|patch|delete)\s*\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $i => $method) {
                    $routes[] = [
                        'path' => $matches[2][$i],
                        'method' => strtoupper($method)
                    ];
                }
            }
        }

        // React Router route components
        if ($app_type === 'react' || strpos($content, 'Route') !== false) {
            if (preg_match_all('/<Route\s+path=[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $path) {
                    $routes[] = [
                        'path' => $path,
                        'method' => 'GET',
                        'type' => 'client-side'
                    ];
                }
            }
        }

        // Vue Router configurations
        if ($app_type === 'vue' || strpos($content, 'VueRouter') !== false) {
            if (preg_match_all('/path:\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $path) {
                    $routes[] = [
                        'path' => $path,
                        'method' => 'GET',
                        'type' => 'client-side'
                    ];
                }
            }
        }

        // Angular route configurations
        if ($app_type === 'angular' || strpos($content, '@angular') !== false) {
            if (preg_match_all('/path:\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $path) {
                    $routes[] = [
                        'path' => $path,
                        'method' => 'GET',
                        'type' => 'client-side'
                    ];
                }
            }
        }

        return array_unique($routes, SORT_REGULAR);
    }

    private function extract_dependencies($content) {
        $deps = [];
        preg_match_all('/import\s+.*\s+from\s+[\'"]([^\'"]+)[\'"]/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    private function extract_properties($content) {
        $props = [];
        preg_match_all('/(this\.)?(\w+)\s*[:=]/', $content, $matches);
        return array_unique($matches[2] ?? []);
    }

    private function extract_relationships($content, $app_type) {
        $relationships = [];

        // Laravel Eloquent relationships
        if ($app_type === 'laravel' || $app_type === 'php') {
            $eloquent_patterns = [
                'hasOne' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->hasOne\(([^)]+)\)/',
                'hasMany' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->hasMany\(([^)]+)\)/',
                'belongsTo' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->belongsTo\(([^)]+)\)/',
                'belongsToMany' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->belongsToMany\(([^)]+)\)/',
                'hasOneThrough' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->hasOneThrough\(([^)]+)\)/',
                'hasManyThrough' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->hasManyThrough\(([^)]+)\)/',
                'morphTo' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->morphTo\(/',
                'morphOne' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->morphOne\(([^)]+)\)/',
                'morphMany' => '/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[^}]*return\s+\$this->morphMany\(([^)]+)\)/',
            ];

            foreach ($eloquent_patterns as $rel_type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $i => $method_name) {
                        $related_model = isset($matches[2][$i]) ? trim($matches[2][$i], '\'"') : 'unknown';
                        $relationships[] = [
                            'type' => $rel_type,
                            'method' => $method_name,
                            'related_model' => $related_model,
                            'framework' => 'laravel'
                        ];
                    }
                }
            }
        }

        // Symfony Doctrine relationships
        if ($app_type === 'symfony' || strpos($content, '@ORM') !== false) {
            $doctrine_patterns = [
                'OneToOne' => '/@ORM\\\\OneToOne\(targetEntity="([^"]+)"/',
                'OneToMany' => '/@ORM\\\\OneToMany\(targetEntity="([^"]+)"/',
                'ManyToOne' => '/@ORM\\\\ManyToOne\(targetEntity="([^"]+)"/',
                'ManyToMany' => '/@ORM\\\\ManyToMany\(targetEntity="([^"]+)"/',
            ];

            foreach ($doctrine_patterns as $rel_type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $related_entity) {
                        // Find the property name associated with this annotation
                        if (preg_match('/private\s+\$(\w+);[\s\S]*?' . preg_quote($pattern, '/') . '/', $content, $prop_match)) {
                            $relationships[] = [
                                'type' => $rel_type,
                                'property' => $prop_match[1] ?? 'unknown',
                                'related_model' => $related_entity,
                                'framework' => 'symfony'
                            ];
                        }
                    }
                }
            }
        }

        // TypeORM relationships (TypeScript/Node.js)
        if ($app_type === 'node-api' || strpos($content, 'typeorm') !== false) {
            $typeorm_patterns = [
                'OneToOne' => '/@OneToOne\(\s*\(\)\s*=>\s*(\w+)/',
                'OneToMany' => '/@OneToMany\(\s*\(\)\s*=>\s*(\w+)/',
                'ManyToOne' => '/@ManyToOne\(\s*\(\)\s*=>\s*(\w+)/',
                'ManyToMany' => '/@ManyToMany\(\s*\(\)\s*=>\s*(\w+)/',
            ];

            foreach ($typeorm_patterns as $rel_type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $related_entity) {
                        $relationships[] = [
                            'type' => $rel_type,
                            'related_model' => $related_entity,
                            'framework' => 'typeorm'
                        ];
                    }
                }
            }
        }

        // Sequelize relationships (Node.js)
        if (strpos($content, 'sequelize') !== false) {
            $sequelize_patterns = [
                'hasOne' => '/\.hasOne\((\w+)/',
                'hasMany' => '/\.hasMany\((\w+)/',
                'belongsTo' => '/\.belongsTo\((\w+)/',
                'belongsToMany' => '/\.belongsToMany\((\w+)/',
            ];

            foreach ($sequelize_patterns as $rel_type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $related_model) {
                        $relationships[] = [
                            'type' => $rel_type,
                            'related_model' => $related_model,
                            'framework' => 'sequelize'
                        ];
                    }
                }
            }
        }

        // Mongoose relationships (Node.js/MongoDB)
        if (strpos($content, 'mongoose') !== false) {
            // Extract ref in schema definitions
            if (preg_match_all('/(\w+):\s*\{[^}]*type:\s*Schema\.Types\.ObjectId[^}]*ref:\s*[\'"](\w+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $i => $field) {
                    $relationships[] = [
                        'type' => 'reference',
                        'field' => $field,
                        'related_model' => $matches[2][$i],
                        'framework' => 'mongoose'
                    ];
                }
            }
        }

        // Django ORM relationships (Python)
        if ($app_type === 'python' || strpos($content, 'django.db') !== false) {
            $django_patterns = [
                'ForeignKey' => '/(\w+)\s*=\s*models\.ForeignKey\([\'"]?(\w+)[\'"]?/',
                'OneToOneField' => '/(\w+)\s*=\s*models\.OneToOneField\([\'"]?(\w+)[\'"]?/',
                'ManyToManyField' => '/(\w+)\s*=\s*models\.ManyToManyField\([\'"]?(\w+)[\'"]?/',
            ];

            foreach ($django_patterns as $rel_type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $i => $field) {
                        $relationships[] = [
                            'type' => $rel_type,
                            'field' => $field,
                            'related_model' => $matches[2][$i],
                            'framework' => 'django'
                        ];
                    }
                }
            }
        }

        return $relationships;
    }

    private function extract_validations($content, $app_type) {
        $validations = [];

        // Laravel validation rules
        if ($app_type === 'laravel' || $app_type === 'php') {
            // Extract validation rules from validate() calls
            if (preg_match_all('/\$this->validate\([^,]+,\s*\[([\s\S]*?)\]\s*\)/m', $content, $matches)) {
                foreach ($matches[1] as $rules_block) {
                    if (preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $rules_block, $rule_matches)) {
                        foreach ($rule_matches[1] as $i => $field) {
                            $validations[] = [
                                'field' => $field,
                                'rules' => $rule_matches[2][$i],
                                'framework' => 'laravel'
                            ];
                        }
                    }
                }
            }

            // Extract validation from Validator::make()
            if (preg_match_all('/Validator::make\([^,]+,\s*\[([\s\S]*?)\]\s*\)/m', $content, $matches)) {
                foreach ($matches[1] as $rules_block) {
                    if (preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $rules_block, $rule_matches)) {
                        foreach ($rule_matches[1] as $i => $field) {
                            $validations[] = [
                                'field' => $field,
                                'rules' => $rule_matches[2][$i],
                                'framework' => 'laravel'
                            ];
                        }
                    }
                }
            }

            // Extract Request class validation rules
            if (preg_match_all('/public\s+function\s+rules\s*\(\s*\)\s*\{[\s\S]*?return\s*\[([\s\S]*?)\];/m', $content, $matches)) {
                foreach ($matches[1] as $rules_block) {
                    if (preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $rules_block, $rule_matches)) {
                        foreach ($rule_matches[1] as $i => $field) {
                            $validations[] = [
                                'field' => $field,
                                'rules' => $rule_matches[2][$i],
                                'framework' => 'laravel'
                            ];
                        }
                    }
                }
            }
        }

        // Symfony validation annotations
        if ($app_type === 'symfony' || strpos($content, '@Assert') !== false) {
            $assert_patterns = [
                'NotBlank' => '/@Assert\\\\NotBlank/',
                'NotNull' => '/@Assert\\\\NotNull/',
                'Email' => '/@Assert\\\\Email/',
                'Length' => '/@Assert\\\\Length\(min\s*=\s*(\d+),\s*max\s*=\s*(\d+)\)/',
                'Range' => '/@Assert\\\\Range\(min\s*=\s*(\d+),\s*max\s*=\s*(\d+)\)/',
                'Url' => '/@Assert\\\\Url/',
                'Regex' => '/@Assert\\\\Regex\(pattern\s*=\s*"([^"]+)"\)/',
                'Choice' => '/@Assert\\\\Choice\(choices\s*=\s*\{([^}]+)\}\)/',
            ];

            foreach ($assert_patterns as $type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    // Find associated property
                    $pos = strpos($content, $matches[0][0]);
                    $before = substr($content, 0, $pos);
                    if (preg_match('/private\s+\$(\w+);[\s]*$/', $before, $prop_match)) {
                        $validations[] = [
                            'field' => $prop_match[1],
                            'type' => $type,
                            'framework' => 'symfony'
                        ];
                    }
                }
            }
        }

        // Express-validator (Node.js)
        if ($app_type === 'node-api' || strpos($content, 'express-validator') !== false) {
            $validator_patterns = [
                'isEmail' => '/(\w+)\.isEmail\(\)/',
                'notEmpty' => '/(\w+)\.notEmpty\(\)/',
                'isLength' => '/(\w+)\.isLength\(\{[^}]*min:\s*(\d+)[^}]*max:\s*(\d+)/',
                'isInt' => '/(\w+)\.isInt\(\)/',
                'isFloat' => '/(\w+)\.isFloat\(\)/',
                'isURL' => '/(\w+)\.isURL\(\)/',
                'matches' => '/(\w+)\.matches\([\'"]([^\'"]+)[\'"]/',
            ];

            foreach ($validator_patterns as $type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $field) {
                        $validations[] = [
                            'field' => $field,
                            'type' => $type,
                            'framework' => 'express-validator'
                        ];
                    }
                }
            }
        }

        // Joi validation (Node.js)
        if (strpos($content, 'Joi') !== false) {
            // Extract Joi schema definitions
            if (preg_match_all('/(\w+):\s*Joi\.(string|number|boolean|date|array|object)\(\)([^,\n}]*)/m', $content, $matches)) {
                foreach ($matches[1] as $i => $field) {
                    $type = $matches[2][$i];
                    $constraints = $matches[3][$i];

                    $validations[] = [
                        'field' => $field,
                        'type' => $type,
                        'constraints' => trim($constraints),
                        'framework' => 'joi'
                    ];
                }
            }
        }

        // Django validators (Python)
        if ($app_type === 'python' || strpos($content, 'django') !== false) {
            $django_validators = [
                'MaxLengthValidator' => '/(\w+)\s*=.*MaxLengthValidator\((\d+)\)/',
                'MinLengthValidator' => '/(\w+)\s*=.*MinLengthValidator\((\d+)\)/',
                'EmailValidator' => '/(\w+)\s*=.*EmailValidator\(\)/',
                'URLValidator' => '/(\w+)\s*=.*URLValidator\(\)/',
                'RegexValidator' => '/(\w+)\s*=.*RegexValidator\([\'"]([^\'"]+)[\'"]/',
            ];

            foreach ($django_validators as $type => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $i => $field) {
                        $validations[] = [
                            'field' => $field,
                            'type' => $type,
                            'framework' => 'django'
                        ];
                    }
                }
            }

            // Django model field constraints
            $field_constraints = [
                'max_length' => '/(\w+)\s*=.*max_length\s*=\s*(\d+)/',
                'min_length' => '/(\w+)\s*=.*min_length\s*=\s*(\d+)/',
                'null' => '/(\w+)\s*=.*null\s*=\s*(True|False)/',
                'blank' => '/(\w+)\s*=.*blank\s*=\s*(True|False)/',
                'unique' => '/(\w+)\s*=.*unique\s*=\s*True/',
            ];

            foreach ($field_constraints as $constraint => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $i => $field) {
                        $value = $matches[2][$i] ?? 'true';
                        $validations[] = [
                            'field' => $field,
                            'constraint' => $constraint,
                            'value' => $value,
                            'framework' => 'django'
                        ];
                    }
                }
            }
        }

        // React/Formik validation (JavaScript/TypeScript)
        if ($app_type === 'react' || strpos($content, 'Yup') !== false) {
            // Yup validation schema
            if (preg_match_all('/(\w+):\s*Yup\.(string|number|boolean|date|array|object)\(\)([^,\n}]*)/m', $content, $matches)) {
                foreach ($matches[1] as $i => $field) {
                    $type = $matches[2][$i];
                    $constraints = $matches[3][$i];

                    $validations[] = [
                        'field' => $field,
                        'type' => $type,
                        'constraints' => trim($constraints),
                        'framework' => 'yup'
                    ];
                }
            }
        }

        // HTML5 validation attributes
        if (preg_match_all('/<input[^>]*name=[\'"](\w+)[\'"][^>]*(required|pattern|min|max|minlength|maxlength)=[\'"]([^\'"]*)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $i => $field) {
                $validations[] = [
                    'field' => $field,
                    'attribute' => $matches[2][$i],
                    'value' => $matches[3][$i],
                    'framework' => 'html5'
                ];
            }
        }

        return $validations;
    }

    private function detect_config_type($filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return $ext ?: 'unknown';
    }

    private function parse_config_file($content, $filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        try {
            if ($ext === 'json') {
                return json_decode($content, true);
            } elseif (in_array($ext, ['yml', 'yaml'])) {
                // Simple YAML parsing (or use symfony/yaml)
                return $this->simple_yaml_parse($content);
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        return ['raw' => substr($content, 0, 500)];
    }

    private function simple_yaml_parse($content) {
        // Simplified YAML parser for basic key: value pairs
        $data = [];
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $matches)) {
                $data[$matches[1]] = trim($matches[2]);
            }
        }
        return $data;
    }

    private function find_entry_points($path) {
        // Common entry point files
        $entry_files = ['index.js', 'index.ts', 'main.js', 'main.ts', 'app.js', 'app.ts', 'index.php', 'app.php'];
        $found = [];

        foreach ($entry_files as $file) {
            $full_path = $path . '/' . $file;
            if (file_exists($full_path)) {
                $found[] = $full_path;
            }
        }

        return $found;
    }

    private function extract_imports($content) {
        $imports = [];
        preg_match_all('/import\s+.*\s+from\s+[\'"]([^\'"]+)[\'"]/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    private function extract_function_calls($content) {
        $calls = [];
        preg_match_all('/(\w+)\s*\(/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    private function extract_async_operations($content) {
        $async = [];
        if (preg_match_all('/(async\s+function|await\s+|\.then\(|Promise)/', $content, $matches)) {
            $async = $matches[0];
        }
        return $async;
    }

    private function extract_event_listeners($content) {
        $listeners = [];
        preg_match_all('/addEventListener\([\'"](\w+)[\'"]/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    private function extract_table_columns($content) {
        $columns = [];
        // Extract column definitions from migrations
        if (preg_match_all('/t\.(\w+)\([\'"](\w+)[\'"]/', $content, $matches)) {
            foreach ($matches[2] as $i => $col) {
                $columns[] = ['name' => $col, 'type' => $matches[1][$i]];
            }
        }
        return $columns;
    }

    /**
     * Clean up temporary files
     */
    public function cleanup_temp_files() {
        if (is_dir($this->temp_dir)) {
            // Clean files older than 24 hours
            $files = glob($this->temp_dir . '*');
            $now = time();

            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) >= 86400) {
                    unlink($file);
                }
            }
        }
    }
}
