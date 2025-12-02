<?php
/**
 * Noise Generator
 *
 * Implements Perlin and Simplex noise for procedural generation
 */

namespace Aevov\PhysicsEngine\World;

class NoiseGenerator {

    private $seed;
    private $perm = [];

    public function __construct($seed = null) {
        $this->set_seed($seed ?? time());
    }

    public function set_seed($seed) {
        $this->seed = $seed;
        mt_srand($seed);

        // Generate permutation table
        $this->perm = range(0, 255);
        shuffle($this->perm);
        $this->perm = array_merge($this->perm, $this->perm); // Duplicate for overflow
    }

    /**
     * 2D Perlin noise
     */
    public function noise2d($x, $y) {
        // Grid cell coordinates
        $xi = (int)floor($x) & 255;
        $yi = (int)floor($y) & 255;

        // Fractional part
        $xf = $x - floor($x);
        $yf = $y - floor($y);

        // Fade curves
        $u = $this->fade($xf);
        $v = $this->fade($yf);

        // Hash coordinates of the 4 cube corners
        $aa = $this->perm[$this->perm[$xi] + $yi];
        $ab = $this->perm[$this->perm[$xi] + $yi + 1];
        $ba = $this->perm[$this->perm[$xi + 1] + $yi];
        $bb = $this->perm[$this->perm[$xi + 1] + $yi + 1];

        // Gradients
        $g1 = $this->gradient2d($aa, $xf, $yf);
        $g2 = $this->gradient2d($ba, $xf - 1, $yf);
        $g3 = $this->gradient2d($ab, $xf, $yf - 1);
        $g4 = $this->gradient2d($bb, $xf - 1, $yf - 1);

        // Linear interpolation
        $x1 = $this->lerp($g1, $g2, $u);
        $x2 = $this->lerp($g3, $g4, $u);

        return $this->lerp($x1, $x2, $v);
    }

    /**
     * 3D Perlin noise
     */
    public function noise3d($x, $y, $z) {
        $xi = (int)floor($x) & 255;
        $yi = (int)floor($y) & 255;
        $zi = (int)floor($z) & 255;

        $xf = $x - floor($x);
        $yf = $y - floor($y);
        $zf = $z - floor($z);

        $u = $this->fade($xf);
        $v = $this->fade($yf);
        $w = $this->fade($zf);

        $aaa = $this->perm[$this->perm[$this->perm[$xi] + $yi] + $zi];
        $aba = $this->perm[$this->perm[$this->perm[$xi] + $yi + 1] + $zi];
        $aab = $this->perm[$this->perm[$this->perm[$xi] + $yi] + $zi + 1];
        $abb = $this->perm[$this->perm[$this->perm[$xi] + $yi + 1] + $zi + 1];
        $baa = $this->perm[$this->perm[$this->perm[$xi + 1] + $yi] + $zi];
        $bba = $this->perm[$this->perm[$this->perm[$xi + 1] + $yi + 1] + $zi];
        $bab = $this->perm[$this->perm[$this->perm[$xi + 1] + $yi] + $zi + 1];
        $bbb = $this->perm[$this->perm[$this->perm[$xi + 1] + $yi + 1] + $zi + 1];

        $x1 = $this->lerp(
            $this->gradient3d($aaa, $xf, $yf, $zf),
            $this->gradient3d($baa, $xf - 1, $yf, $zf),
            $u
        );

        $x2 = $this->lerp(
            $this->gradient3d($aba, $xf, $yf - 1, $zf),
            $this->gradient3d($bba, $xf - 1, $yf - 1, $zf),
            $u
        );

        $y1 = $this->lerp($x1, $x2, $v);

        $x1 = $this->lerp(
            $this->gradient3d($aab, $xf, $yf, $zf - 1),
            $this->gradient3d($bab, $xf - 1, $yf, $zf - 1),
            $u
        );

        $x2 = $this->lerp(
            $this->gradient3d($abb, $xf, $yf - 1, $zf - 1),
            $this->gradient3d($bbb, $xf - 1, $yf - 1, $zf - 1),
            $u
        );

        $y2 = $this->lerp($x1, $x2, $v);

        return $this->lerp($y1, $y2, $w);
    }

    /**
     * Fade function for smooth interpolation
     */
    private function fade($t) {
        return $t * $t * $t * ($t * ($t * 6 - 15) + 10);
    }

    /**
     * Linear interpolation
     */
    private function lerp($a, $b, $t) {
        return $a + $t * ($b - $a);
    }

    /**
     * 2D gradient
     */
    private function gradient2d($hash, $x, $y) {
        $h = $hash & 3;
        return (($h & 1) ? -$x : $x) + (($h & 2) ? -$y : $y);
    }

    /**
     * 3D gradient
     */
    private function gradient3d($hash, $x, $y, $z) {
        $h = $hash & 15;
        $u = $h < 8 ? $x : $y;
        $v = $h < 4 ? $y : ($h == 12 || $h == 14 ? $x : $z);
        return (($h & 1) ? -$u : $u) + (($h & 2) ? -$v : $v);
    }
}
