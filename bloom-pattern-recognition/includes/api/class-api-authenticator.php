<?php
/**
 * Handles API authentication and request verification
 */
class BLOOM_API_Authenticator {
    private $token_lifetime = 3600; // 1 hour
    
    public function verify_request($request) {
        $token = $request->get_header('X-BLOOM-Token');
        if (!$token) {
            return false;
        }

        return $this->validate_token($token);
    }

    public function generate_token($client_id) {
        $token_data = [
            'client_id' => $client_id,
            'created' => time(),
            'expires' => time() + $this->token_lifetime
        ];
        
        $token = $this->encode_token($token_data);
        $this->store_token($token, $token_data);
        
        return $token;
    }

    private function validate_token($token) {
        $stored = $this->get_stored_token($token);
        if (!$stored) {
            return false;
        }

        if (time() > $stored['expires']) {
            $this->invalidate_token($token);
            return false;
        }

        return true;
    }

    private function store_token($token, $data) {
        set_transient(
            'bloom_token_' . $token,
            $data,
            $this->token_lifetime
        );
    }

    private function get_stored_token($token) {
        return get_transient('bloom_token_' . $token);
    }
}