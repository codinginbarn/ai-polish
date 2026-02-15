=== AI Polish ===
Contributors: texasbiz
Tags: openai, content rewrite
Requires at least: 6.0
Tested up to: 6.9.2
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rewrite or polish WordPress content with OpenAI directly in Gutenberg and Classic Editor.

== Description ==

AI Polish is a focused editorial plugin for WordPress.

It does one job well:
- Polish content for grammar, flow, and readability
- Rewrite content for stronger structure and clarity

It works inside the post editor for:
- Posts
- Pages
- Public custom post types

You can optionally include title and excerpt updates, and apply output automatically or manually.

AI Polish is intentionally targeted and lightweight. It is not an all-in-one plugin suite.

== Features ==

- Two editing modes: `Polish` and `Rewrite`
- Supports Gutenberg and Classic Editor
- Optional title and excerpt updates
- Auto-replace support after successful generation
- Model selection from your OpenAI account
- Connection test and model loader tools in settings
- Uses your own OpenAI API key

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-polish` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Go to `Settings -> AI Polish`.
4. Enter your OpenAI API key and save.
5. (Optional) Load and select an OpenAI model.
6. Open any post/page/CPT editor and use the `AI Polish` metabox.

== Frequently Asked Questions ==

= Where is my OpenAI API key stored? =

The key is stored in your WordPress options table under the plugin settings option (`ai_polish_settings`).

= Does this work with Gutenberg and Classic Editor? =

Yes. AI Polish supports both editor types.

= What is the difference between Polish and Rewrite? =

`Polish` keeps structure and meaning stable while improving clarity and flow.
`Rewrite` allows stronger rephrasing and restructuring while preserving core meaning.

= Can it update title and excerpt too? =

Yes. Enable the `Update title` and/or `Update excerpt` options in the metabox before running.

= Why do I see timeout errors? =

Your server may not be able to reach `api.openai.com` over HTTPS. Check outbound firewall and DNS settings.

= Why does it say no content to process? =

Save/update the post once and run again. In some editor contexts, the plugin may fall back to saved post content.

== Screenshots ==

1. AI Polish settings page with API key, model, and tools.
2. AI Polish metabox in the post editor.
3. Output preview with manual or auto replace workflow.

== Changelog ==

= 0.1.0 =
* Initial release.
* Added OpenAI-powered Polish and Rewrite actions.
* Added support for Gutenberg and Classic Editor.
* Added settings page with API key, model selection, and connection tools.
* Added optional title/excerpt handling and auto-replace.

== Upgrade Notice ==

= 0.1.0 =
Initial public release.
