=== Plugin Name ===
Contributors: askadice
Donate link:
Tags: copyright, text, image, content, content protection, no right click, blog protection, copy protection, copy protect, copy, paste, protection, cprotext
Requires at least: 3.9.0
Tested up to: 4.9.0
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

CPROTEXT protects your texts and images from unauthorized and fraudulent copy.
The protected contents are immune to in-browser copy/paste and web scraping.

== Description ==

CPROTEXT is an online service for texts and images protection that you can test via the example page of the [CPROTEXT website](https://www.cprotext.com "CPROTEXT: Copyright protection for online contents").

WP-CPROTEXT protects your texts and images published on a WordPress based website from any kind of digital copy, being in-browser copy/paste or web scraping by any web crawlers. When you decide to protect and publish a text or an image in WordPress, you can choose to protect it with the CPROTEXT online service. 
The returned data are then stored along the original document in your WordPress installation. These data are used to display a copy protected version of your original content.

Once stored, __the protected texts and images data are forever yours, independently of the CPROTEXT online service__. You can then choose to enable or disable this protection at will.

As explained in the [CPROTEXT F.A.Q.](https://www.cprotext.com/#faq), the protected texts and images data returned by the CPROTEXT online service are only standard HTML and CSS code. No JavaScript code is required to make this protection effective. Therefore, __contrary to other copy protection plugins, the CPROTEXT protection is engraved in the web page and can not be removed__ by hacking the web page. Moreover, attempts to alter the protected text data would result in displaying a randomly modified text.

To improve the SEO rank of your text web page, you are able to insert a placeholder text. This placeholder will be available to web crawlers such as search engines. It can either be an abstract of your content, the first lines of your text, or whatever keywords you would like to expose to search engines so that your content is efficiently referenced. This placeholder text is also used as a failover for older browsers failing to comply to basic web standards.

== Installation ==

1. Download the zip file
1. Upload and extract the content of the zip file in your `/wp-content/plugins/` directory
1. Activate the WP-CPROTEXT plugin through the 'Plugins' menu in WordPress
1. Enjoy !

== Frequently Asked Questions ==

Check the [CPROTEXT website](https://www.cprotext.com) for the F.A.Q. related
to the CPROTEXT online service.

*  Why are you doing this ? Text and image obfuscation is wrong !

   While the nature of the web is to share information widely, some decide that they want to keep control over their contents.

   If content protection is not a priority, then don't use such protection.
   But those who wish to protect their texts and images should have the choice of a real solution.

   Other available solutions give a false sense of protection since they can be easily bypassed. Moreover they usually are very intrusive by altering the browser functionalities of the website visitors, such as disabling the context menu (right click).
   With CPROTEXT, authors can protect their contents with a truly efficient solution without annoying their website visitors.

   The only valid reason against text obfuscation is the loss of accessibility.
   That's why we strongly advise every CPROTEXT user to publish their protected text with an audio version such as in the CPROTEXT [example](https://www.cprotext.com/en/example.html) page.

== Screenshots ==

1. WP-CPROTEXT Settings panel

2. To protect your images, go in the media library and check the "Protected" box.

3. To protect your texts, enable the CPROTEXT box and fill it with the relevant information.

== Changelog ==

= 2.0.0 =

* use CPROTEXT API 1.1
* add image protection
* ie8 support by default
* add screenshots

= 1.2.1 =
fix compatibility issues for php < 5.4

= 1.2.0 =

* add "no notification" and "stealth notification" options
* fix title only post updates
* fix issues with simple/double quotes and anti-slashes
* fix ie8 support related issues

= 1.1.0 =
apply WordPress good practices for AJAX use
 
= 1.0.0 =
Initial release

