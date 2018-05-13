<?php
/*
Plugin Name: Cloudinary
Plugin URI: http://cloudinary.com/
Description: Cloudinary allows you to upload your images to the cloud. They'll be available to your visitors through a fast content delivery network, improving your website's loading speed and overall user experience. With Cloudinary, you can transform uploaded images without leaving Wordpress - apply effects (sharpen, gray scale, sepia, and more), smart cropping and re-sizing (including face detection based cropping), and much more.

Version:  1.1.10
Author: Cloudinary Ltd.
Author URI: http://cloudinary.com/
*/
require_once plugin_dir_path( __FILE__ ) . 'autoload.php';

define('cloudinary_VERSION', '1.1.10');
define('cloudinary_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define ('CLOUDINARY_BASE_URL', "https://cloudinary.com");
define ('CLOUDINARY_UNIQUE_ID', "cloudinary-image-management-and-manipulation-in-the-cloud-cdn");
define ('CLOUDINARY_USER_PLATFORM_TEMPLATE', "CloudinaryWordPress/%s (WordPress %s)");

function cloudinary_include_assets() {
  $cloudinary_js_dir = plugins_url('/js', __FILE__);
  wp_enqueue_script('jquery');
  wp_enqueue_script('cld-form', $cloudinary_js_dir . '/jquery.form.js');

  $cloudinary_css_dir = plugins_url('/css', __FILE__);
  wp_enqueue_style('cld-style', $cloudinary_css_dir . '/cloudinary.css?cv=' . cloudinary_VERSION);
}


class CloudinaryPlugin
{
  public function __construct() {
    $this->cloudinary_upgrade();
    register_uninstall_hook('uninstall.php', '');

    add_action('admin_init', array($this, 'config'));
    add_action('admin_menu', array($this, 'cloudinary_options_page'));

    add_filter('manage_media_columns', array($this, 'media_lib_add_upload_column') );
    add_action('manage_media_custom_column', array($this, 'media_lib_upload_column_value'), 0, 2);
    add_action('admin_footer-upload.php', array($this, 'media_lib_upload_admin_footer'));
    add_action('load-upload.php', array($this, 'media_lib_upload_action'));
    add_action('admin_notices', array($this, 'media_lib_upload_notices'));

    add_action('media_buttons', array($this, 'media_cloudinary'), 11);
    add_action('wp_ajax_cloudinary_update_options', array($this, 'ajax_update_options'));
    add_filter('wp_get_attachment_url', array($this, 'fix_url'), 1, 2);
    add_filter('image_downsize', array($this, 'remote_resize'), 1, 3);

    add_action('wp_ajax_cloudinary_register_image', array($this, 'ajax_register_image'));
  }

  /**
   * Backwards compatibility.
   */
  public function CloudinaryPlugin()
  {
    self::__construct();
  }

  /**
   * Called from client side when user adds image to Cloudinary
   *
   * wp_send_json prints json result and dies(exits) immediately
   *
   */
  function ajax_register_image()
  {
    if ( empty($_POST) || !check_admin_referer('cloudinary_register_image') ) {
	    wp_send_json(array( "message" => 'Sorry, your nonce did not verify.', "error" => true ));
    }

    $post_id        = $_POST["post_id"];
    $attachment_id  =&$_POST["attachment_id"];
    $url            = $_POST["url"];

    if (!empty($post_id) && !current_user_can('edit_post', $post_id) ) {
	    wp_send_json(array("message" => 'Permission denied.', "error" => true));
    }
    if (!empty($attachment_id) && !current_user_can('edit_post', $attachment_id) ) {
	    wp_send_json(array("message" => 'Permission denied.', "error" => true));
    }
    if (empty($url)) {
	    wp_send_json(array("message" => 'Missing URL.', "error" => true));
    }

    $id = $this->register_image($url, $post_id, $attachment_id, null, $_POST["width"], $_POST["height"]);
    wp_send_json(array("success"=>true, "attachment_id"=>$id));
  }

  function register_image($url, $post_id, $attachment_id, $original_attachment, $width, $height) {
    $info = pathinfo($url);
    $public_id = $info["filename"];
    $mime_types = array("png"=>"image/png", "jpg"=>"image/jpeg", "pdf"=>"application/pdf", "gif"=>"image/gif", "bmp"=>"image/bmp");
    $type = $mime_types[$info["extension"]];
    $meta = null;
    if ($original_attachment) {
      $md = wp_get_attachment_metadata($attachment_id);
      $meta = $md["image_meta"];
      $title = $original_attachment->post_title;
      $caption = $original_attachment->post_content;
    } else {
      $title    = null;
      $caption  = null;
      $meta     = null;
    }
    if (!$title) $title = $public_id;
    if (!$caption) $caption = '';
    if (!$meta) $meta = array(
                  'aperture' => 0,
                  'credit' => '',
                  'camera' => '',
                  'caption' => $caption,
                  'created_timestamp' => 0,
                  'copyright' => '',
                  'focal_length' => 0,
                  'iso' => 0,
                  'shutter_speed' => 0,
                  'title' => $title);

    $attachment = array(
            'post_mime_type' => $type,
            'guid' => $url,
            'post_parent' => $post_id,
            'post_title' => $title,
            'post_content' => $caption);
    if ($attachment_id && is_numeric($attachment_id)) {
      $attachment["ID"] = intval($attachment_id);
    }

    // Save the data
    $id = wp_insert_attachment($attachment, $url, $post_id);
    if ( !is_wp_error($id) ) {
      $metadata = array("image_meta" => $meta, "width" => $width, "height" => $height, "cloudinary"=>true);
      wp_update_attachment_metadata( $id,  $metadata);
    }
    return $id;
  }

  function migrate_away($attach_id) {
    $current_attachment = get_post($attach_id);
    $metadata = wp_get_attachment_metadata($attach_id);
    $url = wp_get_attachment_url($attach_id);
    if (!Cloudinary::option_get($metadata, "cloudinary")) {
      return "Not a Cloudinary image";
    }

    $uploads = wp_upload_dir(str_replace("-", "/", substr($current_attachment->post_date,0,7)));
    $url_info = pathinfo( $url );
    $public_id = rawurldecode($url_info["basename"]);
    $filename = wp_unique_filename( $uploads['path'], $public_id, null );
    $fullpathfilename = $uploads['path'] . "/" . $filename;

    $response = wp_remote_get($url);

    if( is_wp_error( $response ) ) {
      $error = $response->get_error_message();      
      return $public_id . ' cannot be migrate away. ' . $error ;
    }

    if ($response["response"]["code"] != 200) {
      $error = $response["headers"]["x-cld-error"];
      if (!$error) $error = "Unable to migrate away $url";
      return $public_id . ' cannot be migrate away. ' . $error ;
    }
    $image_string = $response["body"];
    $fileSaved = file_put_contents($uploads['path'] . "/" . $filename, $image_string);
    if ( !$fileSaved ) {
      return "The file cannot be saved.";
    }
    $attachment = array(
      "ID" => $attach_id,
      'guid' => $uploads['url'] . "/" . $filename,
      "cloudinary" => null
    );
    $attrs = array('post_mime_type', 'post_title', 'post_content', 'post_status', 'post_author', 'post_name', 'post_date');
    foreach ($attrs as $key) {
      $attachment[$key] = $current_attachment->$key;
    }

    $attach_id = wp_insert_attachment( $attachment, $fullpathfilename, $current_attachment->post_parent );
    if ( !$attach_id ) {
      return "Failed to save record into database.";
    }
    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $fullpathfilename );
    wp_update_attachment_metadata( $attach_id,  $attach_data );

    $new_src = wp_get_attachment_image_src($attach_id, null);
    $errors = array();
    $this->update_image_src_all($attach_id, $attach_data, $url, $new_src[0], false, $errors);
    if (count($errors) > 0) {
      return "Cannot migrate the following posts - " . implode(", ", array_keys($errors));
    }

    return null;
  }

  /**
   * @deprecated
   * Convert metadata from cloudinary response to plugin-friendly format
   *
   * @param array $remote_meta    - metadata returned by Cloudinary API
   * @return array                - extracted metadata
   */
  function extract_metadata($remote_meta) {
    trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
    $meta = array(
            'aperture' => 0,
            'credit' => '',
            'camera' => '',
            'caption' => '',
            'created_timestamp' => 0,
            'copyright' => '',
            'focal_length' => 0,
            'iso' => 0,
            'shutter_speed' => 0,
            'title' => '',
    );
    $meta['title'] = $this->extract_meta_value($remote_meta, array('Headline', 'ObjectName'));
    $caption = $this->extract_meta_value($remote_meta, array('Caption-Abstract'));
    if ( ! empty( $caption ) ) {
      if ( empty( $meta['title'] ) ) {
        if ( strlen( $caption ) < 80 )
                $meta['title'] = $caption;
        else
                $meta['caption'] = $caption;
      } elseif ( $caption != $meta['title'] ) {
        $meta['caption'] = $caption;
      }
    }
    $meta['credit'] = $this->extract_meta_value($remote_meta, array('Artist', 'Author', 'Credit', 'By-line'));
    if ( ! empty( $remote_meta["DateCreated"] ) and ! empty( $remote_meta["TimeCreated"] ) ) // created date and time
      $meta['created_timestamp'] = strtotime($remote_meta["DateCreated"] . ' ' . $remote_meta["TimeCreated"]);
    $meta['copyright'] = $this->extract_meta_value($remote_meta, array('Copyright', 'CopyrightNotice'));
    if ( !empty( $remote_meta['Title'] ) )
      $meta['title'] = trim( $remote_meta['Title'] );
    if ( ! empty( $remote_meta['ImageDescription'] ) ) {
      if ( empty( $meta['title'] ) && strlen( $remote_meta['ImageDescription'] ) < 80 ) {
        // Assume the title is stored in ImageDescription
        $meta['title'] = trim( $remote_meta['ImageDescription'] );
        if ( ! empty( $remote_meta['UserComment'] ) && trim( $remote_meta['UserComment'] ) != $meta['title'] )
          $meta['caption'] = trim( $remote_meta['UserComment'] );
      } elseif ( trim( $remote_meta['ImageDescription'] ) != $meta['title'] ) {
        $meta['caption'] = trim( $remote_meta['ImageDescription'] );
      }
    } elseif ( ! empty( $remote_meta['Comments'] ) && trim( $remote_meta['Comments'] ) != $meta['title'] ) {
      $meta['caption'] = trim( $remote_meta['Comments'] );
    }

    $meta['camera'] = $this->extract_meta_value($remote_meta, array('Model'));
    if ( ! empty($remote_meta['DateTimeDigitized'] ) )
      $meta['created_timestamp'] = wp_exif_date2ts($remote_meta['DateTimeDigitized'] );
    $meta['iso'] = $this->extract_meta_value($remote_meta, array('ISO'), 0);

    if ( ! empty($remote_meta['FNumber'] ) )
      $meta['aperture'] = round( wp_exif_frac2dec( $remote_meta['FNumber'] ), 2 );
    if ( ! empty($remote_meta['FocalLength'] ) )
      $meta['focal_length'] = (string) wp_exif_frac2dec( $remote_meta['FocalLength'] );
    if ( ! empty($remote_meta['ExposureTime'] ) )
      $meta['shutter_speed'] = (string) wp_exif_frac2dec( $remote_meta['ExposureTime'] );

    return $meta;
  }

  /**
   * @deprecated
   */
  function extract_meta_value($info, $keys, $default='') {
    trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
    foreach ($keys as $key) {
      if ( ! empty($info[$key] ) ) {
        return trim($info[$key]);
      }
    }
    return $default;
  }

  function get_wp_sizes() {
    if (isset($this->sizes)) return $this->sizes;
    // make thumbnails and other intermediate sizes
    global $_wp_additional_image_sizes;
    $sizes = array();

    foreach ( get_intermediate_image_sizes() as $s ) {
      $sizes[$s] = array( 'width' => '', 'height' => '', 'crop' => false );
      if ( isset( $_wp_additional_image_sizes[$s]['width'] ) )
        $sizes[$s]['width'] = intval( $_wp_additional_image_sizes[$s]['width'] ); // For theme-added sizes
      else
        $sizes[$s]['width'] = get_option( "{$s}_size_w" ); // For default sizes set in options
      if ( isset( $_wp_additional_image_sizes[$s]['height'] ) )
        $sizes[$s]['height'] = intval( $_wp_additional_image_sizes[$s]['height'] ); // For theme-added sizes
      else
        $sizes[$s]['height'] = get_option( "{$s}_size_h" ); // For default sizes set in options
      if ( isset( $_wp_additional_image_sizes[$s]['crop'] ) )
        $sizes[$s]['crop'] = intval( $_wp_additional_image_sizes[$s]['crop'] ); // For theme-added sizes
      else
        $sizes[$s]['crop'] = get_option( "{$s}_crop" ); // For default sizes set in options
    }

    $this->sizes = apply_filters( 'intermediate_image_sizes_advanced', $sizes );
    return $this->sizes;
  }

  function fix_url($url, $post_id) {
    $metadata = wp_get_attachment_metadata($post_id);
    if (Cloudinary::option_get($metadata, "cloudinary") && preg_match('#^.*?/(https?://.*)#', $url, $matches)) {
      return $matches[1];
    }
    return $url;
  }

  /**
   * Build Cloudinary URL for resized image
   *
   * @param string         $url - original image url
   * @param array          $metadata - original image metadata
   * @param string|array   $size - target size. Can be array with width, height parameters, or
   *                               string with predefined sizes, see $this->get_wp_sizes for available values
   *
   * @return false|array Array containing the image URL, width, height, and boolean for whether
   *                     the image is an intermediate size. False on failure.
   */
  function build_resize_url($url, $metadata, $size) {
    // Check if this is a Cloudinary URL
    if (!preg_match('#(.*?)/(v[0-9]+/.*)$#', $url, $matches)) {
      return false;
    }

    if (!$size) {
      return array($url, $metadata["width"], $metadata["height"], false);
    }

    if (is_string($size)) {
        $available_sizes = $this->get_wp_sizes();
        // Unsupported custom size or 'full' image return as is, indicating that it was not changed
        if(!array_key_exists($size, $available_sizes)) {
            return array($url, $metadata["width"], $metadata["height"], false);
        }

        $wanted = $available_sizes[$size];
        $crop = $wanted["crop"];
    }
    elseif (is_array($size)) {
      $wanted = array("width" => $size[0], "height" => $size[1]);
      $crop = false;
    }
    else{
        // Unsupported argument
        return false;
    }

    $transformation = "";
    $src_w = $dst_w = $metadata["width"];
    $src_h = $dst_h = $metadata["height"];
    if ($crop) {
      $resized = image_resize_dimensions($metadata['width'], $metadata['height'], $wanted['width'], $wanted['height'], true);
      if ($resized) {
        list ($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $resized;
        $transformation = "c_crop,h_$src_h,w_$src_w,x_$src_x,y_$src_y/";
      }
    }

    list($width, $height) = image_constrain_size_for_editor($dst_w, $dst_h, $size);
    if ($width != $src_w || $height != $src_h) {
      $transformation = $transformation . "h_$height,w_$width/";
    }

    $url = "$matches[1]/$transformation$matches[2]";

    return array($url, $width, $height, true);
  }

  /**
   * Filter for image_downsize wordpress function
   *
   * See https://codex.wordpress.org/Function_Reference/image_downsize
   *
   * @return false|array Array containing the image URL, width, height, and boolean for whether
   *                     the image is an intermediate size. False on failure.
   */
  function remote_resize($dummy, $post_id, $size) {
    $url = wp_get_attachment_url($post_id);
    $metadata = wp_get_attachment_metadata($post_id);
    if (!Cloudinary::option_get($metadata, "cloudinary")) {
        return false;
    }

    return $this->build_resize_url($url, $metadata, $size);
  }

  /* Upgrade */
  function cloudinary_upgrade() {
    $current_version = get_option('cloudinary_version');
    if ($current_version != cloudinary_VERSION) {
      if ($current_version && version_compare($current_version, '1.0.4', '<')) {
        /* cloudinary_url used to be stored in a configuration table. migrate to an option */
        global $wpdb;
        $table_name_account = $wpdb->prefix . "cloudinary_config";
        $myrows = $wpdb->get_results( "SELECT * FROM $table_name_account where user_id = 1" );
        if(!empty($myrows)){
          $cloudinary_url = trim($myrows[0]->cloudinary_url);
          update_option('cloudinary_url', $cloudinary_url);
        }
        /* drop table in database */
        $wpdb->query("DROP TABLE IF EXISTS $table_name_account");
      }
      update_option('cloudinary_version', cloudinary_VERSION);
    }
  }

  /* Configure Cloudinary integration if settings available */
  function config() {
    $cloudinary_url = get_option('cloudinary_url');
    if($cloudinary_url){
      Cloudinary::config_from_url($cloudinary_url);
      Cloudinary::$USER_PLATFORM = self::get_user_platform();
    }
  }

  /**
   * Provides USER_PLATFORM string that is prepended to USER_AGENT string that is passed to the Cloudinary servers.
   *
   * Sample value: CloudinaryWordPress/1.2.3 (WordPress 4.5.6)
   *
   * @return string USER_PLATFORM
   */
  private static function get_user_platform(){
    return sprintf(CLOUDINARY_USER_PLATFORM_TEMPLATE, cloudinary_VERSION, get_bloginfo('version'));
  }

  function configured() {
    return Cloudinary::config_get("api_secret") && Cloudinary::config_get("cloud_name") && Cloudinary::config_get("api_key");
  }

  /* Configure menus */
  function cloudinary_options_page() {
    $this->config(); # This is called before admin_init
    $settings_item = CLOUDINARY_UNIQUE_ID . "/options.php";
    $library_item = CLOUDINARY_UNIQUE_ID . "/library.php";

    $main_action = $this->configured() ? $library_item : $settings_item;
    add_menu_page('Cloudinary Menu', 'Cloudinary', 'manage_options', $main_action, null, plugins_url(CLOUDINARY_UNIQUE_ID . '/images/favicon.png'));
    add_submenu_page($main_action, "Cloudinary Media Library", "Media library", 'publish_pages', $main_action);
    add_submenu_page($main_action, "Cloudinary Settings", "Settings", 'manage_options', $settings_item);
  }

  function ajax_update_options() {
    if ( empty($_POST) || !check_admin_referer('cloudinary_update_options') ) {
       echo 'Sorry, your nonce did not verify.';
    } else {
      $cloudinary_url = str_replace("CLOUDINARY_URL=", "", trim($_POST['cloudinary_url']));
      Cloudinary::config_from_url($cloudinary_url);
      if ($this->configured()){
        update_option('cloudinary_url', $cloudinary_url);
        $url = $this->prepare_cloudinary_media_lib_url("check");
        $args = array("method"=>"GET", "timeout"=>5, "redirection"=>5, "httpversion"=>"1.0", "blocking"=>true, "headers"=>array(),
                      "body"=>null, "cookies"=>array(), "sslverify"=>false);
        $response = wp_remote_get($url, $args);
        if (is_wp_error( $response )) {
          echo 'Cannot access Cloudinary (error ' . $response->get_error_message() . ") - Verify your CLOUDINARY_URL";
        } else if ($response["response"]["code"] == "200") {
          echo 'success';
        } else {
          echo 'Cannot access Cloudinary (error ' . $response["response"]["code"] . ") - Verify your CLOUDINARY_URL";
        }
      } else {
        echo 'Invalid CLOUDINARY_URL. Must match the following format: cloudinary://api_key:api_secret@cloud_name';
      }
    }
    die();
  }

  function update_image_src_all($attachment_id, $attachment_metadata, $old_url, $new_url, $migrate_in, &$errors) {
    $query = new WP_Query(
      array(
        'post_type' => 'any',
        'post_status' => 'publish,pending,draft,auto-draft,future,private',
        's' => "wp-image-{$attachment_id}"
      )
    );

    while ($query->have_posts()) {
      $query->the_post();
      $this->update_image_src($query->post, $attachment_id, $attachment_metadata, $old_url, $new_url, $migrate_in, $errors);
    }
  }

  function update_image_src($post, $attachment_id, $attachment_metadata, $old_url, $new_url, $migrate_in, &$errors) {
    $this->get_wp_sizes();
    $post_content = $post->post_content;
    preg_match_all('~<img.*?>~i', $post->post_content, $images);
    foreach ($images[0] as $img) {
      if (preg_match('~class *= *["\']([^"\']+)["\']~i', $img, $class) && preg_match('~wp-image-(\d+)~i', $class[1], $id) && $id[1]==$attachment_id) {
        $wanted_size = null;
        if (preg_match('~size-([a-zA-Z0-9_\-]+)~i', $class[1], $size)) {
          if (isset($this->sizes[$size[1]])) {
            $wanted_size = $size[1];
          } else if ($size[1] == "full") {
            # default url requested
          } else {
            # Unknown transformation.
            if ($migrate_in) {
              continue; # Skip
            } else {
              error_log("Cannot automatically migrate image - non-standard image size detected " . $size[1]);
              $errors[$post->ID] = true;
              return false;
            }
          }
        }
        if (preg_match('~src *= *["\']([^"\']+)["\']~i', $img, $src)) {
          if ($migrate_in) {
            # Migrate In
            list($new_img_src) = $this->build_resize_url($new_url, $attachment_metadata, $wanted_size);
            if ($new_img_src) {
              $post_content = str_replace($src[1], $new_img_src, $post_content);
            }
          } else {
            # Migrate Out
            list($old_img_src) = $this->build_resize_url($old_url, $attachment_metadata, $wanted_size);
            if ($old_img_src) {
              //Compare URLs ignoring secure protocol
              if (str_replace('https://', 'http://', $old_img_src) != str_replace('https://', 'http://', $src[1])) {
                error_log("Cannot automatically migrate image - non-standard image url detected " . $src[1] . " expected $old_img_src requested size $wanted_size");
                $errors[$post->ID] = true;
                return false;
              }
              if (!isset($wanted_size)) $wanted_size = "full";
              list($new_img_src) = image_downsize($attachment_id, $wanted_size);
              if (!$new_img_src) {
                error_log("Cannot automatically migrate image - failed to downsize " . $src[1] . " to " . $wanted_size);
                $errors[$post->ID] = true;
                return false;
              }
              $post_content = str_replace($src[1], $new_img_src, $post_content);
            }
          }
        }
      }
      # Also replace original link with new link, for hrefs
      $post_content = str_replace($old_url, $new_url, $post_content);
    }
    if ($post_content != $post->post_content) {
      return wp_update_post(array("post_content"=>$post_content, "ID"=>$post->ID));
    }
    return false;
  }

  function init_media_lib_integration($xdmremote, $autoShow) {
    $cloudinary_js_dir = plugins_url('/js', __FILE__);
    wp_enqueue_script('jquery');
    wp_enqueue_script('cld-xdm', $cloudinary_js_dir . '/easyXDM.min.js');
    wp_enqueue_script('cld-json2', $cloudinary_js_dir . '/json2.min.js');
    wp_enqueue_script('cld-js', $cloudinary_js_dir . '/cloudinary.js?cv=' . cloudinary_VERSION);
    $cloudinary_css_dir = plugins_url('/css', __FILE__);
    wp_enqueue_style('cld-css', $cloudinary_css_dir . '/cloudinary.css?cv=' . cloudinary_VERSION);


    $xdmbase = plugins_url('', __FILE__);
    $xdmremotehelper = CLOUDINARY_BASE_URL . "/easyXDM.name.html";
    $xdmautoshow = $autoShow ? "true" : "false";
    $ajaxurl = wp_nonce_url(admin_url('admin-ajax.php'), "cloudinary_register_image");
    return "<link href='" . $cloudinary_css_dir . "/cloudinary.css?cv=" . cloudinary_VERSION . "' media='screen' rel='stylesheet' type='text/css' />" .
     "<span id='cloudinary-library-config' data-base='$xdmbase' data-remote='$xdmremote' data-ajaxurl='$ajaxurl'".
     "data-remotehelper='$xdmremotehelper' data-autoshow=$xdmautoshow></span>";
  }

  function media_cloudinary($editor_id = 'content') {
    $xdmremote = $this->prepare_cloudinary_media_lib_url("wp_post");
    if (!$xdmremote) return "";

    echo $this->init_media_lib_integration($xdmremote, false) .
         '<a href="#" class="cloudinary_add_media button" id="' . esc_attr( $editor_id ) . '-add_media" ' .
         'title="' . esc_attr__( 'Add Media from Cloudinary' ) . '">' . __('Cloudinary Upload/Insert') . '</a><span class="cloudinary_message"></span>';

    return null;
  }

  function media_lib_add_upload_column( $cols ) {
    $cols["media_url"] = "Cloudinary";
    return $cols;
  }

  function media_lib_upload_column_value( $column_name, $attachment_id) {
    if ( $column_name == "media_url") {
      $metadata = wp_get_attachment_metadata($attachment_id);
      if (is_array($metadata) && Cloudinary::option_get($metadata, "cloudinary")) {
        $src = plugins_url('/images/edit_icon.png', __FILE__);
        echo "<span style='line-height: 24px;'><img src='$src' style='vertical-align: middle;' width='24' height='24'/> Uploaded</span>";
      } else if (Cloudinary::config_get("api_secret")) {
        $action_url = wp_nonce_url("?", "bulk-media");
        echo "<a href='$action_url&cloud_upload=$attachment_id'>Upload to Cloudinary</a>";
      }
    }
  }

  function media_lib_upload_admin_footer() {
    if (!$this->configured()) {
      return;
    }
    $img_l = plugins_url('/images/ajax-loader.gif', __FILE__);
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function() {
      jQuery("select[name='action'],select[name='action2']").each(function() {
        jQuery('<option>').val('upload_cloudinary').text('<?php _e('Upload to Cloudinary')?>').appendTo(this);
        jQuery('<option>').val('migrate_away_cloudinary').text('<?php _e('Migrate away from Cloudinary')?>').appendTo(this);
      });
      jQuery('body').prepend('<div class="black_overlay" id="fade" style="display: none;"></div><div style="background-color: white; display: none;" id="loading-image"><table border="0" cellspacing="0" cellpadding="5"><tbody><tr><td><img alt="Loading..." src="<?php echo $img_l; ?>"></td><td>Loading..Please Wait!</td></tr></tbody></table></div>');
    });
    </script>
    <?php
  }

  function upload_to_cloudinary($attachment_id, $migrate) {
    $md = wp_get_attachment_metadata($attachment_id);
    if (Cloudinary::option_get($md, "cloudinary")) {
      return "Already uploaded to Cloudinary";
    }

    $attachment = get_post($attachment_id);

    if (!empty($md)) {
      $full_path = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . $md['file'];
    } else {
	  $full_path = $attachment->guid;
	  if (empty($full_path)) {
	    return "Unsupported attachment type";
	  }
    }

    try {
      $result = \Cloudinary\Uploader::upload($full_path,array('use_filename'=>true));
    } catch(Exception $e) {
      return $e->getMessage();
    }

    $post_parent = null;
    if ($migrate) {
      $old_url = wp_get_attachment_url($attachment_id);
      $post_parent = $attachment->post_parent;
    } else {
      $attachment_id = null;
    }

    $this->register_image($result["secure_url"], $post_parent, $attachment_id, $attachment, $result["width"], $result["height"]);

    if ($migrate) {
      $errors = array();
      $this->update_image_src_all($attachment_id, $result, $old_url, $result["secure_url"], true, $errors);
      if (count($errors) > 0) {
        return "Cannot migrate the following posts - " . implode(", ", $errors);
      }
    }

    return null;
  }

  function media_lib_upload_action() {
    $wp_list_table = _get_list_table('WP_Media_List_Table');
    $action = $wp_list_table->current_action();
    $sendback = wp_get_referer();

    global $pagenow;
    if($pagenow == 'upload.php' && isset($_REQUEST['cloud_upload']) && (int) $_REQUEST['cloud_upload']) {
      if (!$this->configured()) {
         echo "Please setup environment to upload images to cloudinary";
         exit();
      }

      check_admin_referer('bulk-media');
      // Single image upload
      $error = $this->upload_to_cloudinary($_REQUEST['cloud_upload'], true);
      $_REQUEST = array();
      if ($error) {
        $errors = array($error=>1);
        $successes = 0;
      } else {
        $errors = array();
        $successes = 1;
      }

      $this->return_to_media_lib($errors, $successes, "upload_cloudinary", $sendback);
    }
    if($action === 'upload_cloudinary' || $action === 'migrate_away_cloudinary') {
      if (!$this->configured()) {
         echo "Please setup environment to upload images to cloudinary";
         exit();
      }

      // Multiple images upload
      check_admin_referer('bulk-media');

      $post_ids = array();
      if ( isset($_REQUEST['media'] ) ) {
        $post_ids = $_REQUEST['media'];
      } elseif ( isset( $_REQUEST['ids'] ) ) {
        $post_ids = explode( ',', $_REQUEST['ids'] );
      }

      $successes = 0;
      $errors = array();
      foreach( $post_ids as $k =>  $post_id ) {
        $error = $action === 'upload_cloudinary' ? $this->upload_to_cloudinary($post_id, true) : $this->migrate_away($post_id);
        if ($error) {
          if (isset($errors[$error])) {
            $errors[$error] += 1;
          } else {
            $errors[$error] = 1;
          }
        } else {
          $successes++;
        }
      }
      $this->return_to_media_lib($errors, $successes, $action, $sendback);
    }
  }

  function return_to_media_lib($errors, $successes, $action, $sendback) {
    $image = $successes == 1 ? 'image' : 'images';
    $action_message = $action === 'upload_cloudinary' ? " uploaded to cloudinary." : " migrated away from cloudinary.";
    $message = number_format_i18n($successes).' '.$image.$action_message;

    if (!empty($errors)) {
      $errors_count = array_sum($errors);
      $errors_count_message = $errors_count == 1 ? 'error' : 'errors';
      $message = "$message " . number_format_i18n($errors_count) . " failed because of the following $errors_count_message: ";
      foreach( $errors as $error =>  $error_count ) {
        $message = $message . $error;
        if ($error_count > 1) {
          $message = $message . ' (' . number_format_i18n($error_count) . ' times)';
        }
        $message = $message . '. ';
      }
    }
    $location = add_query_arg( array("cloudinary_message"=>urlencode($message)), $sendback );
    wp_redirect( $location );
    exit();
  }

  function media_lib_upload_notices() {
    global $post_type, $pagenow;
    if ($pagenow == 'upload.php' && $post_type == 'attachment' && Cloudinary::option_get($_REQUEST, 'cloudinary_message')) {
      $message = htmlentities ( $_REQUEST['cloudinary_message'] , ENT_NOQUOTES );
      echo "<div class=\"updated\"><p>{$message}</p></div>";
    }
    if ($pagenow == 'upload.php' && Cloudinary::option_get($_REQUEST, 'cloud_upload')) {
      $message = "Sorry, this file format is not supported.";
      echo "<div class=\"updated\"><p>{$message}</p></div>";
    }
  }

  function prepare_cloudinary_media_lib_url($mode) {
    if (!$this->configured()) return null;
    $params = array("timestamp" => time(), "mode"=>$mode, "plugin_version"=>cloudinary_VERSION);
    $params["signature"] = Cloudinary::api_sign_request($params, Cloudinary::config_get("api_secret"));
    $params["api_key"] = Cloudinary::config_get("api_key");
    $query = http_build_query($params);
    return CLOUDINARY_BASE_URL . "/console/media_library/cms?$query";
  }
}

$cloudinary_plugin = new CloudinaryPlugin();
