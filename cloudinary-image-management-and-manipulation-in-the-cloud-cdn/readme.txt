=== Cloudinary – Image and Video Optimization, Manipulation, and Delivery ===
Contributors: Cloudinary, XWP, Automattic
Tags: image, video, DAM, optimization, CDN
Requires at least: 4.7
Tested up to: 5.4.2
Requires PHP: 5.6
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use Cloudinary to efficiently create, manage and deliver images, videos, and other media, personalized and optimized for every device and channel.

== Description ==
Cloudinary makes automating your image, video, and rich media workflow easy, offering a variety of features. From media upload to image optimization, video transcoding and manipulation through delivery via global CDNs, everything is offered from within the plugin.

Optimize your site performance and user experience with faster page load times, advanced auto-responsive delivery, and better visual experience. Automate image quality and video encoding settings, scale and crop images using AI to focus on the most important region, apply comprehensive video and image manipulations and effects, and deliver optimized media experiences to any device in any resolution or pixel density. 

And with advanced digital asset management capabilities available in the plugin, you won’t need to leave your WordPress environment. Take full advantage of advanced search, AI-based tagging, rich media editing, collections, structured metadata, and an intuitive UI as well as our Video and Image APIs for headless DAM functionality, making it much easier to manage all of your media in WordPress. 


= Requirements =

* All you need to get started is to register for a free [Cloudinary account](https://cloudinary.com/users/register/free?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace) to use the plugin and start uploading your media to the cloud.
* As your site’s media management needs grow, you have the choice to easily upgrade to higher usage plans with practically limitless scale.

= Highlights =

* Automatically optimize and apply specified global transformations to pre-existing media upon plugin installation as well as all future uploads.
* Search and browse your media with AI-powered tagging and attributes including file type, size, format, and many other parameters all within the media library.
* Automatic responsive images for delivery on various devices in different resolutions.
* Taxonomy level transformations allow users to set transformations by “category” or “tag” and apply these in real time.
* Transcode, AI crop, apply filters, generate thumbnails, and more with our video transformation capabilities.
* The most comprehensive array of image and video manipulation capabilities, powered by AI.
* Ability to display videos using Cloudinary’s video player giving additional features such as bitrate limit, analytics, monetization, and more.

= Simple Integration =

* Upload and manage your assets with Cloudinary directly from your WordPress interface.
* Migrate all your locally hosted assets to Cloudinary.
* Move your existing posts’ images and videos to Cloudinary with a single click.


= About Cloudinary =

* [Our website](http://cloudinary.com?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)
* [Blog](http://cloudinary.com/blog?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)
* [Features overview](http://cloudinary.com/features?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)
* [DAM solution](https://cloudinary.com/solutions/digital_asset_management?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)
* [Detailed documentation](http://cloudinary.com/documentation?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)
* [Image transformations documentation](http://cloudinary.com/documentation/image_transformations?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)
* [Video transformations documentation](https://cloudinary.com/documentation/video_manipulation_and_delivery?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)
* [FAQ](http://cloudinary.com/faq?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)

= Contributors & Developers =
“Cloudinary – Image and Video Optimization, Manipulation, and Delivery” is an open source software. The following people have contributed to this plugin:
[Cloudinary](https://profiles.wordpress.org/cloudinary/)
[XWP](https://profiles.wordpress.org/xwp/)
[Automattic](https://profiles.wordpress.org/automattic/)


== Installation ==
= Install from within wordpress =
* Visit the plugins page within your dashboard and select `Add New`.
* Search for `Cloudinary`.
* Select "Cloudinary – Image and Video Optimization, Manipulation, and Delivery" from the list.
* Activate `Cloudinary` from your Plugins page.
* Go to `Setting up` below.

= Install Cloudinary manually =
* Upload the `Cloudinary` folder to the /wp-content/plugins/ directory.
* Activate the `Cloudinary` plugin through the `Plugins` menu in WordPress.
* Go to `Setting up` below.

= Setting up =
* Once the plugin is activated, go to the `Cloudinary` settings.
* You’ll be prompted to “Add your Cloudinary URL”.
* Enter your “Cloudinary environment variable URL”, the format should be cloudinary://{API_Key}:{API_Secret}@{Cloud_Name} and can be found in the "Account Details" section of the Cloudinary Console Dashboard, then click save.
* After saving, additional settings tabs will be available.

**Note**
If you have two factor authentication configured for your account, you will need to open the Cloudinary Console and login before you can use the Cloudinary plugin.
Your site is now setup to start using Cloudinary.


== Frequently Asked Questions ==
= Upgrade from v1=

Once installing the new version of the plugin, the plugin will automatically upgrade all of your assets to work with the new plugin.
Upgrade is seamless and requires no action from your side.

= Does the plugin sync all of my media to Cloudinary? =

The plugin will automatically sync all of your WordPress media to your Cloudinary account and start delivering assets from Cloudinary.
Your assets in the WP storage will remain intact. 

= Where can I find more info? =

You can read the plugin [documentation](https://cloudinary.com/documentation/wordpress_integration?utm_source=wp&utm_medium=wpmarketplace&utm_campaign=wpmarketplace)

= Compatibility with other Plugins/Themes =

The following plugins/themes/page-builders might not be fully compatible with the Cloudinary plugin and advised to be tested side-by-side before enabling in production. 

* Divi
* Elementor
* WPBakery
* Goodlayers
* Leopard
* Cube Portfolio


== Screenshots ==
1. Streamline Your Creative Workflow
2. Media Editor
3. Media Library
4. Easy Generation of Asset Derivatives
5. Automatically Deliver Responsive Images
6. Global Image Transformation Settings
7. Global Video Transformation Settings
8. Folder and Syncing Settings
9. Easily Configure Your Account

== Changelog ==

= 2.1.9 (04 Aug 2020) = 

Fixes and Improvements: 

* Upgraded the Cloudinary video version
* Added two new synced icons: Downloading and Syncing meta data
* Minor UI improvements
* Bug fixes:
    - Fixed the $wpsize_!_cld_full! param that can be observed in the Cloudinary URL
    - Fixed wrong calculation of quota credits vs percentage
    - Fixed Upload error on files over 100mb from the previous version
    - Fixed the Ability of Changing the Public Id
    - Fixed Interaction with code blocks with filters that might cause “invalid content”
    - Fixed Overwrite transformations on videos
    - Fixed excessive backslash stripping

= 2.1.2 (09 Jun 2020) = 

Fixes and Improvements: 

  * Fixed cases where the image size were added to the URL.
  * Added support to dashes ('-') in the connection string.
  * Added an option to re-sync a single asset to Cloudinary.

= 2.1.1 (01 Jun 2020) = 

New features:

  * We now provide several options for the WP<->Cloudinary sync, allowing you to better control your media:
     - Bulk-sync - Will sync all assets with Cloudinary in a click-of-a-button.
     - Auto-sync - Will sync new uploaded assets in an on-demand manner.
     - Manual - Added a `push to Cloudinary` button to the Media Library bulk actions that allows syncing selected assets to Cloudinary.
  * Global Transformations are now being applied to Featured Images.
  * Added an `Account Status` to the dashboard tab, displaying the account usage, quota and metrics.

Fixes and Improvements: 

  * Improved the sync mechanism.
  * General bug fixes and performance improvements.
  * Improved error handling.

= 2.0.3 (03 Apr 2020) =
  * Fix migration issue

= 2.0.0 (31 Mar 2020) =
  * Release of a new major version of the plugin

== Upgrade Notice ==
Enjoy a seamless upgrade to experience the completely new look and feel of our plugin. Boasting many new features including our digital asset management platform, video player offering advanced capabilities, auto-responsive images, automatic optimizations and transformations, and much more.