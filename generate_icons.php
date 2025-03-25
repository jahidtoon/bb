<?php
// Set header
header('Content-Type: image/png');

// Function to create a simple PWA icon
function createPWAIcon($size) {
    // Create a new image with the specified size
    $image = imagecreatetruecolor($size, $size);
    
    // Define colors
    $purple = imagecolorallocate($image, 156, 66, 245); // #9c42f5 (theme color)
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 18, 18, 18);
    
    // Fill background with purple
    imagefill($image, 0, 0, $purple);
    
    // Draw a white circle
    $center = $size / 2;
    $radius = $size * 0.4;
    imagefilledellipse($image, $center, $center, $radius*2, $radius*2, $white);
    
    // Draw "BB" text
    $font_size = $size * 0.4;
    $text = "BB";
    
    // Get text dimensions
    $bbox = imagettfbbox($font_size, 0, 'Arial', $text);
    
    // Calculate text position to center it
    $text_width = $bbox[2] - $bbox[0];
    $text_height = $bbox[7] - $bbox[1];
    $text_x = $center - ($text_width / 2);
    $text_y = $center + ($text_height / 2);
    
    // Use built-in font if no TTF available
    imagestring($image, 5, $center - 15, $center - 10, "BB", $black);
    
    return $image;
}

// Create the icon with the size from the query string
$size = isset($_GET['size']) ? intval($_GET['size']) : 192;
$icon = createPWAIcon($size);

// Output the image
imagepng($icon);
imagedestroy($icon);
?> 