<?php
/**
 * ImageUploadConfig.php
 *
 * @package ImageUpload
 * @link
 * @author Jos <jhvandragt@gmail.com>
 * @copyright 2026 nljos
 * @license AGPLv3 https://www.gnu.org/licenses/agpl-3.0.en.html
 */



//!\ Settings with this marking are used in both ImageUpload.php and ImageUploadHandler.php



/**
 * --------------------------------------------------------------------------
 * Upload directory
 * --------------------------------------------------------------------------
 */

//!\ Root url where uploaded files are hosted
$ImageUpload_config['root_url'] = 'https://images.beneluxspoor.net';
$ImageUpload_config['root_url'] = 'http://localhost:9000';
/* ^^^ TEMP REINOUT URL, TODO */

// Root path where uploaded files are hosted. No change needed if this script itself is in subdirectory: Sources/
$ImageUpload_config['root_path'] = '/var/www';


/**
 * --------------------------------------------------------------------------
 * Upload handler
 * --------------------------------------------------------------------------
 */

// CORS: whitelist all (sub)domains that are allowed to send files to the Upload Handler. Use '*' to allow all origins
$ImageUpload_config['allow-origin'] = array(
    'http://localhost:8000',
    'https://forum.beneluxspoor.net',
    'https://test.beneluxspoor.net'
);

//!\ Prevent manipulation of settings. Env var should have the same value for both the forum and image site.
$ImageUpload_config['secret_key'] = getenv("BNLS_IMAGE_UPLOAD_SECRET");


/**
 * --------------------------------------------------------------------------
 * Upload parameters
 * --------------------------------------------------------------------------
 */

//!\ Maximum file size (kb/mb/gb, e.g. 10mb)
$ImageUpload_config['max_file_size'] = '10mb';

// Maximum number of megapixels (e.g. 40)
$ImageUpload_config['max_megapixel'] = 40;

//!\ Downscale to maximum width (px, e.g. 1080)
$ImageUpload_config['downscale_width'] = 1800;

//!\ Downscale to maximum height (px, e.g. 1080) Aspect ratio will be preserved
$ImageUpload_config['downscale_height'] = 1800;


/**
 * --------------------------------------------------------------------------
 * Filename preferences
 * --------------------------------------------------------------------------
 */

// Add the UserID as prefix to the filename
$ImageUpload_config['filename_prefix_userid'] = true;

// Add the TopicID as prefix to the filename
$ImageUpload_config['filename_prefix_topicid'] = false;

// Add day of year as suffix to the filename (makes the filename unique per day in a two year cycle)
$ImageUpload_config['filename_suffix_day'] = true;

// Add a random id as suffix to the filename
$ImageUpload_config['filename_suffix_unique'] = false;


/**
 * --------------------------------------------------------------------------
 * Preview images
 * --------------------------------------------------------------------------
 * maximum dimensions are set in Attachment Settings -> Thumbnail Settings -> posted or attached images
 */

// Create preview image
$ImageUpload_config['create_preview_image'] = true;

// Add a suffix to the preview image filename
$ImageUpload_config['preview_image_suffix'] = 'p';

// Save preview images in this subdirectory
$ImageUpload_config['preview_image_subdir'] = '';
