<?php
//Fix the limitation of MS of tile image
//1. image dimension < 1024
//2. image size < 200KB

define('FILESIZE_LIMIT', 200 * 1024); //200KB
define('FILESIZE_SOFT_LIMIT', 197 * 1024); //197KB
define('IMAGE_DIM_LIMIT', 1024);

//Bilibili does not respect Accept-Encoding
//They will gzip the output even missing Accept-Encoding
//So we need to ungzip the return content
function raw_get($url) {
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'header' => implode("\r\n", array(
                'Accept-Charset: utf-8;q=0.7,*;q=0.7' // optional
            ))
        )
    ));
    //Fetch raw output
    $content = file_get_contents($url, FILE_TEXT, $context);
    foreach ($http_response_header as $value) {
        //If the response encoding is gzip, ungzip the content
        if(stristr($value, 'gzip') !== false) {
            $content = gzdecode($content);
            break;
        }
    }
    return $content;
}

//Fetch the content of image
$url = $_GET['img'];
$fn = tempnam(sys_get_temp_dir(), 'NOT');
file_put_contents($fn, raw_get($url));

$img = imagecreatefromjpeg($fn);
$w = imagesx($img);
$h = imagesy($img);

$ratio = 1.0;

//Check image dimension
if($w > IMAGE_DIM_LIMIT) {
    $ratio = min($ratio, IMAGE_DIM_LIMIT / (float) $w);
}

if($h > IMAGE_DIM_LIMIT) {
    $ratio = min($ratio, IMAGE_DIM_LIMIT / (float) $h);
}

unlink($fn);

$w = (int)floor($ratio * $w);
$h = (int)floor($ratio * $h);

$img = imagescale($img, $w, $h);

$fn = tempnam(sys_get_temp_dir(), 'NOT');;
imagejpeg($img, $fn);

//Check image size
while(filesize($fn) > FILESIZE_LIMIT) {
    $ratio = sqrt(filesize($fn) / FILESIZE_SOFT_LIMIT);
    $w = (int)floor($ratio * $w);
    $h = (int)floor($ratio * $h);
    $img = imagescale($img, $w, $h);

    unlink($fn);
    $fn = tempnam(sys_get_temp_dir(), 'NOT');;
    imagejpeg($img, $fn);
}

unlink($fn);

//Output
header('Content-Type: image/jpeg');
imagejpeg($img);
imagedestroy($img);
?>