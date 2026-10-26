<?php
/**
 * Cleans the old site's post HTML (development.md section 9, step 4).
 *
 * @package InternFeel
 */

declare(strict_types=1);

// PHP's DOM classes name their properties in camelCase (parentNode, textContent, ...); they cannot be renamed.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

namespace InternFeel\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Removes inline formatting and dead embeds, rewrites internal links, and keeps only safe markup.
 */
final class Cleaner {

	/**
	 * Hosts of the old site, rewritten to the site doing the import.
	 */
	private const OLD_HOSTS = array( 'internfeel.com', 'www.internfeel.com' );

	/**
	 * Elements unwrapped (their children kept): formatting wrappers from old editors.
	 */
	private const UNWRAP = array( 'span', 'font', 'div', 'u' );

	/**
	 * Attributes dropped from every element. srcset and sizes point at the old resized files and are
	 * rebuilt by WordPress for attachments.
	 */
	private const DROP_ATTRIBUTES = array( 'style', 'class', 'id', 'lang', 'align', 'data-css', 'data-preserver-spaces', 'data-lazy-src', 'srcset', 'sizes', 'decoding', 'target' );

	/**
	 * Old plugins' shortcodes removed with everything between their tags (dead buttons and popups).
	 */
	private const SHORTCODES_REMOVE = array( 'su_button', 'otw_shortcode_button', 'sg_popup' );

	/**
	 * Old plugins' single shortcodes removed outright.
	 */
	private const SHORTCODES_DROP = array( 'su_spacer', 'mycred_load_coupon', 'su_button', 'otw_shortcode_button', 'sg_popup' );

	/**
	 * Old plugins' wrapping shortcodes whose content stays (boxes, and social or content lockers that
	 * hid the text until a visitor shared the post).
	 */
	private const SHORTCODES_UNWRAP = array( 'su_box', 'sociallocker', 'content_locker' );

	/**
	 * Clean one post body.
	 *
	 * @param string $html     The old post's rendered HTML.
	 * @param string $new_base Base URL of the site doing the import, such as https://internfeel.com.
	 * @return string
	 */
	public static function clean( string $html, string $new_base ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}

		$html = self::shortcodes( $html );

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div id="if-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $dom->getElementById( 'if-root' );
		if ( ! $root instanceof \DOMElement ) {
			return '';
		}

		self::embeds( $dom );
		self::images( $dom, $new_base );
		self::links( $dom, $new_base );
		self::text_urls( $dom, $new_base );
		self::headings( $dom );
		self::attributes( $dom );
		self::unwrap( $dom );

		$out = '';
		foreach ( iterator_to_array( $root->childNodes ) as $child ) {
			$out .= $dom->saveHTML( $child );
		}

		// Collapse paragraphs the old editors left empty.
		$out = (string) preg_replace( '#<p>(\s|&nbsp;|\xc2\xa0|<br\s*/?>)*</p>#u', '', $out );

		return trim( wp_kses_post( $out ) );
	}

	/**
	 * Remove or unwrap the old plugins' shortcodes, which would otherwise show as raw text. Only these
	 * known tags are touched: the posts also contain genuine bracketed text.
	 *
	 * @param string $html Post HTML.
	 * @return string
	 */
	private static function shortcodes( string $html ): string {
		foreach ( self::SHORTCODES_REMOVE as $tag ) {
			$html = (string) preg_replace( '#\[' . $tag . '\b[^\]]*\].*?\[/' . $tag . '\]#s', '', $html );
		}

		$drop   = implode( '|', array_merge( self::SHORTCODES_DROP, self::SHORTCODES_UNWRAP ) );
		$result = preg_replace( '#\[/?(?:' . $drop . ')\b[^\]]*\]#', '', $html );

		return null === $result ? $html : $result;
	}

	/**
	 * Remove dead Facebook and Flipkart embeds; turn YouTube embeds into a link on its own line, which
	 * WordPress embeds again.
	 *
	 * @param \DOMDocument $dom The document.
	 */
	private static function embeds( \DOMDocument $dom ): void {
		foreach ( iterator_to_array( $dom->getElementsByTagName( 'iframe' ) ) as $iframe ) {
			$src = (string) $iframe->getAttribute( 'src' );
			if ( 1 === preg_match( '#youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,})#', $src, $match ) ) {
				$p = $dom->createElement( 'p', 'https://www.youtube.com/watch?v=' . $match[1] );
				$iframe->parentNode?->replaceChild( $p, $iframe );
				continue;
			}
			$iframe->parentNode?->removeChild( $iframe );
		}

		foreach ( array( 'script', 'style', 'object', 'embed', 'form', 'noscript' ) as $tag ) {
			foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $node ) {
				$node->parentNode?->removeChild( $node );
			}
		}
	}

	/**
	 * Point old-site images at this site, use the lazy-load plugin's real source, and drop images
	 * hosted elsewhere (they were not downloaded and would break).
	 *
	 * @param \DOMDocument $dom      The document.
	 * @param string       $new_base Base URL of this site.
	 */
	private static function images( \DOMDocument $dom, string $new_base ): void {
		foreach ( iterator_to_array( $dom->getElementsByTagName( 'img' ) ) as $img ) {
			$src = (string) $img->getAttribute( 'data-lazy-src' );
			if ( '' === $src ) {
				$src = (string) $img->getAttribute( 'src' );
			}

			$rewritten = self::rewrite_url( $src, $new_base );
			if ( null === $rewritten ) {
				$img->parentNode?->removeChild( $img );
				continue;
			}

			$img->setAttribute( 'src', $rewritten );
			$img->setAttribute( 'loading', 'lazy' );
		}
	}

	/**
	 * Rewrite links to the old site so they point at this site over https.
	 *
	 * @param \DOMDocument $dom      The document.
	 * @param string       $new_base Base URL of this site.
	 */
	private static function links( \DOMDocument $dom, string $new_base ): void {
		foreach ( iterator_to_array( $dom->getElementsByTagName( 'a' ) ) as $link ) {
			$href      = (string) $link->getAttribute( 'href' );
			$rewritten = self::rewrite_url( $href, $new_base );
			if ( null !== $rewritten ) {
				$link->setAttribute( 'href', $rewritten );
				// Links whose text spells out the old address show the new one instead.
				$text = trim( (string) $link->textContent );
				if ( trim( $href ) === $text ) {
					$link->textContent = $rewritten;
				}
			} elseif ( 1 === preg_match( '#^https?://#i', $href ) ) {
				$link->setAttribute( 'rel', 'nofollow noopener' );
			}
		}
	}

	/**
	 * Rewrite old-site addresses written out as plain text, as in the old "Related Interviews!" lists.
	 *
	 * @param \DOMDocument $dom      The document.
	 * @param string       $new_base Base URL of this site.
	 */
	private static function text_urls( \DOMDocument $dom, string $new_base ): void {
		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//text()[contains(., "internfeel.com")]' );
		if ( false === $nodes ) {
			return;
		}
		foreach ( $nodes as $node ) {
			$node->nodeValue = (string) preg_replace( '#https?://(?:www\.)?internfeel\.com#i', untrailingslashit( $new_base ), (string) $node->nodeValue );
		}
	}

	/**
	 * The post title is the page's only H1, so H1s in the body become H2s.
	 *
	 * @param \DOMDocument $dom The document.
	 */
	private static function headings( \DOMDocument $dom ): void {
		foreach ( iterator_to_array( $dom->getElementsByTagName( 'h1' ) ) as $h1 ) {
			$h2 = $dom->createElement( 'h2' );
			while ( $h1->firstChild ) {
				$h2->appendChild( $h1->firstChild );
			}
			$h1->parentNode?->replaceChild( $h2, $h1 );
		}
	}

	/**
	 * Drop the old editors' inline formatting attributes.
	 *
	 * @param \DOMDocument $dom The document.
	 */
	private static function attributes( \DOMDocument $dom ): void {
		foreach ( iterator_to_array( $dom->getElementsByTagName( '*' ) ) as $element ) {
			if ( ! $element instanceof \DOMElement || 'if-root' === $element->getAttribute( 'id' ) ) {
				continue;
			}
			foreach ( self::DROP_ATTRIBUTES as $attribute ) {
				$element->removeAttribute( $attribute );
			}
			// Every data-* attribute is a leftover of an old plugin (lazy loading, page builders).
			foreach ( iterator_to_array( $element->attributes ) as $attribute ) {
				if ( str_starts_with( $attribute->nodeName, 'data-' ) ) {
					$element->removeAttribute( $attribute->nodeName );
				}
			}
		}
	}

	/**
	 * Replace formatting wrappers with their children.
	 *
	 * @param \DOMDocument $dom The document.
	 */
	private static function unwrap( \DOMDocument $dom ): void {
		foreach ( self::UNWRAP as $tag ) {
			foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $node ) {
				if ( $node instanceof \DOMElement && 'if-root' === $node->getAttribute( 'id' ) ) {
					continue;
				}
				$parent = $node->parentNode;
				if ( null === $parent ) {
					continue;
				}
				while ( $node->firstChild ) {
					$parent->insertBefore( $node->firstChild, $node );
				}
				$parent->removeChild( $node );
			}
		}
	}

	/**
	 * Map an old-site URL onto this site, keeping its path.
	 *
	 * @param string $url      A URL from the old content.
	 * @param string $new_base Base URL of this site, without a trailing slash.
	 * @return string|null The rewritten URL; the URL unchanged when it is relative; null when it is an
	 *                     absolute URL on another host.
	 */
	public static function rewrite_url( string $url, string $new_base ): ?string {
		$url = trim( $url );
		if ( '' === $url || 1 !== preg_match( '#^(https?:)?//#i', $url ) ) {
			return $url;
		}

		$parts = wp_parse_url( 0 === strpos( $url, '//' ) ? 'https:' . $url : $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( ! in_array( $host, self::OLD_HOSTS, true ) ) {
			return null;
		}

		// Some old links have a second address pasted onto the first; keep the first.
		$path  = (string) preg_replace( '#/https?:.*$#i', '/', (string) ( $parts['path'] ?? '/' ) );
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$frag  = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

		return untrailingslashit( $new_base ) . $path . $query . $frag;
	}
}
