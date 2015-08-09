<?php
define('BANGUMI_JSON', 'http://www.bilibili.com/index/slideshow/13.json');
define('IMAGE_FIXER', 'http://' . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . '/fix_image.php');
define('FILESIZE_LIMIT', 200 * 1024); //200KB
define('IMAGE_DIM_LIMIT', 1024);

//Handle compression
function raw_get($url) {
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'header' => implode("\r\n", array(
                'Accept-Charset: utf-8;q=0.7,*;q=0.7' // optional
            ))
        )
    ));
    $content = file_get_contents($url, FILE_TEXT, $context);
    foreach ($http_response_header as $value) {
        if(stristr($value, 'gzip') !== false) {
            $content = gzdecode($content);
            break;
        }
    }
    return $content;
}

//Handle encoding
function retrieve_url($fn) {
    return mb_convert_encoding(raw_get($fn), 'UTF-8');
}

//Handle oversize images
function fix_image($img_url) {
    //First download it
    $fn = tempnam(sys_get_temp_dir(), 'NOT');
    file_put_contents($fn, raw_get($img_url));

    list($w, $h) = getimagesize($fn);

    if(filesize($fn) > FILESIZE_LIMIT || $w > IMAGE_DIM_LIMIT || $h > IMAGE_DIM_LIMIT) {
        $img_url = IMAGE_FIXER . '?img=' . rawurlencode($img_url);
    }

    unlink($fn);
    return $img_url;
}

//Fetch list of new bangumi
$bangumi = json_decode(retrieve_url(BANGUMI_JSON), true);
$num = $bangumi['results'];
$id = $_GET['id'] ? $_GET['id'] : 1;

if($id > $num) {
    //Too many bangumi, quitting...
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    ?>
    <h2>Out of range</h2>
    <?php
    die();
}

header('Content-Type:text/xml; charset=utf-8');
$target = $bangumi['list'][$id - 1];

//Set default values
$image_wide = $target['img'];
$image_square = $target['img'];
$title = htmlentities($target['title'], ENT_XML1);

//Find the image for small tile
$bangumi_page = retrieve_url($target['link']);
//Find <img src="{{link}}" style="display:none;" class="cover_image"/>
$dom = new DOMDocument();
@$dom->loadHTML($bangumi_page);
$finder = new DomXPath($dom);
$classname="cover_image";
$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

if($nodes && $nodes->length > 0) {
    //Cover image found, use it
    $img_node = $nodes->item(0);
    $image_square = $img_node->getAttribute('src') ? $img_node->getAttribute('src') : $image_square;
}
//Test properties of images in case we need to fix them

$image_square = fix_image($image_square);
$image_wide = fix_image($image_wide);

//Now generate XML
?>
<tile>
    <visual lang="en-US" version="2">
        <binding template="TileSquare150x150PeekImageAndText04" branding="name" fallback="TileSquarePeekImageAndText04">
            <image id="1" src="<?=$image_square?>" alt=""/>
            <text id="1"><?=$title?></text>
        </binding>
        <binding template="TileWide310x150ImageAndText01" branding="name" fallback="TileWideImageAndText01">
            <image id="1" src="<?=$image_wide?>" alt=""/>
            <text id="1"><?=$title?></text>
        </binding>
        <binding template="TileSquare310x310ImageAndTextOverlay01">
            <image id="1" src="<?=$image_square?>" alt=""/>
            <text id="1"><?=$title?></text>
        </binding>
    </visual>
</tile>

