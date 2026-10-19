<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$directory = $argv[1] ?? '';
if ($directory === '' || !chef_is_absolute_path_for_test($directory)) {
    throw new RuntimeException('An absolute fixture directory is required.');
}
if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
    throw new RuntimeException('The fixture directory could not be created.');
}

function chef_is_absolute_path_for_test(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

function chef_fixture_image(int $width, int $height): GdImage
{
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 28, 96, 45);
    $accent = imagecolorallocate($image, 242, 214, 143);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);
    imagefilledellipse($image, intdiv($width, 2), intdiv($height, 2), max(2, intdiv($width, 2)), max(2, intdiv($height, 2)), $accent);
    return $image;
}

$image = chef_fixture_image(640, 360);
$jpeg = $directory . '/valid.jpg';
$png = $directory . '/valid.png';
$webp = $directory . '/valid.webp';
imagejpeg($image, $jpeg, 90);
imagepng($image, $png, 6);
imagewebp($image, $webp, 85);
imagedestroy($image);
copy($jpeg, $directory . '/menu.php.jpg');
file_put_contents($directory . '/forged.jpg', "plain text\n");
file_put_contents($directory . '/malformed.png', "\x89PNG\r\n\x1a\ninvalid");
copy($jpeg, $directory . '/oversized.jpg');
file_put_contents($directory . '/oversized.jpg', str_repeat('x', 4194304), FILE_APPEND);

$huge = chef_fixture_image(4000, 4000);
imagepng($huge, $directory . '/huge.png', 9);
imagedestroy($huge);

$pngData = file_get_contents($png);
$chunkData = pack('NN', 2, 0);
$chunk = pack('N', strlen($chunkData)) . 'acTL' . $chunkData . pack('N', crc32('acTL' . $chunkData));
$animatedPng = substr($pngData, 0, 33) . $chunk . substr($pngData, 33);
file_put_contents($directory . '/animated.png', $animatedPng);
file_put_contents($directory . '/animated.webp', file_get_contents($webp) . 'ANIM');
file_put_contents($directory . '/payload.php', "<?php echo 'EXECUTED';\n");

echo json_encode(['status' => 'PASS', 'directory' => $directory], JSON_UNESCAPED_SLASHES) . PHP_EOL;
