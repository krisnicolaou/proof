<?php
/**
 * The SEO Framework plugin
 * Copyright (C) 2015 - 2016 Sybre Waaijer, CyberWire (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 or later as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

defined( 'ABSPATH' ) or die;

/**
 * Class AutoDescription_Query
 *
 * Caches and organizes the WP Query.
 * Functions are (somewhat) in alphabetical order!
 *
 * @since 2.6.0
 *
 * @license GPLv2+ <http://www.gnu.org/licenses/gpl-2.0.html>
 */
class AutoDescription_Query extends AutoDescription_Compat {

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	private function __wakeup() { }

	/**
	 * Handle unapproachable invoked methods.
	 */
	public function __call( $name, $arguments ) {
		parent::__call( $name, $arguments );
	}

	/**
	 * Constructor. Load parent constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Checks whether $wp_query or $current_screen is set.
	 *
	 * @since 2.6.1
	 * @access private
	 * @staticvar bool $cache : Always true if set.
	 *
	 * @global object $wp_query
	 * @global object|null $current_screen
	 *
	 * @return bool True when wp_query or current_screen has been initialized.
	 */
	public function can_cache_query() {

		static $cache = null;

		if ( isset( $cache ) )
			return $cache;

		if ( isset( $GLOBALS['wp_query']->query ) || isset( $GLOBALS['current_screen'] ) )
			return $cache = true;

		return false;
	}

	/**
	 * Get the real page ID, also from CPT, archives, author, blog, etc.
	 *
	 * @since 2.5.0
	 * @staticvar int $id the ID.
	 *
	 * @param bool $use_cache Whether to use the cache or not.
	 * @return int|false The ID.
	 */
	public function get_the_real_ID( $use_cache = true ) {

		if ( $this->is_admin() )
			return $this->get_the_real_admin_ID();

		$can_cache = $this->can_cache_query();
		$use_cache = $can_cache ? $use_cache : false;

		if ( $use_cache ) {
			static $id = null;

			if ( isset( $id ) )
				return $id;
		}

		//* Try to get ID from plugins.
		$id = $can_cache ? $this->check_the_real_ID() : 0;

		if ( empty( $id ) ) {
			//* This catches most ID's. Even Post IDs.
			$id = get_queried_object_id();
		}

		/**
		 * Applies filters 'the_seo_framework_current_object_id' : integer
		 * Can be either the Post ID, or the Term ID.
		 *
		 * @param int $id
		 * @param bool Whether the globals WP_Query or current_screen are set.
		 * @see AutoDescription_Query::can_cache_query()
		 *
		 * @since 2.6.2
		 */
		return $id = (int) apply_filters( 'the_seo_framework_current_object_id', $id, $can_cache );
	}

	/**
	 * Fetches post or term ID within the admin.
	 * Alters while in the loop. Therefore, this can't be cached and must be called within the loop.
	 *
	 * @since 2.7.0
	 */
	public function get_the_real_admin_ID() {

		/**
		 * This is get_the_ID() with WordPress 3.9 compatibility.
		 * @todo convert to get_the_ID()
		 * @priority OMGWTFBBQ 2.7.x (I warned you.)
		 */
		$post = get_post();
		$id = empty( $post ) ? 0 : $post->ID;

		//* Current term ID (outside loop).
		if ( empty( $id ) && $this->is_archive_admin() )
			$id = $this->get_admin_term_id();

		return $id;
	}

	/**
	 * Get the real ID from plugins.
	 *
	 * Only works on front-end as there's no need to check for inconsistent
	 * functions for the current ID in the admin.
	 *
	 * @since 2.5.0
	 *
	 * @return int|empty the ID.
	 */
	public function check_the_real_ID() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		$id = '';

		if ( $this->is_wc_shop() ) {
			//* WooCommerce Shop
			$id = get_option( 'woocommerce_shop_page_id' );
		} elseif ( function_exists( 'get_question_id' ) && did_action( 'template_redirect' ) ) {
			//* AnsPress
			$id = get_question_id();
		}

		/**
		 * Applies filters the_seo_framework_real_id : The Real ID for plugins on front-end.
		 * @since 2.5.0
		 * @TODO add to Filters API.
		 */
		$this->set_query_cache(
			__METHOD__,
			$id = (int) apply_filters( 'the_seo_framework_real_id', $id )
		);

		return $id;
	}

	/**
	 * Fetches the Term ID on admin pages.
	 *
	 * @since 2.6.0
	 * @since 2.6.6 Moved from class AutoDescription_TermData.
	 *
	 * @return int Term ID.
	 */
	public function get_admin_term_id() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		if ( false === $this->is_archive_admin() )
			return 0;

		$term_id = 0;

		/**
		 * is_archive_admin() determines if admin referer checks have run
		 * through global $current_screen. Will output 'Invalid taxonomy' on try.
		 */
		if ( ! empty( $_GET['tag_ID'] ) ) {
			//* WordPress 4.5+
			$term_id = $_GET['tag_ID'];
		} elseif ( ! empty( $_GET['term_id'] ) ) {
			//* Older WordPress versions.
			$term_id = $_GET['term_id'];
		}

		$this->set_query_cache(
			__METHOD__,
			$term_id = intval( $term_id ) ? absint( $term_id ) : 0
		);

		return $term_id;
	}

	/**
	 * Detects 404.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function is_404() {
		return is_404();
	}

	/**
	 * Detects admin screen.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function is_admin() {
		return is_admin();
	}

	/**
	 * Detects attachment page.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $attachment Attachment ID, title, slug, or array of such.
	 * @return bool
	 */
	public function is_attachment( $attachment = '' ) {

		if ( empty( $attachment ) )
			return is_attachment();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $attachment ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_attachment = is_attachment( $attachment ),
			$attachment
		);

		return $is_attachment;
	}

	/**
	 * Detects archive pages. Also in admin.
	 *
	 * @since 2.6.0
	 * @global object $wp_query
	 *
	 * @return bool
	 */
	public function is_archive() {

		if ( $this->is_admin() )
			return $this->is_archive_admin();

		if ( is_archive() && false === $this->is_singular() )
			return true;

		if ( $this->can_cache_query() && false === $this->is_singular() ) {
			global $wp_query;

			if ( $wp_query->is_post_type_archive || $wp_query->is_date || $wp_query->is_author || $wp_query->is_category || $wp_query->is_tag || $wp_query->is_tax )
				return true;
		}

		return false;
	}

	/**
	 * Extends default WordPress is_archive() and determines screen in admin.
	 *
	 * @since 2.6.0
	 * @global object $current_screen
	 *
	 * @return bool Post Type is archive
	 */
	public function is_archive_admin() {
		global $current_screen;

		if ( isset( $current_screen->base ) && ( 'edit-tags' === $current_screen->base || 'term' === $current_screen->base ) )
			return true;

		return false;
	}

	/**
	 * Detects Term edit screen in WP Admin.
	 *
	 * @since 2.6.0
	 * @global object $current_screen
	 *
	 * @return bool We're on Term Edit screen.
	 */
	public function is_term_edit() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		global $current_screen;

		$is_term_edit = false;

		if ( $this->wp_version( '4.4.9999', '>' ) ) {
			if ( isset( $current_screen->base ) && ( 'term' === $current_screen->base ) )
				$is_term_edit = true;
		} else {
			if ( isset( $current_screen->base ) && ( 'edit-tags' === $current_screen->base ) )
				$is_term_edit = true;
		}

		$this->set_query_cache(
			__METHOD__,
			$is_term_edit
		);

		return $is_term_edit;
	}

	/**
	 * Detects Post edit screen in WP Admin.
	 *
	 * @since 2.6.0
	 * @global object $current_screen
	 *
	 * @return bool We're on Post Edit screen.
	 */
	public function is_post_edit() {
		global $current_screen;

		if ( isset( $current_screen->base ) && 'post' === $current_screen->base )
			return true;

		return false;
	}

	/**
	 * Detects Post or Archive Lists in Admin.
	 *
	 * @since 2.6.0
	 * @global object $current_screen
	 * @access private
	 *
	 * @return bool We're on the edit screen.
	 */
	public function is_wp_lists_edit() {
		global $current_screen;

		//* @NOTE WP >= 4.5 & WP < 4.5 conflict.
		if ( isset( $current_screen->base ) && ( 'edit' === $current_screen->base || 'edit-tags' === $current_screen->base ) )
			return true;

		return false;
	}

	/**
	 * Detects author archives.
	 *
	 * @since 2.6.0
	 * @uses $this->is_archive()
	 *
	 * @param mixed $author Optional. User ID, nickname, nicename, or array of User IDs, nicknames, and nicenames
	 * @return bool
	 */
	public function is_author( $author = '' ) {

		if ( empty( $author ) )
			return is_author();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $author ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_author = is_author( $author ),
			$author
		);

		return $is_author;
	}

	/**
	 * Detect the separated blog page.
	 *
	 * @since 2.3.4
	 *
	 * @param int $id the Page ID.
	 * @return bool true if is blog page. Always false if blog page is homepage.
	 */
	public function is_blog_page( $id = 0 ) {

		if ( empty( $id ) )
			$id = $this->get_the_real_ID();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $id ) )
			return $cache;

		$is_blog_page = false;

		$pfp = (int) get_option( 'page_for_posts' );

		if ( $this->has_page_on_front() ) {
			if ( $id === $pfp && false === is_archive() )
				$is_blog_page = true;
			elseif ( is_home() )
				$is_blog_page = true;
		}

		$this->set_query_cache(
			__METHOD__,
			$is_blog_page,
			$id
		);

		return $is_blog_page;
	}

	/**
	 * Detects category archives.
	 *
	 * @since 2.6.0
	 * @uses $this->is_archive()
	 *
	 * @param mixed $category Optional. Category ID, name, slug, or array of Category IDs, names, and slugs.
	 * @return bool
	 */
	public function is_category( $category = '' ) {

		if ( $this->is_admin() )
			return $this->is_category_admin();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $category ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_category = is_category( $category ),
			$category
		);

		return $is_category;
	}

	/**
	 * Extends default WordPress is_category() and determines screen in admin.
	 *
	 * @since 2.6.0
	 * @global object $current_screen
	 *
	 * @return bool Post Type is category
	 */
	public function is_category_admin() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		global $current_screen;

		$is_category = false;

		if ( $this->is_archive_admin() && isset( $current_screen->taxonomy ) ) {

			$tax = $current_screen->taxonomy;
			$len = strlen( $tax );

			if ( $len >= 8 && false !== strrpos( $tax, 'category', -8 ) )
				$is_category = true;
			elseif ( $len >= 3 && false !== strrpos( $tax, 'cat', -3 ) )
				$is_category = true;
		}

		$this->set_query_cache(
			__METHOD__,
			$is_category
		);

		return $is_category;
	}

	/**
	 * Detects date archives.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function is_date() {
		return is_date();
	}

	/**
	 * Detects day archives.
	 *
	 * @since 2.6.0
	 * @uses $this->is_date()
	 *
	 * @return bool
	 */
	public function is_day() {
		return is_day();
	}

	/**
	 * Detects feed.
	 *
	 * @since 2.6.0
	 *
	 * @param string|array $feeds Optional feed types to check.
	 * @return bool
	 */
	public function is_feed( $feeds = '' ) {
		return is_feed( $feeds );
	}

	/**
	 * Detects front page.
	 *
	 * @since 2.6.0
	 *
	 * @param int $id The Page or Post ID.
	 * @return bool
	 */
	public function is_front_page( $id = 0 ) {

		static $cache = array();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $id ) )
			return $cache;

		$is_front_page = false;

		if ( is_front_page() && empty( $id ) )
			$is_front_page = true;

		//* Elegant Themes Support.
		if ( false === $is_front_page && empty( $id ) && $this->is_home() ) {
			$sof = get_option( 'show_on_front' );

			if ( 'page' !== $sof && 'posts' !== $sof )
				$is_front_page = true;
		}

		//* Compare against $id
		if ( false === $is_front_page && $id ) {
			$sof = get_option( 'show_on_front' );

			if ( 'page' === $sof && (int) get_option( 'page_on_front' ) === $id )
				$is_front_page = true;

			if ( 'posts' === $sof && (int) get_option( 'page_for_posts' ) === $id )
				$is_front_page = true;
		}

		$this->set_query_cache(
			__METHOD__,
			$is_front_page,
			$id
		);

		return $is_front_page;
	}

	/**
	 * Detects home page.
	 *
	 * @since 2.6.0
	 * @staticvar bool $cache
	 *
	 * @return bool
	 */
	public function is_home() {
		return is_home();
	}

	/**
	 * Detects month archives.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function is_month() {
		return is_month();
	}

	/**
	 * Detects pages.
	 * When $page is supplied, it will check against the current object. So it will not work in the admin screens.
	 *
	 * @since 2.6.0
	 * @staticvar bool $cache
	 * @uses $this->is_singular()
	 *
	 * @param int|string|array $page Optional. Page ID, title, slug, or array of such. Default empty.
	 * @return bool
	 */
	public function is_page( $page = '' ) {

		if ( $this->is_admin() )
			return $this->is_page_admin();

		if ( empty( $page ) )
			return is_page();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $page ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_page = is_page( $page ),
			$page
		);

		return $is_page;
	}

	/**
	 * Detects pages within the admin area.
	 *
	 * @since 2.6.0
	 * @see $this->is_page()
	 * @global object $current_screen;
	 *
	 * @return bool
	 */
	public function is_page_admin() {
		global $current_screen;

		if ( isset( $current_screen->post_type ) && 'page' === $current_screen->post_type )
			return true;

		return false;
	}

	/**
	 * Detects preview.
	 *
	 * @since 2.6.0
	 * @staticvar bool $cache
	 *
	 * @return bool
	 */
	public function is_preview() {
		return is_preview();
	}

	/**
	 * Detects preview.
	 *
	 * @since 2.6.0
	 * @staticvar bool $cache
	 *
	 * @return bool
	 */
	public function is_search() {
		return is_search();
	}

	/**
	 * Detects posts.
	 * When $post is supplied, it will check against the current object. So it will not work in the admin screens.
	 *
	 * @since 2.6.0
	 * @staticvar bool $cache
	 * @uses AutoDescription_Query::is_single_admin()
	 *
	 * @param int|string|array $post Optional. Post ID, title, slug, or array of such. Default empty.
	 * @return bool
	 */
	public function is_single( $post = '' ) {

		if ( $this->is_admin() )
			return $this->is_single_admin();

		if ( empty( $post ) )
			return is_single();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $post ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_single = is_single( $post ),
			$post
		);

		return $is_single;
	}

	/**
	 * Detects posts within the admin area.
	 *
	 * @since 2.6.0
	 * @global object $current_screen
	 * @see AutoDescription_Query::is_single()
	 *
	 * @return bool
	 */
	public function is_single_admin() {
		global $current_screen;

		if ( isset( $current_screen->post_type ) && 'post' === $current_screen->post_type )
			return true;

		return false;
	}

	/**
	 * Determines if the current page is singular is holds singular items within the admin screen.
	 * Replaces and expands default WordPress is_singular().
	 *
	 * @since 2.5.2
	 * @uses AutoDescription_Query::is_singular_admin()
	 * @uses AutoDescription_Query::is_blog_page()
	 * @uses AutoDescription_Query::is_wc_shop()
	 * @access private
	 *
	 * @param string|array $post_types Optional. Post type or array of post types. Default empty string.
	 * @return bool Post Type is singular
	 */
	public function is_singular( $post_types = '' ) {

		//* WP_Query functions require loop, do alternative check.
		if ( $this->is_admin() )
			return $this->is_singular_admin();

		if ( is_int( $post_types ) ) {
			//* Cache ID. Core is_singular() doesn't accept integers.
			$id = $post_types;
			$post_types = '';
		}

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $post_types ) )
			return $cache;

		if ( ! $is_singular = is_singular( $post_types ) ) {
			$id = isset( $id ) ? $id : $this->get_the_real_ID();

			//* Check for somewhat singulars. We need this to adjust Meta data filled in Posts.
			if ( $this->is_blog_page( $id ) || $this->is_wc_shop() )
				$is_singular = true;
		}

		$this->set_query_cache(
			__METHOD__,
			$is_singular,
			$post_types
		);

		return $is_singular;
	}

	/**
	 * Determines if the page is singular within the admin screen.
	 *
	 * @since 2.5.2
	 * @global object $current_screen
	 *
	 * @return bool Post Type is singular
	 */
	public function is_singular_admin() {
		global $current_screen;

		if ( isset( $current_screen->base ) && ( 'edit' === $current_screen->base || 'post' === $current_screen->base ) )
			return true;

		return false;
	}

	/**
	 * Detects the static front page.
	 *
	 * @since 2.3.8
	 *
	 * @param int $id the Page ID to check. If empty, the current ID will be fetched.
	 * @return bool true if is blog page. Always false if the homepage is a blog.
	 */
	public function is_static_frontpage( $id = '' ) {

		if ( empty( $id ) )
			$id = $this->get_the_real_ID();

		if ( 'page' === get_option( 'show_on_front' ) )
			return (int) get_option( 'page_on_front' ) === $id;

		return false;
	}

	/**
	 * Detects tag archives.
	 *
	 * @staticvar bool $cache
	 * @since 2.6.0
	 * @uses $this->is_archive()
	 *
	 * @param mixed $tag Optional. Tag ID, name, slug, or array of Tag IDs, names, and slugs.
	 * @return bool
	 */
	public function is_tag( $tag = '' ) {

		//* Admin requires another check.
		if ( $this->is_admin() )
			return $this->is_tag_admin();

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $tag ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_tag = is_tag( $tag ),
			$tag
		);

		return $is_tag;
	}

	/**
	 * Determines if the page is a tag within the admin screen.
	 *
	 * @since 2.6.0
	 * @global object $current_screen
	 *
	 * @return bool Post Type is category.
	 */
	public function is_tag_admin() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		$is_tag = false;

		if ( $this->is_archive_admin() ) {
			global $current_screen;

			if ( isset( $current_screen->taxonomy ) && strlen( $current_screen->taxonomy ) >= 3 && false !== strrpos( $current_screen->taxonomy, 'tag', -3 ) )
				$is_tag = true;
		}

		$this->set_query_cache(
			__METHOD__,
			$is_tag
		);

		return $is_tag;
	}

	/**
	 * Detects taxonomy archives.
	 *
	 * @since 2.6.0
	 *
	 * @param string|array     $taxonomy Optional. Taxonomy slug or slugs.
	 * @param int|string|array $term     Optional. Term ID, name, slug or array of Term IDs, names, and slugs.
	 * @return bool
	 */
	public function is_tax( $taxonomy = '', $term = '' ) {

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $taxonomy, $term ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_tax = is_tax( $taxonomy, $term ),
			$taxonomy, $term
		);

		return $is_tax;
	}

	/**
	 * Determines if the page is a Ulimate Member's plugin User page.
	 * Checks for function availability: um_user, um_is_core_page, um_get_requested_user
	 *
	 * @since 2.5.2
	 * @uses $this->can_i_use()
	 *
	 * @return bool Whether we're on a Ultimate Member page.
	 */
	public function is_ultimate_member_user_page() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_um_user_page = $this->can_i_use( array( 'functions' => array( 'um_user', 'um_is_core_page', 'um_get_requested_user' ) ), false )
		);

		return $is_um_user_page;
	}

	/**
	 * Determines if the page is the WooCommerce plugin Shop page.
	 *
	 * @since 2.5.2
	 * @staticvar bool $cache
	 *
	 * @return bool True if on the WooCommerce shop page.
	 */
	public function is_wc_shop() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_shop = false === $this->is_admin() && function_exists( 'is_shop' ) && is_shop()
		);

		return $is_shop;
	}

	/**
	 * Determines if the page is the WooCommerce plugin Product page.
	 *
	 * @since 2.5.2
	 *
	 * @return bool True if on a WooCommerce Product page.
	 */
	public function is_wc_product() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		$this->set_query_cache(
			__METHOD__,
			$is_product = false === $this->is_admin() && function_exists( 'is_product' ) && is_product()
		);

		return $is_product;
	}

	/**
	 * Detects year archives.
	 *
	 * @since 2.6.0
	 *
	 * @return bool
	 */
	public function is_year() {
		return is_year();
	}

	/**
	 * Determines whether we're on the SEO settings page.
	 * WARNING: Do not ever use this as a safety check.
	 *
	 * @since 2.6.0
	 * @since 2.7.0 Added secure parameter.
	 *
	 * @param bool $secure Whether to ignore the use of the second (insecure) parameter.
	 * @return bool
	 */
	public function is_seo_settings_page( $secure = true ) {

		if ( null !== $cache = $this->get_query_cache( __METHOD__, null, $secure ) )
			return $cache;

		if ( $secure ) {
			$page = $this->is_menu_page( $this->seo_settings_page_hook );
		} else {
			$page = $this->is_menu_page( $this->seo_settings_page_hook, $this->seo_settings_page_slug );
		}

		$this->set_query_cache(
			__METHOD__,
			$page,
			$secure
		);

		return $page;
	}

	/**
	 * Checks the screen base file through global $page_hook or $_GET.
	 *
	 * @since 2.2.2
	 * @since 2.7.0 Added pageslug parameter.
	 * @global string $page_hook the current page hook.
	 * @note Usage of $pageslug might be insecure. Check all variables!
	 *
	 * @param string $pagehook The menu pagehook to compare to.
	 * @param string $pageslug The menu page slug to compare to.
	 * @return bool true if screen match.
	 */
	public function is_menu_page( $pagehook = '', $pageslug = '' ) {
		global $page_hook;

		if ( isset( $page_hook ) ) {
			if ( $page_hook === $pagehook )
				return true;
		} elseif ( $this->is_admin() && $pageslug ) {
			if ( isset( $_GET['page'] ) && $pageslug === $_GET['page'] )
				return true;
		}

		return false;
	}

	/**
	 * Fetches the amount of pages on the screen.
	 * Fetches global $page through Query Var to prevent conflicts.
	 *
	 * @since 2.6.0
	 *
	 * @return int $page Always a positive number.
	 */
	public function page() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		$page = $this->is_multipage() ? get_query_var( 'page' ) : 1;

		$this->set_query_cache(
			__METHOD__,
			$page = $page ? (int) $page : 1
		);

		return $page;
	}

	/**
	 * Determines whether the current loop is a multipage.
	 *
	 * @since 2.7.0
	 * @global int $pages Used as reference.
	 *
	 * @return bool True if multipage.
	 */
	protected function is_multipage() {
		global $pages;

		$_pages = $pages;

		$post = $this->is_singular() || $this->is_front_page() ? get_post( $this->get_the_real_ID() ) : null;

		if ( is_object( $post ) ) {
			$content = $post->post_content;
			if ( false !== strpos( $content, '<!--nextpage-->' ) ) {
				$content = str_replace( "\n<!--nextpage-->", '<!--nextpage-->', $content );

				// Ignore nextpage at the beginning of the content.
				if ( 0 === strpos( $content, '<!--nextpage-->' ) )
					$content = substr( $content, 15 );

				$_pages = explode( '<!--nextpage-->', $content );
			} else {
				$_pages = array( $post->post_content );
			}
		} else {
			return false;
		}

		/**
		 * Filter the "pages" derived from splitting the post content.
		 *
		 * "Pages" are determined by splitting the post content based on the presence
		 * of `<!-- nextpage -->` tags.
		 *
		 * @since 4.4.0 WordPress core
		 *
		 * @param array $_pages Array of "pages" derived from the post content.
		 *              of `<!-- nextpage -->` tags..
		 * @param WP_Post $post  Current post object.
		 */
		$_pages = apply_filters( 'content_pagination', $_pages, $post );

		$numpages = count( $_pages );

		if ( $numpages > 1 ) {
			$multipage = true;
		} else {
			$multipage = false;
		}

		return $multipage;
	}

	/**
	 * Fetches the number of the current page.
	 * Fetches global $paged through Query Var to prevent conflicts.
	 *
	 * @since 2.6.0
	 *
	 * @return int $paged
	 */
	public function paged() {

		if ( null !== $cache = $this->get_query_cache( __METHOD__ ) )
			return $cache;

		$paged = get_query_var( 'paged' );

		$this->set_query_cache(
			__METHOD__,
			$paged = $paged ? (int) $paged : 1
		);

		return $paged;
	}

	/**
	 * Handles object cache for the query class.
	 *
	 * @since 2.7.0
	 * @staticvar bool $can_cache_query : True when this function can run.
	 * @staticvar mixed $cache : The cached query.
	 * @see AutoDescritpion_Query::set_query_cache(); to set query cache.
	 *
	 * @param string $key The key to set or get.
	 * @param mixed $value_to_set The value to set.
	 * @param array|mixed $hash Extra arguments, that will be used to generate an alternative cache key.
	 *        Must always be inside a single array when $value_to_set is set. @see AutoDescritpion_Query::set_query_cache()
	 *        Must always be separated parameters otherwise.
	 * @return mixed : {
	 * 		mixed The cached value if set and $value_to_set is null.
	 *		null If the query can't be cached yet, or when no value has been set.
	 *		If $value_to_set is set : {
	 *			true If the value is being set for the first time.
	 *			false If the value has been set and $value_to_set is being overwritten.
	 * 		}
	 * }
	 */
	public function get_query_cache( $key, $value_to_set = null ) {

		static $can_cache_query = null;

		if ( is_null( $can_cache_query ) ) {
			if ( $this->can_cache_query() ) {
				$can_cache_query = true;
			} else {
				return null;
			}
		}

		static $cache = array();

		if ( func_num_args() > 2 ) {
			$hash = isset( $value_to_set ) ? serialize( (array) func_get_arg( 2 ) ) : serialize( array_slice( func_get_args(), 2 ) );
		} else {
			$hash = false;
		}

		if ( isset( $value_to_set ) ) {
			if ( isset( $cache[ $key ][ $hash ] ) ) {
				$cache[ $key ][ $hash ] = $value_to_set;
				return false;
			}
			$cache[ $key ][ $hash ] = $value_to_set;
			return true;
		} else {
			if ( isset( $cache[ $key ][ $hash ] ) )
				return $cache[ $key ][ $hash ];
		}

		return null;
	}

	/**
	 * Object cache handler for the query class.
	 *
	 * @since 2.7.0
	 * @see AutoDescription_Query::get_query_cache()
	 *
	 * @param string $key The key to set.
	 * @param mixed $value_to_set If null, no cache will be set.
	 * @param mixed $hash Extra arguments, that will be used to generate an alternative cache key.
	 * @return bool : {
	 *		true If the value is being set for the first time.
	 *		false If the value has been set and $value_to_set is being overwritten.
	 * }
	 */
	public function set_query_cache( $key, $value_to_set ) {
		if ( func_num_args() > 2 ) {
			return $this->get_query_cache( $key, $value_to_set, array_slice( func_get_args(), 2 ) );
		} else {
			return $this->get_query_cache( $key, $value_to_set );
		}
	}
}
