=== Multilingual for WordPress ===
Contributors: everydaypractice
Donate link: https://everyday-practice.com/donate
Tags: typography, multilingual, text-wrapping, performance, internationalization
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Server-side text wrapping for consistent typography across languages. Wraps text in spans by language type to eliminate FOUC and provide enhanced multilingual design control.

== Description ==

Multilingual for WordPress provides server-side text wrapping functionality that eliminates Flash of Unstyled Content (FOUC) and ensures consistent typography from the first render. Based on the multilingual.js library, this plugin is optimized specifically for WordPress environments.

= Key Features =

* **Server-side Processing**: Wraps text on the server before rendering, eliminating FOUC
* **Multi-language Support**: Supports English, Korean, Chinese, Japanese, numbers, and punctuation
* **Custom Character Sets**: Add your own custom character types and patterns
* **AJAX/REST Compatible**: Works seamlessly with dynamic content updates
* **Performance Optimized**: Minimal client-side processing required
* **Flexible Selectors**: Configure which elements to automatically process

= Supported Text Types =

* **English (en)**: `ml-en` class for `[A-Za-z]+` patterns
* **Korean (ko)**: `ml-ko` class for Hangul characters
* **Chinese (cn)**: `ml-cn` class for Chinese characters
* **Japanese (jp)**: `ml-jp` class for Hiragana and Katakana
* **Numbers (num)**: `ml-num` class for `[0-9]+` patterns
* **Punctuation (punct)**: `ml-punct` class for various punctuation marks

= How It Works =

1. Configure auto-apply selectors in plugin settings
2. Server-side processing wraps text in appropriate `<span>` elements
3. Client-side script handles dynamic content updates
4. CSS styling provides consistent typography across languages

= Use Cases =

* Multilingual websites needing consistent typography
* Sites with mixed language content
* Typography-focused designs requiring precise control
* Performance-sensitive sites avoiding client-side text processing

= Compatibility =

This plugin follows the patterns and class naming conventions of the original [multilingual.js](https://github.com/multilingualjs/multilingual.js) library, making it easy to migrate existing projects.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/multilingual-js-for-wordpress/` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings → Multilingual Settings screen to configure the plugin

= Basic Configuration =

1. Go to **Settings → Multilingual Settings**
2. Select the text types you want to wrap (English, Korean, Chinese, etc.)
3. Configure auto-apply selectors (e.g., `.content`, `article`, `p`)
4. Set exclusion selectors for areas to skip
5. Save settings

= Manual Application =

For dynamic content, you can manually trigger text wrapping:

`window.MLWP_APPLY(document);`

**Important**: This function only works on elements matching your configured auto-apply selectors.

== Frequently Asked Questions ==

= How is this different from the original multilingual.js? =

This WordPress plugin provides server-side text wrapping, eliminating Flash of Unstyled Content (FOUC) that occurs with client-side processing. It's specifically optimized for WordPress with features like AJAX/REST compatibility and WordPress hook integration.

= Does it work with page builders? =

Yes, the plugin works with most page builders including Gutenberg, Elementor, and others. It processes content through WordPress hooks and handles dynamic updates via MutationObserver.

= Can I add custom character sets? =

Yes! You can define custom character types in the plugin settings. For example:
- `parentheses:(){}[]`
- `bullet:•◦▪▫`
- `currency:$€¥₩`

= Does it affect site performance? =

The plugin is optimized for performance. Server-side processing only occurs when necessary, and client-side processing is minimal. Most text wrapping happens during page generation.

= Is it compatible with caching plugins? =

Yes, since most processing happens server-side during content generation, it works well with caching plugins. Dynamic content updates are handled client-side as needed.

= Can I exclude certain areas from processing? =

Yes, use the "Exclusion Selectors" setting to specify areas that should not be processed. The plugin also automatically excludes `<script>`, `<style>`, `<code>`, `<pre>`, and other technical elements.

= Does it work with multilingual plugins like Polylang or WPML? =

Yes, this plugin focuses on typography and text wrapping rather than translation, so it complements translation plugins well.

== Screenshots ==

1. Plugin settings page showing configuration options
2. Auto-apply selectors configuration
3. Custom character sets setup
4. Frontend example showing wrapped text with custom CSS
5. Developer tools showing generated span elements

== Changelog ==

= 1.1.0 =
* Enhanced server-side processing for better performance
* Added support for custom character sets
* Improved AJAX and REST API compatibility
* Added exclusion selectors functionality
* Enhanced MutationObserver for dynamic content
* Improved shortcode whitelist feature
* Better protection for template tokens and shortcodes

= 1.0.0 =
* Initial release
* Server-side text wrapping functionality
* Support for English, Korean, Chinese, Japanese, numbers, and punctuation
* Basic AJAX compatibility
* WordPress admin interface

== Upgrade Notice ==

= 1.1.0 =
This version adds custom character sets and improved performance. Please review your settings after upgrading.

== Advanced Usage ==

= CSS Styling Example =

```css
/* Typography optimization for mixed languages */
body {
  font-family: 'Roboto', 'Noto Sans KR', sans-serif;
  font-size: 1rem;
  line-height: 1.6;
}

.ml-en,
.ml-num,
.ml-punct {
  position: relative;
  font-size: 94%;
  top: -0.01em;
}

.ml-ko {
  font-weight: 400;
}

.ml-cn,
.ml-jp {
  font-weight: 500;
}
```

= Shortcode Protection =

To protect specific shortcodes from processing, add them to the shortcode whitelist:
- `[your_shortcode]` content will be preserved
- Only whitelisted shortcodes are protected
- Template tokens `{{variable}}` are always protected

= Developer Hooks =

The plugin respects WordPress coding standards and provides standard WordPress hooks for customization.

== Support ==

For support, feature requests, or bug reports, please visit:
* Plugin support forum on WordPress.org
* GitHub repository: https://github.com/everyday-practice/Multilingual.js-for-WordPress
* Developer website: https://everyday-practice.com

== Credits ==

This plugin is inspired by and compatible with the [multilingual.js](https://github.com/multilingualjs/multilingual.js) library. Special thanks to the original multilingual.js contributors. 