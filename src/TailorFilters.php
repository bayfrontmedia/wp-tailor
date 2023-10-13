<?php

namespace Bayfront\Tailor;

class TailorFilters {

	/**
	 * Filter function used to remove the tinymce emoji plugin.
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public static function filterTinyMceEmoji( array $plugins ): array {
		return array_diff( $plugins, array( 'wpemoji' ) );
	}

	/**
	 * Remove emoji CDN hostname from DNS prefetching hints.
	 *
	 * @param array $urls (URLs to print for resource hints)
	 * @param string $relation_type (The relation type the URLs are printed for)
	 *
	 * @return array
	 */
	public static function removeEmojiFromDnsPrefetch( array $urls, string $relation_type ): array {

		if ( 'dns-prefetch' == $relation_type ) {

			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );

			$urls = array_diff( $urls, array( $emoji_svg_url ) );

		}

		return $urls;

	}

	/**
	 * Remove oEmbed support from Tiny MCE.
	 *
	 * @param array $plugins
	 *
	 * @return array
	 */
	public static function removeOembedFromTinyMce( array $plugins ): array {
		return array_diff( $plugins, array( 'wpembed' ) );
	}

	/**
	 * Remove all embeds from rewrite rules.
	 *
	 * @param array $rules
	 *
	 * @return array
	 */
	public static function removeEmbedRewriteRules( array $rules ): array {

		foreach ( $rules as $rule => $rewrite ) {

			if ( str_contains( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}

		}

		return $rules;

	}

}