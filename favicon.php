<?php
// Genera el avatar circular como PNG para usar de favicon
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$src = imagecreatefrompng(__DIR__ . '/avatar.png');
$sw  = imagesx($src);
$sh  = imagesy($src);

// Recortamos la parte superior (cara) — ajusta $crop_y si necesitas
$crop_size = min($sw, $sh);
$crop_x    = ($sw - $crop_size) / 2;
$crop_y    = 0; // empieza desde arriba para incluir la cabeza

$size = 64; // tamaño del favicon
$out  = imagecreatetruecolor($size, $size);
imagesavealpha($out, true);
$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefill($out, 0, 0, $transparent);

$cx = $size / 2;
$cy = $size / 2;
$r  = $size / 2;

for ($y = 0; $y < $size; $y++) {
    for ($x = 0; $x < $size; $x++) {
        if (($x - $cx) ** 2 + ($y - $cy) ** 2 <= $r * $r) {
            $sx = (int)($crop_x + ($x / $size) * $crop_size);
            $sy = (int)($crop_y + ($y / $size) * $crop_size);
            $sx = max(0, min($sw - 1, $sx));
            $sy = max(0, min($sh - 1, $sy));
            $color = imagecolorat($src, $sx, $sy);
            $rc = ($color >> 16) & 0xFF;
            $gc = ($color >>  8) & 0xFF;
            $bc =  $color        & 0xFF;
            imagesetpixel($out, $x, $y, imagecolorallocate($out, $rc, $gc, $bc));
        }
    }
}

imagepng($out);
imagedestroy($src);
imagedestroy($out);
