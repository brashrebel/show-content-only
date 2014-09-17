<?php
/*
Plugin Name: Show Content Only
Plugin URI: http://www.seodenver.com/content-only/
Description: Display only the post's content, without a theme, scripts or stylesheets.
Author: Katz Web Services, Inc.
Author URI: http://www.katzwebservices.com
Version: 1.3
Text Domain: show-content-only
*/

/*  Copyright 2011  Katz Web Services, Inc.  (email : info@katzwebdesign.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class ShowContentOnly {

	function __construct() {
		add_action( 'init', array( $this, 'languages' ) );
		add_action( 'wp', array( $this, 'keyword' ) );
		add_action( 'admin_menu', array( $this, 'meta_box' ) );
	}

	function languages() {
		load_plugin_textdomain( 'show-content-only', false, untrailingslashit( basename( dirname( __FILE__ ) ) ) . '/languages' );
	}

	function keyword() {
		global $post;

		if ( isset( $_GET['content-only'] ) && is_singular() ) {
			the_post();
			echo '<html><head><meta name="robots" value="noindex,nofollow" />';
			if ( ! empty( $_GET['js'] ) ) {
				wp_print_scripts();
			}
			if ( ! empty( $_GET['css'] ) ) {
				wp_head();
				wp_print_styles();
				if ( function_exists( 'post_class' ) ) {
					echo '</head><body ';
					post_class();
					echo '>';
				} else {
					echo '</head><body>';
				}
			} else {

			}

			if ( isset( $_GET['plain'] ) ) {
				echo $this->get_the_content();
			} else {
				$this->the_content();
			}

			if ( isset( $_GET['tags'] ) ) {
				the_tags( '<ul><li>', '</li><li>', '</li></ul>' );
			}

			if ( isset( $_GET['categories'] ) ) {
				the_category();
			}
			echo '</body></html>';
			die();
		}
	}

	function meta_box() {
		$args = array(
			'public' => true
		);
		$types = get_post_types( $args );
		foreach ( $types as $type ) {
		add_meta_box( 'contentonly', __( 'Show Content Only Links', 'show-content-only' ), array(
				$this,
				'links'
			), $type, 'side', 'high' );
		}
	}

	// Meta box content
	function links() {
		global $post;
		if ( isset( $post->ID ) && $post->ID != 0 ) {
			$p     = $post->post_type == 'page' ? 'page_id' : 'p';
			$links = array(
				'Content only'               => array(
					$p             => $post->ID,
					'content-only' => true
				),
				'Content + Styles'           => array(
					$p             => $post->ID,
					'content-only' => true,
					'css'          => true
				),
				'Content + Tags'             => array(
					$p             => $post->ID,
					'content-only' => true,
					'tags'         => true
				),
				'Content + Categories'       => array(
					$p             => $post->ID,
					'content-only' => true,
					'categories'   => true
				),
				'Content, Tags & Categories' => array(
					$p             => $post->ID,
					'content-only' => true,
					'categories'   => true,
					'tags'         => true
				),
			);
			if ( $links ) {
				echo '<ul>';
				foreach ( $links as $name => $link ) {
					$link = htmlentities( add_query_arg( $link, get_option( 'home' ) . '/' ) );
					echo '<li><a href="' . $link . '" class="button button-small">' . $name . '</a></li>';
				}
				echo '</ul>';
			}
		} else {
			echo '<p>' . __( 'You must publish or save this post before Show Content Only links become available.', 'show-content-only' ) . '</p>';
		}
	}

	/**
	 * Display the post content.
	 *
	 * @since 0.71
	 *
	 * @param string $more_link_text Optional. Content for when there is more text.
	 * @param string $stripteaser Optional. Teaser content before the more text.
	 * @param string $more_file Optional. Not used.
	 */
	function the_content( $more_link_text = null, $stripteaser = 0, $more_file = '' ) {
		$content = $this->get_the_content( $more_link_text, $stripteaser, $more_file );
		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		echo $content;
	}

	/**
	 * Retrieve the post content.
	 *
	 * @since 0.71
	 *
	 * @param string $more_link_text Optional. Content for when there is more text.
	 * @param string $stripteaser Optional. Teaser content before the more text.
	 * @param string $more_file Optional. Not used.
	 *
	 * @return string
	 */
	function get_the_content( $more_link_text = null, $stripteaser = 0, $more_file = '' ) {
		global $id, $post, $more, $page, $pages, $multipage, $preview, $pagenow;

		if ( null === $more_link_text ) {
			$more_link_text = __( '(more...)' );
		}

		$output    = '';
		$hasTeaser = false;

		// If post password required and it doesn't match the cookie.
		if ( post_password_required( $post ) ) {
			$output = get_the_password_form();

			return $output;
		}

		if ( $more_file != '' ) {
			$file = $more_file;
		} else {
			$file = $pagenow;
		} //$_SERVER['PHP_SELF'];

		if ( $page > count( $pages ) ) // if the requested page doesn't exist
		{
			$page = count( $pages );
		} // give them the highest numbered page that DOES exist

		$content = $pages[ $page - 1 ];
		if ( preg_match( '/<!--more(.*?)?-->/', $content, $matches ) ) {
			$content = explode( $matches[0], $content, 2 );
			if ( ! empty( $matches[1] ) && ! empty( $more_link_text ) ) {
				$more_link_text = strip_tags( wp_kses_no_null( trim( $matches[1] ) ) );
			}

			$hasTeaser = true;
		} else {
			$content = array( $content );
		}
		if ( ( false !== strpos( $post->post_content, '<!--noteaser-->' ) && ( ( ! $multipage ) || ( $page == 1 ) ) ) ) {
			$stripteaser = 1;
		}
		$teaser = $content[0];
		if ( ( $more ) && ( $stripteaser ) && ( $hasTeaser ) ) {
			$teaser = '';
		}
		$output .= $teaser;
		if ( count( $content ) > 1 ) {
			if ( $more ) {
				$output .= '<span id="more-' . $id . '"></span>' . $content[1];
			} else {
				if ( ! empty( $more_link_text ) ) {
					$output .= apply_filters( 'the_content_more_link', ' <a href="' . get_permalink() . "#more-$id\" class=\"more-link\" rel=\"nofollow\">$more_link_text</a>", $more_link_text );
				}
				$output = force_balance_tags( $output );
			}

		}
		if ( $preview ) // preview fix for javascript bug with foreign languages
		{
			$output = preg_replace_callback( '/\%u([0-9A-F]{4})/', create_function( '$match', 'return "&#" . base_convert($match[1], 16, 10) . ";";' ), $output );
		}

		$output = do_shortcode( $output );

		return $output;
	}
}

$showcontentonly = new ShowContentOnly();