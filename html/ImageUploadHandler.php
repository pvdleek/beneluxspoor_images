<?php
/**
 * ImageUploadHandler.php
 *
 * @package ImageUpload
 * @link
 * @author Jos <jhvandragt@gmail.com>
 * @copyright 2026 nljos
 * @license AGPLv3 https://www.gnu.org/licenses/agpl-3.0.en.html
 */


/**
 * --------------------------------------------------------------------------
 * Config
 * --------------------------------------------------------------------------
 */
 
require 'ImageUploadConfig.php';


/**
 * --------------------------------------------------------------------------
 * Disable browser caching
 * --------------------------------------------------------------------------
 * Prevents browsers (especially mobile browsers like iOS Safari)
 * from caching upload responses.
 */
 
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');


/**
 * --------------------------------------------------------------------------
 * CORS support
 * --------------------------------------------------------------------------
 */
 
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = (array) $ImageUpload_config['allow-origin'];

if (in_array('*', $allowed, true)) {
    // Allow all domains
    header('Access-Control-Allow-Origin: *');
    
} elseif ($origin && in_array($origin, $allowed, true)) {
    // Only allow from whitelisted domains
    header("Access-Control-Allow-Origin: $origin");
    
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Vary: Origin');


// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}


/**
 * --------------------------------------------------------------------------
 * Runtime configuration
 * --------------------------------------------------------------------------
 */

// Allow script execution for 5 minutes
@set_time_limit(5 * 60);

// Remove temporary upload chunks older than this age
$cleanupTmpDir = true;
$maxFileAge       = 5 * 3600;


/**
 * --------------------------------------------------------------------------
 * Validate security token
 * --------------------------------------------------------------------------
 */

$token = hash_hmac('sha256', $_REQUEST['modsettings'], $ImageUpload_config['secret_key']);

if (!hash_equals($token, $_REQUEST['token'] ?? '')) {
    die('{"jsonrpc":"2.0","error":{"code":101,"message":"Invalid token"},"id":"id"}');
}


/**
 * --------------------------------------------------------------------------
 * Decode settings
 * --------------------------------------------------------------------------
 */

$settings = decodeSettings($_REQUEST['modsettings']);

$userId  = (int) ($settings['userid'] ?? 0);
$action  = (string) ($settings['action'] ?? '');
$boardId = (int) ($settings['boardid'] ?? 0);
$topicId = (int) ($settings['topicid'] ?? 0);


/**
 * --------------------------------------------------------------------------
 * Build upload directory
 * --------------------------------------------------------------------------
 */

if ($action == 'pm' && !empty($ImageUpload_config['pm_upload_dir'])) {

   $uploadDir = trim($ImageUpload_config['pm_upload_dir'], '/');
}
else {

   $uploadDir = ltrim($ImageUpload_config['upload_dir'], '/');

   // Append current year to upload directory
   if ($ImageUpload_config['upload_dir_suffix_year'] || $ImageUpload_config['upload_dir_suffix_month']) {
       $uploadDir .= date('Y');
   }

   // Append current month to upload directory
   if ($ImageUpload_config['upload_dir_suffix_month']) {
       $uploadDir .= date('m');
   }

   $uploadDir = trim($uploadDir, '/');
}

if (!preg_match('~^[a-zA-Z0-9/_-]+$~', $uploadDir)) {
    die('{"jsonrpc":"2.0","error":{"code":103,"message":"Invalid upload dir"},"id":"id"}');
}


/**
 * --------------------------------------------------------------------------
 * Helper functions
 * --------------------------------------------------------------------------
 */

/**
 * Decode compressed settings payload.
 */
function decodeSettings(string $input): array
{
    $input = gzuncompress(base64_decode($input), 10 * 1024);
    $data  = json_decode($input, true);
    if (!is_array($data)) return [];

    $keyMap = [
        'userid'                               => 'u',
        'action'                               => 'a',
        'boardid'                              => 'b',
        'topicid'                              => 't',

        'max_image_width'                      => 'mw',
        'max_image_height'                     => 'mh',
        'attachmentThumbWidth'                 => 'tw',
        'attachmentThumbHeight'                => 'th',
    ];

    $reverseMap = array_flip($keyMap);
    $result     = [];

    foreach ($data as $short => $value) {
        if (isset($reverseMap[$short])) {
            $result[$reverseMap[$short]] = $value;
        }
    }

    return $result;
}


/**
 * Convert filename into a safe ASCII-only lowercase filename.
 */
function safeFilename(string $filename): array
{
    $info = pathinfo(strtolower($filename));
    $name = $info['filename'];

    // Convert UTF-8 characters to ASCII
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);

    // Replace all non-alphanumeric characters with underscores
    $name = preg_replace('/[^a-z0-9]+/', '_', $name);
    $name = trim($name, '_');

    // Fallback filename
    $name = empty($name) ? 'file' : $name;

    return [
        'name'      => $name,
        'extension' => $info['extension'],
    ];
}


/**
 * Encode decimal number into Base36.
 */
function encodeBase36($num, int $length = 4): string
{
    return str_pad(base_convert($num, 10, 36), $length, '0', STR_PAD_LEFT);
}


/**
 * Decode Base36 string back into decimal.
 */
function decodeBase36(string $str)
{
    return base_convert($str, 36, 10);
}


/**
 * Create thumbnail or resized image.
 */
function resizeImage(
    string $srcPath,
    string $destPath,
    string $mime,
    int $width,
    int $height,
    int $resizeWidth,
    int $resizeHeight,
    bool $crop = false,
    bool $forceWebp = false,
    int $quality = 85
) {

    // Create source image resource
    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($srcPath);
            break;

        case 'image/png':
            $src = imagecreatefrompng($srcPath);
            break;

        case 'image/gif':
            $src = imagecreatefromgif($srcPath);
            break;

        case 'image/webp':
            $src = imagecreatefromwebp($srcPath);
            break;

        default:
            return false;
    }

    if (!$src) {
        return false;
    }


    // Calculate destination dimensions
    if ($crop) {

        // Crop image to exact dimensions
        $srcRatio   = $width / $height;
        $thumbRatio = $resizeWidth / $resizeHeight;

        if ($srcRatio > $thumbRatio) {

            $newHeight = $height;
            $newWidth  = (int) ($height * $thumbRatio);

            $srcX = (int) (($width - $newWidth) / 2);
            $srcY = 0;

        } else {

            $newWidth  = $width;
            $newHeight = (int) ($width / $thumbRatio);

            $srcX = 0;
            $srcY = (int) (($height - $newHeight) / 2);
        }

        $dst = imagecreatetruecolor($resizeWidth, $resizeHeight);

        $dstWidth  = $resizeWidth;
        $dstHeight = $resizeHeight;

    } else {

        // Keep original aspect ratio
        $ratio = min(
            $resizeWidth / $width,
            $resizeHeight / $height,
            1  // prevent upscaling
        );

        $dstWidth  = (int) floor($width * $ratio);
        $dstHeight = (int) floor($height * $ratio);

        $newWidth  = $width;
        $newHeight = $height;

        $srcX = 0;
        $srcY = 0;

        $dst = imagecreatetruecolor($dstWidth, $dstHeight);
    }


    // Preserve transparency for PNG/GIF/WebP
    if (in_array($mime, ['image/png', 'image/gif', 'image/webp']) || $forceWebp) {
        imagecolortransparent(
            $dst,
            imagecolorallocatealpha($dst, 0, 0, 0, 127)
        );

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }


    // Resize image
    imagecopyresampled(
        $dst,
        $src,
        0,
        0,
        $srcX,
        $srcY,
        $dstWidth,
        $dstHeight,
        $newWidth,
        $newHeight
    );


    // Save image
    if ($forceWebp) {

        if (!function_exists('imagewebp')) {
            imagedestroy($src);
            imagedestroy($dst);

            return false;
        }

        imagewebp($dst, $destPath, $quality);

    } else {

        switch ($mime) {

            case 'image/jpeg':
                imagejpeg($dst, $destPath, $quality);
                break;

            case 'image/png':
                imagepng($dst, $destPath);
                break;

            case 'image/gif':
                imagegif($dst, $destPath);
                break;

            case 'image/webp':
                imagewebp($dst, $destPath, $quality);
               break;
        }
    }


    // Cleanup memory
    imagedestroy($src);
    imagedestroy($dst);

    return array(
      'width'  => $dstWidth,
      'height' => $dstHeight
    );
}


/**
 * --------------------------------------------------------------------------
 * Determine filename
 * --------------------------------------------------------------------------
 */

if (isset($_REQUEST['name'])) {

    $fileName = safeFilename($_REQUEST['name']);

} elseif (!empty($_FILES)) {

    $fileName = safeFilename($_FILES['file']['name']);

} else {

    die('{"jsonrpc":"2.0","error":{"code":104,"message":"Invalid input"},"id":0}');
}


/**
 * --------------------------------------------------------------------------
 * Apply filename prefixes/suffixes
 * --------------------------------------------------------------------------
 */

// Prefix topic ID
if ($ImageUpload_config['filename_prefix_topicid']) {
    $fileName['name'] = encodeBase36($topicId) . '-' . $fileName['name'];
}

// Prefix user ID
if ($ImageUpload_config['filename_prefix_userid']) {
    $fileName['name'] = encodeBase36($userId) . '-' . $fileName['name'];
}

// Append day-based identifier
if ($ImageUpload_config['filename_suffix_day']) {

    $dayCode = ((date('Y') % 2 === 1) ? 0 : 365) + date('z');
    $fileName['name'] .= '-' . encodeBase36($dayCode, 2);
}

// Append unique identifier
if ($ImageUpload_config['filename_suffix_unique']) {
    $fileName['name'] .= uniqid('-');
}


/**
 * --------------------------------------------------------------------------
 * Build paths
 * --------------------------------------------------------------------------
 */
 
$uploadPath = $ImageUpload_config['root_path'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadDir);

// Create upload directory if it does not exist
if (!is_dir($uploadPath)) {
    @mkdir($uploadPath, 0755, true);
}

$tmpPath = rtrim($ImageUpload_config['tmp_path'], '/');

if (!preg_match('~^[a-zA-Z0-9/_-]+$~', $tmpPath)) {
    die('{"jsonrpc":"2.0","error":{"code":102,"message":"Invalid tmp path"},"id":"id"}');
}

// Create temp directory if it does not exist
if (!is_dir($tmpPath)) {
    @mkdir($tmpPath, 0755, true);
}

$tmpFilePath = $tmpPath . DIRECTORY_SEPARATOR . $fileName['name'] . '.' . $fileName['extension'];

$filePath = $uploadPath . DIRECTORY_SEPARATOR . $fileName['name'] . '.' . $fileName['extension'];

$fileUrl = $ImageUpload_config['root_url'] . '/' . $uploadDir . '/' . $fileName['name'] . '.' . $fileName['extension'];


/**
 * --------------------------------------------------------------------------
 * Chunked upload support
 * --------------------------------------------------------------------------
 */

$chunk  = isset($_REQUEST['chunk']) ? (int) $_REQUEST['chunk'] : 0;

$chunks = isset($_REQUEST['chunks']) ? (int) $_REQUEST['chunks'] : 0;


/**
 * --------------------------------------------------------------------------
 * Cleanup old chunk files
 * --------------------------------------------------------------------------
 */

if ($cleanupTmpDir) {

    if (!is_dir($tmpPath) || !$dir = opendir($tmpPath)) {
        die('{"jsonrpc":"2.0","error":{"code":105,"message":"Failed to open temp directory."},"id":"id"}');
    }

    while (($file = readdir($dir)) !== false) {

        $tmpFile = $tmpPath . DIRECTORY_SEPARATOR . $file;

        // Skip current upload chunk
        if ($tmpFile === "{$tmpFilePath}.part") {
            continue;
        }

        // Remove expired chunk files
        if (preg_match('/\.part$/', $file) && (filemtime($tmpFile) < time() - $maxFileAge)) {
            @unlink($tmpFile);
        }
    }

    closedir($dir);
}


/**
 * --------------------------------------------------------------------------
 * Open output stream
 * --------------------------------------------------------------------------
 */

$out = @fopen(
    "{$tmpFilePath}.part",
    $chunks ? 'ab' : 'wb'
);

if (!$out) {
    die('{"jsonrpc":"2.0","error":{"code":106,"message":"Failed to open output stream."},"id":"id"}');
}


/**
 * --------------------------------------------------------------------------
 * Open input stream
 * --------------------------------------------------------------------------
 */

if (!empty($_FILES)) {

    if ($_FILES['file']['error']) {

        $errors = [
            1 => 'INI_SIZE',
            2 => 'FORM_SIZE',
            3 => 'PARTIAL',
            4 => 'NO_FILE',
            6 => 'NO_TMP_DIR',
            7 => 'CANT_WRITE',
            8 => 'EXTENSION'
        ];

        $errorcode = $_FILES['file']['error'];

        die(json_encode([
            "jsonrpc" => "2.0",
            "error" => [
                "code" => 103,
                "message" => $errors[$errorcode] ?? ($errorcode)
            ],
            "id" => "id"
        ]));
    }

    if ($_FILES['file']['error'] || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        die('{"jsonrpc":"2.0","error":{"code":107,"message":"Failed to move uploaded file."},"id":"id"}');
    }

    $in = @fopen($_FILES['file']['tmp_name'], 'rb');

} else {

    $in = @fopen('php://input', 'rb');
}

if (!$in) {
    die('{"jsonrpc":"2.0","error":{"code":108,"message":"Failed to open input stream."},"id":"id"}');
}


/**
 * --------------------------------------------------------------------------
 * Write uploaded data to temp file
 * --------------------------------------------------------------------------
 */

while ($buffer = fread($in, 4096)) {
    fwrite($out, $buffer);
}

@fclose($out);
@fclose($in);


/**
 * --------------------------------------------------------------------------
 * Finalize upload after last chunk
 * --------------------------------------------------------------------------
 */

if (!$chunks || $chunk === $chunks - 1) {
    rename("{$tmpFilePath}.part", $tmpFilePath);
}


/**
 * --------------------------------------------------------------------------
 * Validate uploaded image
 * --------------------------------------------------------------------------
 */

$imageInfo = @getimagesize($tmpFilePath);

if ($imageInfo === false) {

    @unlink($tmpFilePath);
    die('{"jsonrpc":"2.0","error":{"code":109,"message":"Corrupt file"},"id":"id"}');
}

preg_match('/(\d+)(kb|mb|gb)/', strtolower(trim($ImageUpload_config['max_file_size'])), $m);
$maxBytes = $m[1] * ['kb'=>1024,'mb'=>1048576,'gb'=>1073741824][$m[2]];

if (filesize($tmpFilePath) > $maxBytes) {

    @unlink($tmpFilePath);
    die('{"jsonrpc":"2.0","error":{"code":110,"message":"File too large"},"id":"id"}');
}


$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmpFilePath);

$allowedMimeTypes = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/gif'  => ['gif'],
    'image/webp' => ['webp'],
];

// Validate:
// 1. MIME type matches imageInfo
// 2. MIME type is allowed
// 3. File extension matches the MIME type
if (
    $mime !== $imageInfo['mime'] ||
    !array_key_exists($mime, $allowedMimeTypes) ||
    !in_array($fileName['extension'], $allowedMimeTypes[$mime], true)
) {

    @unlink($tmpFilePath);
    die('{"jsonrpc":"2.0","error":{"code":111,"message":"Invalid filetype"},"id":"id"}');
}


/**
 * --------------------------------------------------------------------------
 * Check image dimensions
 * --------------------------------------------------------------------------
 */

$width  = $imageInfo[0];
$height = $imageInfo[1];

if ($width * $height > $ImageUpload_config['max_megapixel'] * 1000000) {

    unlink($tmpFilePath);
    die('{"jsonrpc":"2.0","error":{"code":112,"message":"Image dimensions too large"},"id":"id"}');
}


/**
 * ----------------------------------------------------------------------------------
 * Rebuild image as a safety precaution (also clientside downscaling does not work for webp)
 * ----------------------------------------------------------------------------------
 */

$file = resizeImage(
	$tmpFilePath,
	$filePath,
	$mime,
	$width,
	$height,
	(int) $ImageUpload_config['downscale_width'],
	(int) $ImageUpload_config['downscale_height']
);

unlink($tmpFilePath);

if ($file === false) {
	die('{"jsonrpc":"2.0","error":{"code":113,"message":"Image corrupt"},"id":"id"}');
}

$width = $file['width'];
$height = $file['height'];



/**
 * --------------------------------------------------------------------------
 * Build result URLs
 * --------------------------------------------------------------------------
 */

$file['url'] = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $uploadDir . '/' . $fileName['name'] . '.' . $fileName['extension'];
$file['width'] = $width;
$file['height'] = $height;


/**
 * --------------------------------------------------------------------------
 * Create Preview image
 * --------------------------------------------------------------------------
 */

if (
    $ImageUpload_config['create_preview_image']  
    && (!empty($ImageUpload_config['preview_image_suffix']) || !empty($ImageUpload_config['preview_image_subdir']))
    && ($width > $settings['max_image_width'] * 1.2 || $height > $settings['max_image_height'] * 1.2)
) {

    $destDir = $uploadPath . DIRECTORY_SEPARATOR;
    $urlDir = $ImageUpload_config['root_url'] . '/' . $uploadDir . '/';

    // Create preview image subdirectory
    if (!empty($ImageUpload_config['preview_image_subdir'])) {

        $destDir .= $ImageUpload_config['preview_image_subdir'] . DIRECTORY_SEPARATOR;

        $urlDir .= $ImageUpload_config['preview_image_subdir'] . '/';

        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
    }

    $destFile = $fileName['name'];

    if (!empty($ImageUpload_config['preview_image_suffix'])) {
        $destFile .= '-' . $ImageUpload_config['preview_image_suffix'];
    }

	$preview = false;
    if ((int) $settings['max_image_width'] < $width || (int) $settings['max_image_height'] < $height) {
		$preview = resizeImage(
			$filePath,
			$destDir . $destFile . '.' . $fileName['extension'],
			$mime,
			$width,
			$height,
			(int) $settings['max_image_width'],
			(int) $settings['max_image_height']
		);
	}
      
    if ($preview !== false) {
        $preview['url'] = $urlDir . $destFile . '.' . $fileName['extension'];
    } else {
        // fallback to the file
        $preview = $file;
    }
}
else {
    // fallback to the file
    $preview = $file;
}


/**
 * --------------------------------------------------------------------------
 * Create Thumbnail image
 * --------------------------------------------------------------------------
 */

if (
    $ImageUpload_config['create_thumb']
    && (!empty($ImageUpload_config['thumb_suffix']) || !empty($ImageUpload_config['thumb_subdir']))
) {

    $destDir = $uploadPath . DIRECTORY_SEPARATOR;
    $urlDir = $ImageUpload_config['root_url'] . '/' . $uploadDir . '/';

    // Create thumbnail subdirectory
    if (!empty($ImageUpload_config['thumb_subdir'])) {

        $destDir .= $ImageUpload_config['thumb_subdir'] . DIRECTORY_SEPARATOR;
        $urlDir .= $ImageUpload_config['thumb_subdir'] . '/';

        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
    }

    $destFile = $fileName['name'];

    if (!empty($ImageUpload_config['thumb_suffix'])) {
        $destFile .= '-' . $ImageUpload_config['thumb_suffix'];
    }

	$thumb = false;
    if ((int) $settings['attachmentThumbWidth'] < $width || (int) $settings['attachmentThumbHeight'] < $height) {
		$thumb = resizeImage(
			$filePath,
			$destDir . $destFile . '.' . $fileName['extension'],
			$mime,
			$width,
			$height,
			(int) $settings['attachmentThumbWidth'],
			(int) $settings['attachmentThumbHeight'],
			true
		);
	}
      
    if ($thumb !== false) {
        $thumb['url'] = $urlDir . $destFile . '.' . $fileName['extension'];
    } else {
        // fallback to the preview
        $thumb = $preview;
    }
}
else {
    // fallback to the preview
    $thumb = $preview;
}


/**
 * --------------------------------------------------------------------------
 * Return JSON-RPC success response
 * --------------------------------------------------------------------------
 */

die(json_encode([
    'jsonrpc' => '2.0',
    'result' => [
        'success' => 1,
        'file' => $file,
        'thumb' => $thumb,
        'preview' => $preview,
    ],
    'id' => 'id',
]));
