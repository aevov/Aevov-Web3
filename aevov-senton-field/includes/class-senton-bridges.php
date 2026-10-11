<?php
/**
 * Guarded bridges to the three core plugins.
 *
 * Every call site is behind an existence check, because the field must stay
 * experienceable on a site that has not activated the mesh or configured a
 * provider. Availability is reported to the UI rather than assumed.
 *
 * Aevov Senton Field
 *
 * Licensed under the Aevov Sovereign Source-Available License (SSAL) v1.0.
 * See the LICENSE file in this plugin directory for the full terms.
 *
 * ADHERENCE — reading, running or copying this file binds you to SSAL v1.0.
 *   Permitted (SSAL §2): view and audit this source to verify integrity; reproduce and
 *            use it solely for internal, non-commercial development inside an authorised
 *            AevOS mesh environment.
 *   Prohibited (SSAL §3) without explicit written consent from the Aevov Organization:
 *            redistribution, sublicensing, sale, or any derivative work; porting,
 *            migrating or adapting this to any non-AevOS kernel or environment; use in
 *            any commercial application or service; production use outside the Aevov
 *            Sovereign Mesh.
 *   Retention: this header, the LICENSE file and BOUNDARY.md must accompany every copy.
 * Rights terminate automatically on breach (SSAL §6). Governing law (SSAL §7).
 *
 * This plugin is SSAL while the surrounding Aevov-Web3 tree is AGPL-3.0. Moving these
 * files into an AGPL context is itself a §3(a) redistribution — do not do it.
 *
 * @package AevovSentonField
 */

namespace AevovSentonField;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Senton_Bridges {

    /** @var string Mesh DHT key prefix for published field signatures. */
    const PEER_PREFIX = 'senton:field:';

    public function availability() {
        return [
            'core'    => function_exists( 'aevov_get_api_key' ) || class_exists( 'AevovCore' ),
            'ai'      => class_exists( 'Aevov\AICore\AICore' ) || isset( $GLOBALS['aevov_ai_core'] ),
            'mesh'    => class_exists( 'Aevov\Meshcore\MeshcorePlugin' ),
            'memory'  => class_exists( 'AevovMemoryCore\MemoryManager' ),
            'network' => did_action( 'aevov_meshcore_loaded' ) > 0,
        ];
    }

    /**
     * aevov-ai-core: ask the configured provider how much this event matters.
     *
     * Returns null when no provider is wired, which is the normal case — the caller
     * then falls back to the deterministic salience in Senton_Organism::salience_of().
     *
     * @param string $input Perceived text.
     * @return float|null Salience in [0,1], or null if unavailable.
     */
    public function ai_salience( $input ) {
        if ( ! $this->availability()['ai'] ) {
            return null;
        }

        $manager = $this->provider_manager();
        if ( ! $manager || ! method_exists( $manager, 'complete' ) ) {
            return null;
        }

        $prompt = 'Score how much this statement should matter to a coherent field, '
            . 'as a decimal between 0.0 and 1.0. Reply with the number only.' . "\n\n" . $input;

        try {
            $response = $manager->complete( null, [ 'prompt' => $prompt, 'max_tokens' => 8, 'temperature' => 0.0 ] );
        } catch ( \Throwable $e ) {
            do_action( 'aevov_error', 'senton-field', $e->getMessage() );
            return null;
        }

        $text = $this->extract_text( $response );
        if ( '' === $text || ! preg_match( '/\d*\.?\d+/', $text, $matches ) ) {
            return null;
        }

        $score = (float) $matches[0];
        return max( 0.0, min( 1.0, $score > 1.0 ? $score / 100.0 : $score ) );
    }

    /**
     * aevov-meshcore: publish only what a peer needs in order to couple — mean phase,
     * coherence, reputation, signature. Never the lattice itself.
     *
     * @param array $announcement Field summary plus psi.
     * @return bool Whether the mesh accepted it.
     */
    public function mesh_publish( array $announcement ) {
        $dht = $this->dht_service();

        if ( $dht && method_exists( $dht, 'put' ) ) {
            $key = self::PEER_PREFIX . $this->node_id();
            return (bool) $dht->put( $key, $announcement, 900 );
        }

        // Degrade to a local peer board so multi-site demos still see exchange.
        $peers              = (array) get_option( 'aevov_senton_field_peer_board', [] );
        $peers[ $this->node_id() ] = $announcement;
        update_option( 'aevov_senton_field_peer_board', $peers, false );

        do_action( 'aevov_senton_field_published', $announcement );
        return false;
    }

    /**
     * Peer announcements from the mesh, minus this node.
     *
     * @return array
     */
    public function mesh_peers() {
        $mine  = $this->node_id();
        $peers = [];

        $dht = $this->dht_service();
        if ( $dht && method_exists( $dht, 'get' ) ) {
            foreach ( $this->peer_key_candidates( $dht ) as $candidate ) {
                $found = $dht->get( self::PEER_PREFIX . $candidate );
                if ( is_array( $found ) && $candidate !== $mine ) {
                    $peers[ $candidate ] = $found;
                }
            }
        }

        if ( empty( $peers ) ) {
            foreach ( (array) get_option( 'aevov_senton_field_peer_board', [] ) as $node_id => $announcement ) {
                if ( $node_id !== $mine && is_array( $announcement ) ) {
                    $peers[ $node_id ] = $announcement;
                }
            }
        }

        return $peers;
    }

    /**
     * aevov-memory-core: the field writes its own trace as an episodic memory, so the
     * organism's memory plugin gets real use without the field depending on it.
     *
     * @param array $event Summary of a step or perception.
     */
    public function memory_record( array $event ) {
        if ( ! class_exists( 'AevovMemoryCore\MemoryManager' ) ) {
            return;
        }

        try {
            $manager = new \AevovMemoryCore\MemoryManager();
            if ( method_exists( $manager, 'write_to_memory' ) ) {
                $manager->write_to_memory( 'senton:field:' . (int) $event['tick'], $event );
            }
        } catch ( \Throwable $e ) {
            do_action( 'aevov_error', 'senton-field', $e->getMessage() );
        }
    }

    /**
     * @return string Pseudonymous node identifier, or a local stand-in.
     */
    public function node_id() {
        $mesh = $this->meshcore();

        if ( $mesh && method_exists( $mesh, 'get_node_manager' ) ) {
            $nodes = $mesh->get_node_manager();
            if ( $nodes && method_exists( $nodes, 'get_node_id' ) ) {
                return (string) $nodes->get_node_id();
            }
        }

        $id = get_option( 'aevov_senton_field_node' );
        if ( ! $id ) {
            $id = substr( hash( 'sha256', home_url() . wp_rand() ), 0, 16 );
            update_option( 'aevov_senton_field_node', $id, false );
        }

        return (string) $id;
    }

    private function provider_manager() {
        if ( isset( $GLOBALS['aevov_ai_core'] ) && is_object( $GLOBALS['aevov_ai_core'] ) ) {
            $ai_core = $GLOBALS['aevov_ai_core'];
        } elseif ( class_exists( 'Aevov\AICore\AICore' ) ) {
            $ai_core = \Aevov\AICore\AICore::get_instance();
        } else {
            return null;
        }

        if ( $ai_core && method_exists( $ai_core, 'get_provider_manager' ) ) {
            return $ai_core->get_provider_manager();
        }

        return null;
    }

    private function meshcore() {
        if ( ! class_exists( 'Aevov\Meshcore\MeshcorePlugin' ) ) {
            return null;
        }

        return \Aevov\Meshcore\MeshcorePlugin::get_instance();
    }

    private function dht_service() {
        $mesh = $this->meshcore();

        if ( $mesh && method_exists( $mesh, 'get_dht_service' ) ) {
            return $mesh->get_dht_service();
        }

        return null;
    }

    /**
     * The DHT exposes find_node/find_closest_nodes rather than a scan, so the field
     * asks for the peers it already knows about and lets discovery fill in the rest.
     *
     * @param object $dht
     * @return string[]
     */
    private function peer_key_candidates( $dht ) {
        $known = array_keys( (array) get_option( 'aevov_senton_field_known_peers', [] ) );

        if ( method_exists( $dht, 'find_closest_nodes' ) ) {
            try {
                foreach ( (array) $dht->find_closest_nodes( $this->node_id(), 12 ) as $node ) {
                    $candidate = is_array( $node ) && isset( $node['node_id'] ) ? $node['node_id'] : $node;
                    if ( is_string( $candidate ) ) {
                        $known[] = $candidate;
                    }
                }
            } catch ( \Throwable $e ) {
                do_action( 'aevov_error', 'senton-field', $e->getMessage() );
            }
        }

        return array_slice( array_unique( array_filter( $known ) ), 0, 12 );
    }

    private function extract_text( $response ) {
        if ( is_string( $response ) ) {
            return $response;
        }

        if ( is_array( $response ) ) {
            foreach ( [ 'content', 'text', 'completion', 'output', 'message' ] as $key ) {
                if ( isset( $response[ $key ] ) && is_string( $response[ $key ] ) ) {
                    return $response[ $key ];
                }
            }
        }

        return '';
    }
}
