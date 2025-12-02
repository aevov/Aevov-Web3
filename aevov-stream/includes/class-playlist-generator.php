<?php

namespace AevovStream;

class PlaylistGenerator {

    public function generate( $streams ) {
        $master_playlist = "#EXTM3U\n";
        $master_playlist .= "#EXT-X-VERSION:3\n";

        foreach ( $streams as $stream ) {
            $master_playlist .= "#EXT-X-STREAM-INF:BANDWIDTH={$stream['bandwidth']},RESOLUTION={$stream['resolution']}\n";
            $master_playlist .= site_url( '/wp-json/aevov-stream/v1/playlist/' . $stream['session_id'] . '?variant=' . $stream['id'] ) . "\n";
        }

        return $master_playlist;
    }

    public function generate_variant_playlist( $pattern_ids ) {
        $playlist = "#EXTM3U\n";
        $playlist .= "#EXT-X-VERSION:3\n";
        $playlist .= "#EXT-X-TARGETDURATION:10\n";
        $playlist .= "#EXT-X-MEDIA-SEQUENCE:0\n";

        foreach ( $pattern_ids as $pattern_id ) {
            $playlist .= "#EXTINF:10.0,\n";
            $playlist .= site_url( '/wp-json/aevov-stream/v1/chunk/' . $pattern_id ) . "\n";
        }

        $playlist .= "#EXT-X-ENDLIST\n";

        return $playlist;
    }
}
