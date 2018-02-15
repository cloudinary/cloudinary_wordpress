<?php
  trigger_error('CloudinaryApi is deprecated, use \Cloudinary\Api instead', E_USER_DEPRECATED);

  require_once plugin_dir_path(__FILE__) . 'lib' . DIRECTORY_SEPARATOR . "Api.php";

  /** @deprecated use \Cloudinary\Api\Error */
  class CloudinaryApiError extends Exception {}
  /** @deprecated use \Cloudinary\Api\NotFound */
  class CloudinaryApiNotFound extends CloudinaryApiError {}
  /** @deprecated use \Cloudinary\Api\NotAllowed */
  class CloudinaryApiNotAllowed extends CloudinaryApiError {}
  /** @deprecated use \Cloudinary\Api\AlreadyExists  */
  class CloudinaryApiAlreadyExists extends CloudinaryApiError {}
  /** @deprecated use \Cloudinary\Api\RateLimited */
  class CloudinaryApiRateLimited extends CloudinaryApiError {}
  /** @deprecated use \Cloudinary\Api\BadRequest */
  class CloudinaryApiBadRequest extends CloudinaryApiError {}
  /** @deprecated use \Cloudinary\Api\GeneralError */
  class CloudinaryApiGeneralError extends CloudinaryApiError {}
  /** @deprecated use \Cloudinary\Api\AuthorizationRequired */
  class CloudinaryApiAuthorizationRequired extends CloudinaryApiError {}
  /** @deprecated use \Cloudinary\Api\Response */
  class CloudinaryApiResponse extends ArrayObject {
    function __construct($response) {        
        parent::__construct(CloudinaryApi::parse_json_response($response));
        $this->rate_limit_reset_at = strtotime($response->headers["X-FeatureRateLimit-Reset"]);
        $this->rate_limit_allowed = intval($response->headers["X-FeatureRateLimit-Limit"]);
        $this->rate_limit_remaining = intval($response->headers["X-FeatureRateLimit-Remaining"]);
    }    
  }

  // Create class alias for old class
  \class_alias(\Cloudinary\Api::class, CloudinaryApi::class);

  // This tricks IDE and marks old class as deprecated
  if (! \class_exists(CloudinaryApi::class)) { // essentially this is "if(false)"
      /** @deprecated use \Cloudinary\Api */
      class CloudinaryApi {}
  }

