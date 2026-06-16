<?php
/**
 * MCP tool definitions and handlers.
 *
 * Every tool returns either an associative array (serialised back to the
 * client) or a WP_Error on failure.
 *
 * @package WPMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The toolbox Claude can call.
 */
class WPMCP_Tools {

	/**
	 * Tool schema definitions exposed via tools/list.
	 *
	 * @return array
	 */
	public function definitions() {
		return array(
			array(
				'name'        => 'list_post_types',
				'description' => 'List all registered public post types (e.g. page, post, and custom post types) with their labels and REST support. Call this first to discover what content exists.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new stdClass(),
				),
			),
			array(
				'name'        => 'list_content',
				'description' => 'List or browse pages/posts of a given post type. Supports keyword search, status filtering and pagination. Returns IDs, titles, slugs, status and URLs — use get_content for the full body and ACF fields.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Post type slug (default "page"). Use list_post_types to discover options.',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Optional keyword to search titles and content.',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'Post status: publish, draft, pending, private, future, any. Default "any".',
						),
						'per_page'  => array(
							'type'        => 'integer',
							'description' => 'Results per page (1-100, default 20).',
						),
						'page'      => array(
							'type'        => 'integer',
							'description' => 'Page number for pagination (default 1).',
						),
					),
				),
			),
			array(
				'name'        => 'search_content',
				'description' => 'Search across all post types for a keyword in titles and content. Quick way to find which page contains a piece of text.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array(
							'type'        => 'string',
							'description' => 'Search term.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Max results (default 20).',
						),
					),
					'required'   => array( 'query' ),
				),
			),
			array(
				'name'        => 'get_content',
				'description' => 'Get the full details of a single page/post by ID: title, slug, status, raw content, excerpt, all ACF fields, featured image, parent and template. Always call this before updating so you can see current values.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'The post/page ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'update_content',
				'description' => 'Update core fields of a single page/post: title, content (post_body HTML), excerpt, slug, status, parent or page template. Only the fields you pass are changed; others are left untouched.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array(
							'type'        => 'integer',
							'description' => 'The post/page ID to update.',
						),
						'title'    => array(
							'type'        => 'string',
							'description' => 'New title.',
						),
						'content'  => array(
							'type'        => 'string',
							'description' => 'New post body (HTML / block markup).',
						),
						'excerpt'  => array(
							'type'        => 'string',
							'description' => 'New excerpt.',
						),
						'slug'     => array(
							'type'        => 'string',
							'description' => 'New URL slug.',
						),
						'status'   => array(
							'type'        => 'string',
							'description' => 'New status: publish, draft, pending, private.',
						),
						'parent'   => array(
							'type'        => 'integer',
							'description' => 'New parent page ID (0 for top-level).',
						),
						'template' => array(
							'type'        => 'string',
							'description' => 'Page template file name.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'get_acf_fields',
				'description' => 'Get all ACF (Advanced Custom Fields) values for a single post/page, keyed by field name. Returns an empty object if ACF is not installed.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'The post/page ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'update_acf_fields',
				'description' => 'Update one or more ACF fields on a single post/page. Pass a "fields" object mapping field name (or field key like field_abc123) to the new value. Supports text, textarea, wysiwyg, number, select, true/false and repeater/group values (as nested arrays).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array(
							'type'        => 'integer',
							'description' => 'The post/page ID.',
						),
						'fields' => array(
							'type'                 => 'object',
							'description'          => 'Map of ACF field name => new value.',
							'additionalProperties' => true,
						),
					),
					'required'   => array( 'id', 'fields' ),
				),
			),
			array(
				'name'        => 'bulk_update_content',
				'description' => 'Update multiple pages/posts in one call. Pass an "items" array; each item has an "id" plus any of: title, content, excerpt, slug, status, and an "acf" object of ACF field updates. Returns a per-item success report.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'items' => array(
							'type'        => 'array',
							'description' => 'Array of update objects, each with an "id" and the fields to change.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'      => array( 'type' => 'integer' ),
									'title'   => array( 'type' => 'string' ),
									'content' => array( 'type' => 'string' ),
									'excerpt' => array( 'type' => 'string' ),
									'slug'    => array( 'type' => 'string' ),
									'status'  => array( 'type' => 'string' ),
									'acf'     => array( 'type' => 'object' ),
								),
								'required'   => array( 'id' ),
							),
						),
					),
					'required'   => array( 'items' ),
				),
			),
			array(
				'name'        => 'search_replace_content',
				'description' => 'Find and replace a text string inside the post body of pages/posts. Set dry_run=true (default) to preview which posts would change before committing. Use a specific post_type to limit scope.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'    => array(
							'type'        => 'string',
							'description' => 'Text to find.',
						),
						'replace'   => array(
							'type'        => 'string',
							'description' => 'Replacement text.',
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Limit to a post type (default "page").',
						),
						'dry_run'   => array(
							'type'        => 'boolean',
							'description' => 'If true (default), only report matches without saving.',
						),
					),
					'required'   => array( 'search', 'replace' ),
				),
			),
			array(
				'name'        => 'create_content',
				'description' => 'Create a new page/post. Supports title, content, excerpt, status, post_type, parent and an "acf" object of initial ACF field values.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string' ),
						'excerpt'   => array( 'type' => 'string' ),
						'status'    => array(
							'type'        => 'string',
							'description' => 'publish, draft (default), pending, private.',
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Default "page".',
						),
						'parent'    => array( 'type' => 'integer' ),
						'acf'       => array( 'type' => 'object' ),
					),
					'required'   => array( 'title' ),
				),
			),
			array(
				'name'        => 'delete_content',
				'description' => 'Move a page/post to trash (or permanently delete with force=true). Reversible unless forced.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'force' => array(
							'type'        => 'boolean',
							'description' => 'Permanently delete instead of trashing (default false).',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'list_acf_field_groups',
				'description' => 'List ACF field groups and their fields (name, key, type, label), optionally filtered by post type. Use this to discover which ACF field names you can update.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Optionally limit to groups attached to this post type.',
						),
					),
				),
			),
			array(
				'name'        => 'list_media',
				'description' => 'Browse the media library. Returns id, title, URL, mime type and alt text. Supports keyword search and pagination.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'upload_media',
				'description' => 'Upload an image/file to the media library from a public URL or base64 data. Optionally set alt text, title and caption. Returns the new attachment id and URL.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'url'       => array(
							'type'        => 'string',
							'description' => 'Public URL to download the file from.',
						),
						'base64'    => array(
							'type'        => 'string',
							'description' => 'Base64-encoded file contents (alternative to url).',
						),
						'filename'  => array(
							'type'        => 'string',
							'description' => 'Filename to use (required when using base64).',
						),
						'alt'       => array( 'type' => 'string' ),
						'title'     => array( 'type' => 'string' ),
						'caption'   => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'update_media',
				'description' => 'Update metadata of an existing media attachment: alt text, title, caption and description.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer' ),
						'alt'         => array( 'type' => 'string' ),
						'title'       => array( 'type' => 'string' ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'set_featured_image',
				'description' => 'Set (or clear) the featured image / thumbnail of a page or post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array(
							'type'        => 'integer',
							'description' => 'The page/post id.',
						),
						'attachment_id' => array(
							'type'        => 'integer',
							'description' => 'Media attachment id to use, or 0 to remove the featured image.',
						),
					),
					'required'   => array( 'id', 'attachment_id' ),
				),
			),
			array(
				'name'        => 'list_taxonomies',
				'description' => 'List registered taxonomies (categories, tags, custom) and which post types they apply to.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new stdClass(),
				),
			),
			array(
				'name'        => 'get_terms',
				'description' => 'List terms in a taxonomy (e.g. all categories or tags), with id, name, slug and count.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy' => array(
							'type'        => 'string',
							'description' => 'Taxonomy slug, e.g. category, post_tag.',
						),
						'search'   => array( 'type' => 'string' ),
					),
					'required'   => array( 'taxonomy' ),
				),
			),
			array(
				'name'        => 'set_post_terms',
				'description' => 'Assign terms (categories, tags or custom taxonomy terms) to a post. Provide term ids or names; set append=true to add without removing existing terms.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'taxonomy' => array( 'type' => 'string' ),
						'terms'    => array(
							'type'        => 'array',
							'description' => 'Term ids (integers) or names (strings). Names are created if missing.',
							'items'       => array( 'type' => array( 'string', 'integer' ) ),
						),
						'append'   => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id', 'taxonomy', 'terms' ),
				),
			),
			array(
				'name'        => 'list_revisions',
				'description' => 'List saved revisions of a page/post so you can roll back. Returns revision id, author and timestamp.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'restore_revision',
				'description' => 'Restore a page/post to a previous revision by revision id (get ids from list_revisions).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'revision_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'revision_id' ),
				),
			),
			array(
				'name'        => 'get_seo_meta',
				'description' => 'Read SEO metadata (title, description, focus keyword, canonical, robots) for a page/post. Auto-detects Yoast SEO or Rank Math.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'update_seo_meta',
				'description' => 'Update SEO metadata for a page/post. Auto-detects Yoast SEO or Rank Math. Pass any of: seo_title, seo_description, focus_keyword, canonical.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'              => array( 'type' => 'integer' ),
						'seo_title'       => array( 'type' => 'string' ),
						'seo_description' => array( 'type' => 'string' ),
						'focus_keyword'   => array( 'type' => 'string' ),
						'canonical'       => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'get_post_meta',
				'description' => 'Read raw custom field (post meta) values for a page/post. Works with any field plugin (MetaBox, Pods, JetEngine, CPT UI) and core meta. Omit "key" to return all non-protected meta.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'  => array( 'type' => 'integer' ),
						'key' => array(
							'type'        => 'string',
							'description' => 'Optional single meta key to read.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'update_post_meta',
				'description' => 'Update raw custom field (post meta) values. Pass a "meta" object mapping meta_key => value. Use for non-ACF custom fields (MetaBox, Pods, JetEngine, etc.).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'meta' => array(
							'type'                 => 'object',
							'description'          => 'Map of meta_key => value.',
							'additionalProperties' => true,
						),
					),
					'required'   => array( 'id', 'meta' ),
				),
			),
			array(
				'name'        => 'get_site_info',
				'description' => 'Get general information about the WordPress site: name, tagline, URL, WP version, active theme, language, timezone, and whether ACF / Yoast / Rank Math / WooCommerce are active.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new stdClass(),
				),
			),
			array(
				'name'        => 'list_redirects',
				'description' => 'List all 301/302 redirects managed by this plugin (used for content consolidation).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new stdClass(),
				),
			),
			array(
				'name'        => 'add_redirect',
				'description' => 'Add or update a redirect. Point an old/merged URL path at its surviving canonical page. Provide "from" (a path like /old-slug/) and "to" (a path or full URL). Or pass a "redirects" array to add many at once. Default code is 301 (permanent).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'from'      => array(
							'type'        => 'string',
							'description' => 'Source path to redirect from, e.g. /sassa-status-check-sms/',
						),
						'to'        => array(
							'type'        => 'string',
							'description' => 'Destination path or full URL.',
						),
						'code'      => array(
							'type'        => 'integer',
							'description' => 'HTTP status: 301 (default), 302, 307 or 308.',
						),
						'redirects' => array(
							'type'        => 'array',
							'description' => 'Optional bulk list; each item has from, to and optional code.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'from' => array( 'type' => 'string' ),
									'to'   => array( 'type' => 'string' ),
									'code' => array( 'type' => 'integer' ),
								),
							),
						),
					),
				),
			),
			array(
				'name'        => 'delete_redirect',
				'description' => 'Delete a redirect by its "from" path.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'from' => array( 'type' => 'string' ),
					),
					'required'   => array( 'from' ),
				),
			),
		);
	}

	/**
	 * Classify a tool as 'read', 'write' or 'delete' for the safety toggles.
	 *
	 * @param string $name Tool name.
	 * @return string
	 */
	public function category( $name ) {
		$write = array(
			'update_content',
			'update_acf_fields',
			'bulk_update_content',
			'search_replace_content',
			'create_content',
			'upload_media',
			'update_media',
			'set_featured_image',
			'set_post_terms',
			'restore_revision',
			'update_seo_meta',
			'update_post_meta',
			'add_redirect',
		);
		$delete = array( 'delete_content', 'delete_redirect' );

		if ( in_array( $name, $delete, true ) ) {
			return 'delete';
		}
		if ( in_array( $name, $write, true ) ) {
			return 'write';
		}
		return 'read';
	}

	/**
	 * Dispatch a named tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments.
	 * @return array|WP_Error
	 */
	public function call( $name, $args ) {
		switch ( $name ) {
			case 'list_post_types':
				return $this->list_post_types();
			case 'list_content':
				return $this->list_content( $args );
			case 'search_content':
				return $this->search_content( $args );
			case 'get_content':
				return $this->get_content( $args );
			case 'update_content':
				return $this->update_content( $args );
			case 'get_acf_fields':
				return $this->get_acf_fields( $args );
			case 'update_acf_fields':
				return $this->update_acf_fields( $args );
			case 'bulk_update_content':
				return $this->bulk_update_content( $args );
			case 'search_replace_content':
				return $this->search_replace_content( $args );
			case 'create_content':
				return $this->create_content( $args );
			case 'delete_content':
				return $this->delete_content( $args );
			case 'list_acf_field_groups':
				return $this->list_acf_field_groups( $args );
			case 'list_media':
				return $this->list_media( $args );
			case 'upload_media':
				return $this->upload_media( $args );
			case 'update_media':
				return $this->update_media( $args );
			case 'set_featured_image':
				return $this->set_featured_image( $args );
			case 'list_taxonomies':
				return $this->list_taxonomies( $args );
			case 'get_terms':
				return $this->get_terms_tool( $args );
			case 'set_post_terms':
				return $this->set_post_terms( $args );
			case 'list_revisions':
				return $this->list_revisions( $args );
			case 'restore_revision':
				return $this->restore_revision( $args );
			case 'get_seo_meta':
				return $this->get_seo_meta( $args );
			case 'update_seo_meta':
				return $this->update_seo_meta( $args );
			case 'get_post_meta':
				return $this->get_post_meta_tool( $args );
			case 'update_post_meta':
				return $this->update_post_meta_tool( $args );
			case 'get_site_info':
				return $this->get_site_info();
			case 'list_redirects':
				return array( 'redirects' => WPMCP_Redirects::all() );
			case 'add_redirect':
				return $this->add_redirect( $args );
			case 'delete_redirect':
				if ( empty( $args['from'] ) ) {
					return new WP_Error( 'wpmcp_missing_from', 'A "from" path is required.' );
				}
				return array(
					'success' => WPMCP_Redirects::delete( $args['from'] ),
					'from'    => $args['from'],
				);
			default:
				return new WP_Error( 'wpmcp_unknown_tool', 'Unknown tool: ' . $name );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Tool implementations                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * List public post types.
	 *
	 * @return array
	 */
	private function list_post_types() {
		$types  = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $types as $type ) {
			$result[] = array(
				'slug'         => $type->name,
				'label'        => $type->label,
				'singular'     => isset( $type->labels->singular_name ) ? $type->labels->singular_name : $type->label,
				'hierarchical' => (bool) $type->hierarchical,
				'rest_base'    => $type->rest_base ? $type->rest_base : $type->name,
			);
		}
		return array( 'post_types' => $result );
	}

	/**
	 * List content of a post type.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function list_content( $args ) {
		$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'page';
		$per_page  = isset( $args['per_page'] ) ? min( 100, max( 1, (int) $args['per_page'] ) ) : 20;
		$page      = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$status    = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		$query = new WP_Query( $query_args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->summarize_post( $post );
		}

		return array(
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'items'       => $items,
		);
	}

	/**
	 * Cross-post-type search.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function search_content( $args ) {
		if ( empty( $args['query'] ) ) {
			return new WP_Error( 'wpmcp_missing_query', 'A "query" string is required.' );
		}
		$per_page = isset( $args['per_page'] ) ? min( 100, max( 1, (int) $args['per_page'] ) ) : 20;

		$query = new WP_Query(
			array(
				's'              => sanitize_text_field( $args['query'] ),
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => $per_page,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->summarize_post( $post );
		}

		return array(
			'total' => (int) $query->found_posts,
			'items' => $items,
		);
	}

	/**
	 * Full details for one post.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function get_content( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$data                   = $this->summarize_post( $post );
		$data['content']        = $post->post_content;
		$data['excerpt']        = $post->post_excerpt;
		$data['parent']         = (int) $post->post_parent;
		$data['template']       = get_page_template_slug( $post->ID );
		$data['featured_image'] = get_the_post_thumbnail_url( $post->ID, 'full' );
		$data['acf']            = $this->read_acf( $post->ID );

		return $data;
	}

	/**
	 * Update core post fields.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function update_content( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$update  = array( 'ID' => $post->ID );
		$changed = array();

		if ( isset( $args['title'] ) ) {
			$update['post_title'] = wp_kses_post( $args['title'] );
			$changed[]            = 'title';
		}
		if ( isset( $args['content'] ) ) {
			$update['post_content'] = $this->prepare_content( $args['content'] );
			$changed[]              = 'content';
		}
		if ( isset( $args['excerpt'] ) ) {
			$update['post_excerpt'] = wp_kses_post( $args['excerpt'] );
			$changed[]              = 'excerpt';
		}
		if ( isset( $args['slug'] ) ) {
			$update['post_name'] = sanitize_title( $args['slug'] );
			$changed[]           = 'slug';
		}
		if ( isset( $args['status'] ) ) {
			$update['post_status'] = sanitize_key( $args['status'] );
			$changed[]             = 'status';
		}
		if ( isset( $args['parent'] ) ) {
			$update['post_parent'] = (int) $args['parent'];
			$changed[]             = 'parent';
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $args['template'] ) ) {
			update_post_meta( $post->ID, '_wp_page_template', sanitize_text_field( $args['template'] ) );
			$changed[] = 'template';
		}

		return array(
			'success'       => true,
			'id'            => $post->ID,
			'changed'       => $changed,
			'edit_link'     => get_edit_post_link( $post->ID, 'raw' ),
			'permalink'     => get_permalink( $post->ID ),
			'updated_state' => $this->summarize_post( get_post( $post->ID ) ),
		);
	}

	/**
	 * Read ACF fields.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function get_acf_fields( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! function_exists( 'get_fields' ) ) {
			return array(
				'acf_active' => false,
				'fields'     => new stdClass(),
				'note'       => 'Advanced Custom Fields is not installed/active on this site.',
			);
		}
		return array(
			'acf_active' => true,
			'fields'     => $this->read_acf( $post->ID ),
		);
	}

	/**
	 * Update ACF fields.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function update_acf_fields( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! function_exists( 'update_field' ) ) {
			return new WP_Error( 'wpmcp_no_acf', 'Advanced Custom Fields is not installed/active on this site.' );
		}
		if ( empty( $args['fields'] ) || ! is_array( $args['fields'] ) ) {
			return new WP_Error( 'wpmcp_missing_fields', 'A "fields" object mapping field name => value is required.' );
		}

		$updated = array();
		$failed  = array();
		foreach ( $args['fields'] as $selector => $value ) {
			$ok = update_field( $selector, $value, $post->ID );
			if ( false === $ok ) {
				$failed[] = $selector;
			} else {
				$updated[ $selector ] = get_field( $selector, $post->ID );
			}
		}

		return array(
			'success' => empty( $failed ),
			'id'      => $post->ID,
			'updated' => $updated,
			'failed'  => $failed,
		);
	}

	/**
	 * Bulk update many posts.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function bulk_update_content( $args ) {
		if ( empty( $args['items'] ) || ! is_array( $args['items'] ) ) {
			return new WP_Error( 'wpmcp_missing_items', 'An "items" array is required.' );
		}

		$report = array();
		foreach ( $args['items'] as $item ) {
			if ( empty( $item['id'] ) ) {
				$report[] = array(
					'id'      => null,
					'success' => false,
					'error'   => 'Missing id.',
				);
				continue;
			}

			$core = $item;
			$acf  = isset( $item['acf'] ) ? $item['acf'] : null;
			unset( $core['acf'] );

			$entry = array( 'id' => (int) $item['id'] );

			// Core fields.
			$core_result = $this->update_content( $core );
			if ( is_wp_error( $core_result ) ) {
				$entry['success'] = false;
				$entry['error']   = $core_result->get_error_message();
				$report[]         = $entry;
				continue;
			}
			$entry['changed'] = $core_result['changed'];
			$entry['success'] = true;

			// ACF fields.
			if ( is_array( $acf ) && ! empty( $acf ) ) {
				$acf_result = $this->update_acf_fields(
					array(
						'id'     => (int) $item['id'],
						'fields' => $acf,
					)
				);
				if ( is_wp_error( $acf_result ) ) {
					$entry['acf_error'] = $acf_result->get_error_message();
					$entry['success']   = false;
				} else {
					$entry['acf_updated'] = array_keys( $acf_result['updated'] );
					if ( ! empty( $acf_result['failed'] ) ) {
						$entry['acf_failed'] = $acf_result['failed'];
					}
				}
			}

			$report[] = $entry;
		}

		$ok = count( array_filter( $report, function ( $r ) {
			return ! empty( $r['success'] );
		} ) );

		return array(
			'total'     => count( $report ),
			'succeeded' => $ok,
			'failed'    => count( $report ) - $ok,
			'results'   => $report,
		);
	}

	/**
	 * Search & replace in post bodies.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function search_replace_content( $args ) {
		if ( ! isset( $args['search'] ) || '' === $args['search'] ) {
			return new WP_Error( 'wpmcp_missing_search', 'A "search" string is required.' );
		}
		$search    = (string) $args['search'];
		$replace   = isset( $args['replace'] ) ? (string) $args['replace'] : '';
		$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'page';
		$dry_run   = isset( $args['dry_run'] ) ? (bool) $args['dry_run'] : true;

		$query = new WP_Query(
			array(
				's'              => $search,
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => 200,
			)
		);

		$matches = array();
		foreach ( $query->posts as $post ) {
			if ( false === strpos( $post->post_content, $search ) ) {
				continue;
			}
			$count       = substr_count( $post->post_content, $search );
			$entry       = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'occurrences' => $count,
			);
			if ( ! $dry_run ) {
				$new = str_replace( $search, $replace, $post->post_content );
				$res = wp_update_post(
					array(
						'ID'           => $post->ID,
						'post_content' => $new,
					),
					true
				);
				$entry['updated'] = ! is_wp_error( $res );
				if ( is_wp_error( $res ) ) {
					$entry['error'] = $res->get_error_message();
				}
			}
			$matches[] = $entry;
		}

		return array(
			'dry_run'        => $dry_run,
			'search'         => $search,
			'replace'        => $replace,
			'posts_affected' => count( $matches ),
			'matches'        => $matches,
			'note'           => $dry_run ? 'Dry run only — no changes saved. Re-run with dry_run=false to apply.' : 'Changes applied.',
		);
	}

	/**
	 * Create a new post.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function create_content( $args ) {
		if ( empty( $args['title'] ) ) {
			return new WP_Error( 'wpmcp_missing_title', 'A "title" is required.' );
		}

		$postarr = array(
			'post_title'   => wp_kses_post( $args['title'] ),
			'post_type'    => isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'page',
			'post_status'  => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft',
			'post_content' => isset( $args['content'] ) ? $this->prepare_content( $args['content'] ) : '',
			'post_excerpt' => isset( $args['excerpt'] ) ? wp_kses_post( $args['excerpt'] ) : '',
		);
		if ( isset( $args['parent'] ) ) {
			$postarr['post_parent'] = (int) $args['parent'];
		}

		$id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$acf_result = null;
		if ( ! empty( $args['acf'] ) && is_array( $args['acf'] ) && function_exists( 'update_field' ) ) {
			foreach ( $args['acf'] as $selector => $value ) {
				update_field( $selector, $value, $id );
			}
			$acf_result = $this->read_acf( $id );
		}

		return array(
			'success'   => true,
			'id'        => $id,
			'permalink' => get_permalink( $id ),
			'edit_link' => get_edit_post_link( $id, 'raw' ),
			'acf'       => $acf_result,
		);
	}

	/**
	 * Trash or delete a post.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function delete_content( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$force  = ! empty( $args['force'] );
		$result = wp_delete_post( $post->ID, $force );
		if ( ! $result ) {
			return new WP_Error( 'wpmcp_delete_failed', 'Could not delete post ' . $post->ID );
		}
		return array(
			'success'   => true,
			'id'        => $post->ID,
			'forced'    => $force,
			'recovered' => $force ? false : true,
		);
	}

	/**
	 * List ACF field groups and their fields.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private function list_acf_field_groups( $args ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array(
				'acf_active' => false,
				'groups'     => array(),
				'note'       => 'Advanced Custom Fields is not installed/active.',
			);
		}

		$filter = array();
		if ( ! empty( $args['post_type'] ) ) {
			$filter['post_type'] = sanitize_key( $args['post_type'] );
		}

		$groups = acf_get_field_groups( $filter );
		$out    = array();
		foreach ( $groups as $group ) {
			$fields     = function_exists( 'acf_get_fields' ) ? acf_get_fields( $group['key'] ) : array();
			$field_list = array();
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					$field_list[] = array(
						'name'  => $field['name'],
						'key'   => $field['key'],
						'label' => $field['label'],
						'type'  => $field['type'],
					);
				}
			}
			$out[] = array(
				'key'    => $group['key'],
				'title'  => $group['title'],
				'fields' => $field_list,
			);
		}

		return array(
			'acf_active' => true,
			'groups'     => $out,
		);
	}

	/**
	 * Browse the media library.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private function list_media( $args ) {
		$per_page = isset( $args['per_page'] ) ? min( 100, max( 1, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		$query = new WP_Query( $query_args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'url'       => wp_get_attachment_url( $post->ID ),
				'mime_type' => $post->post_mime_type,
				'alt'       => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			);
		}

		return array(
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'items'       => $items,
		);
	}

	/**
	 * Upload a file to the media library from a URL or base64 data.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function upload_media( $args ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp      = '';
		$filename = isset( $args['filename'] ) ? sanitize_file_name( $args['filename'] ) : '';

		if ( ! empty( $args['url'] ) ) {
			$src_url = esc_url_raw( $args['url'] );

			// SSRF guard: only allow public http/https URLs. wp_http_validate_url()
			// rejects non-http(s) schemes, private/reserved IPs (e.g. 127.0.0.1,
			// 169.254.169.254, RFC1918 ranges) and non-standard ports.
			$scheme = strtolower( (string) wp_parse_url( $src_url, PHP_URL_SCHEME ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! wp_http_validate_url( $src_url ) ) {
				return new WP_Error(
					'wpmcp_unsafe_url',
					'The URL is not allowed. Provide a public http(s) URL — private, local and reserved addresses are blocked.'
				);
			}

			$tmp = download_url( $src_url );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			if ( ! $filename ) {
				$filename = basename( wp_parse_url( $src_url, PHP_URL_PATH ) );
			}
		} elseif ( ! empty( $args['base64'] ) ) {
			if ( ! $filename ) {
				return new WP_Error( 'wpmcp_missing_filename', 'A "filename" is required when uploading base64 data.' );
			}
			$decoded = base64_decode( $args['base64'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( false === $decoded ) {
				return new WP_Error( 'wpmcp_bad_base64', 'Could not decode base64 data.' );
			}
			$tmp = wp_tempnam( $filename );
			file_put_contents( $tmp, $decoded ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} else {
			return new WP_Error( 'wpmcp_missing_source', 'Provide either a "url" or "base64" with "filename".' );
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return $attachment_id;
		}

		if ( isset( $args['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}
		$post_update = array( 'ID' => $attachment_id );
		if ( isset( $args['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['caption'] ) ) {
			$post_update['post_excerpt'] = wp_kses_post( $args['caption'] );
		}
		if ( count( $post_update ) > 1 ) {
			wp_update_post( $post_update );
		}

		return array(
			'success'   => true,
			'id'        => $attachment_id,
			'url'       => wp_get_attachment_url( $attachment_id ),
			'mime_type' => get_post_mime_type( $attachment_id ),
		);
	}

	/**
	 * Update media metadata.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function update_media( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'attachment' !== $post->post_type ) {
			return new WP_Error( 'wpmcp_not_media', 'Post ' . $post->ID . ' is not a media attachment.' );
		}

		if ( isset( $args['alt'] ) ) {
			update_post_meta( $post->ID, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}
		$update = array( 'ID' => $post->ID );
		if ( isset( $args['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['caption'] ) ) {
			$update['post_excerpt'] = wp_kses_post( $args['caption'] );
		}
		if ( isset( $args['description'] ) ) {
			$update['post_content'] = wp_kses_post( $args['description'] );
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		return array(
			'success' => true,
			'id'      => $post->ID,
			'alt'     => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Set or clear a featured image.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function set_featured_image( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;

		if ( $attachment_id <= 0 ) {
			delete_post_thumbnail( $post->ID );
			return array(
				'success' => true,
				'id'      => $post->ID,
				'cleared' => true,
			);
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'wpmcp_bad_attachment', 'attachment_id ' . $attachment_id . ' is not a media item.' );
		}
		set_post_thumbnail( $post->ID, $attachment_id );

		return array(
			'success'        => true,
			'id'             => $post->ID,
			'featured_image' => get_the_post_thumbnail_url( $post->ID, 'full' ),
		);
	}

	/**
	 * List registered taxonomies.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private function list_taxonomies( $args ) {
		$taxes = get_taxonomies( array( 'public' => true ), 'objects' );
		$out   = array();
		foreach ( $taxes as $tax ) {
			$out[] = array(
				'slug'         => $tax->name,
				'label'        => $tax->label,
				'hierarchical' => (bool) $tax->hierarchical,
				'post_types'   => array_values( (array) $tax->object_type ),
			);
		}
		return array( 'taxonomies' => $out );
	}

	/**
	 * List terms within a taxonomy.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function get_terms_tool( $args ) {
		if ( empty( $args['taxonomy'] ) ) {
			return new WP_Error( 'wpmcp_missing_taxonomy', 'A "taxonomy" is required.' );
		}
		$taxonomy = sanitize_key( $args['taxonomy'] );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'wpmcp_bad_taxonomy', 'Unknown taxonomy: ' . $taxonomy );
		}

		$query_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 200,
		);
		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = sanitize_text_field( $args['search'] );
		}

		$terms = get_terms( $query_args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'id'     => $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'count'  => $term->count,
				'parent' => $term->parent,
			);
		}
		return array(
			'taxonomy' => $taxonomy,
			'terms'    => $out,
		);
	}

	/**
	 * Assign terms to a post.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function set_post_terms( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( empty( $args['taxonomy'] ) || ! taxonomy_exists( sanitize_key( $args['taxonomy'] ) ) ) {
			return new WP_Error( 'wpmcp_bad_taxonomy', 'A valid "taxonomy" is required.' );
		}
		if ( ! isset( $args['terms'] ) || ! is_array( $args['terms'] ) ) {
			return new WP_Error( 'wpmcp_missing_terms', 'A "terms" array is required.' );
		}

		$taxonomy = sanitize_key( $args['taxonomy'] );
		$append   = ! empty( $args['append'] );

		// Integers are treated as term ids; strings as names (created if needed).
		$terms = array();
		foreach ( $args['terms'] as $t ) {
			$terms[] = is_numeric( $t ) ? (int) $t : sanitize_text_field( $t );
		}

		$result = wp_set_object_terms( $post->ID, $terms, $taxonomy, $append );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'      => true,
			'id'           => $post->ID,
			'taxonomy'     => $taxonomy,
			'term_ids'     => array_map( 'intval', $result ),
			'appended'     => $append,
		);
	}

	/**
	 * List revisions of a post.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function list_revisions( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$revisions = wp_get_post_revisions( $post->ID, array( 'posts_per_page' => 30 ) );
		$out       = array();
		foreach ( $revisions as $rev ) {
			$out[] = array(
				'id'       => $rev->ID,
				'author'   => get_the_author_meta( 'display_name', $rev->post_author ),
				'modified' => $rev->post_modified_gmt,
				'title'    => $rev->post_title,
			);
		}
		return array(
			'id'        => $post->ID,
			'revisions' => $out,
		);
	}

	/**
	 * Restore a post to a revision.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function restore_revision( $args ) {
		$revision_id = isset( $args['revision_id'] ) ? (int) $args['revision_id'] : 0;
		if ( ! $revision_id ) {
			return new WP_Error( 'wpmcp_missing_revision', 'A "revision_id" is required.' );
		}
		$revision = wp_get_post_revision( $revision_id );
		if ( ! $revision ) {
			return new WP_Error( 'wpmcp_bad_revision', 'No revision found with id ' . $revision_id );
		}
		// Capability check against the parent post.
		$check = $this->require_post( array( 'id' => $revision->post_parent ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$restored = wp_restore_post_revision( $revision_id );
		if ( ! $restored ) {
			return new WP_Error( 'wpmcp_restore_failed', 'Could not restore revision ' . $revision_id );
		}
		return array(
			'success'     => true,
			'post_id'     => $revision->post_parent,
			'revision_id' => $revision_id,
		);
	}

	/**
	 * Read SEO metadata (Yoast / Rank Math aware).
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function get_seo_meta( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$map = $this->seo_meta_map();
		if ( ! $map ) {
			return array(
				'seo_plugin' => 'none',
				'note'       => 'No supported SEO plugin (Yoast or Rank Math) detected.',
			);
		}
		$data = array( 'seo_plugin' => $map['plugin'], 'id' => $post->ID );
		foreach ( $map['keys'] as $friendly => $meta_key ) {
			$data[ $friendly ] = get_post_meta( $post->ID, $meta_key, true );
		}
		return $data;
	}

	/**
	 * Update SEO metadata (Yoast / Rank Math aware).
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function update_seo_meta( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$map = $this->seo_meta_map();
		if ( ! $map ) {
			return new WP_Error( 'wpmcp_no_seo', 'No supported SEO plugin (Yoast or Rank Math) detected.' );
		}

		$changed = array();
		foreach ( $map['keys'] as $friendly => $meta_key ) {
			if ( isset( $args[ $friendly ] ) ) {
				update_post_meta( $post->ID, $meta_key, sanitize_text_field( $args[ $friendly ] ) );
				$changed[] = $friendly;
			}
		}

		return array(
			'success'    => true,
			'id'         => $post->ID,
			'seo_plugin' => $map['plugin'],
			'changed'    => $changed,
		);
	}

	/**
	 * Read post meta (any custom field plugin).
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function get_post_meta_tool( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! empty( $args['key'] ) ) {
			$key = sanitize_text_field( $args['key'] );
			return array(
				'id'    => $post->ID,
				'key'   => $key,
				'value' => get_post_meta( $post->ID, $key, true ),
			);
		}

		$all = get_post_meta( $post->ID );
		$out = array();
		foreach ( $all as $key => $values ) {
			if ( is_protected_meta( $key, 'post' ) ) {
				continue;
			}
			$out[ $key ] = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
		}
		return array(
			'id'   => $post->ID,
			'meta' => $out,
		);
	}

	/**
	 * Update post meta (any custom field plugin).
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function update_post_meta_tool( $args ) {
		$post = $this->require_post( $args );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( empty( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
			return new WP_Error( 'wpmcp_missing_meta', 'A "meta" object mapping key => value is required.' );
		}

		$updated = array();
		$skipped = array();
		foreach ( $args['meta'] as $key => $value ) {
			$key = sanitize_text_field( $key );
			if ( is_protected_meta( $key, 'post' ) ) {
				$skipped[] = $key;
				continue;
			}
			update_post_meta( $post->ID, $key, $value );
			$updated[] = $key;
		}

		return array(
			'success' => true,
			'id'      => $post->ID,
			'updated' => $updated,
			'skipped' => $skipped,
		);
	}

	/**
	 * General site information.
	 *
	 * @return array
	 */
	private function get_site_info() {
		$theme = wp_get_theme();
		return array(
			'name'        => get_bloginfo( 'name' ),
			'tagline'     => get_bloginfo( 'description' ),
			'url'         => home_url( '/' ),
			'admin_email' => get_bloginfo( 'admin_email' ),
			'wp_version'  => get_bloginfo( 'version' ),
			'language'    => get_bloginfo( 'language' ),
			'timezone'    => wp_timezone_string(),
			'theme'       => array(
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
			),
			'integrations' => array(
				'acf'         => function_exists( 'get_field' ),
				'yoast'       => defined( 'WPSEO_VERSION' ),
				'rank_math'   => class_exists( 'RankMath' ),
				'woocommerce' => class_exists( 'WooCommerce' ),
			),
		);
	}

	/**
	 * Add one or many redirects.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	private function add_redirect( $args ) {
		$added = array();

		if ( ! empty( $args['redirects'] ) && is_array( $args['redirects'] ) ) {
			foreach ( $args['redirects'] as $r ) {
				if ( empty( $r['from'] ) || empty( $r['to'] ) ) {
					continue;
				}
				$added[] = WPMCP_Redirects::add( $r['from'], $r['to'], isset( $r['code'] ) ? $r['code'] : 301 );
			}
			return array(
				'success' => true,
				'count'   => count( $added ),
				'added'   => $added,
			);
		}

		if ( empty( $args['from'] ) || empty( $args['to'] ) ) {
			return new WP_Error( 'wpmcp_missing_redirect', 'Provide "from" and "to" (or a "redirects" array).' );
		}

		$row = WPMCP_Redirects::add( $args['from'], $args['to'], isset( $args['code'] ) ? $args['code'] : 301 );
		return array(
			'success'  => true,
			'redirect' => $row,
		);
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Map friendly SEO field names to the active plugin's meta keys.
	 *
	 * @return array|null { plugin: string, keys: array } or null if none.
	 */
	private function seo_meta_map() {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			return array(
				'plugin' => 'yoast',
				'keys'   => array(
					'seo_title'       => '_yoast_wpseo_title',
					'seo_description' => '_yoast_wpseo_metadesc',
					'focus_keyword'   => '_yoast_wpseo_focuskw',
					'canonical'       => '_yoast_wpseo_canonical',
				),
			);
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return array(
				'plugin' => 'rank_math',
				'keys'   => array(
					'seo_title'       => 'rank_math_title',
					'seo_description' => 'rank_math_description',
					'focus_keyword'   => 'rank_math_focus_keyword',
					'canonical'       => 'rank_math_canonical_url',
				),
			);
		}
		return null;
	}

	/**
	 * Resolve and validate a post argument.
	 *
	 * @param array $args Arguments containing an id.
	 * @return WP_Post|WP_Error
	 */
	private function require_post( $args ) {
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		if ( ! $id ) {
			return new WP_Error( 'wpmcp_missing_id', 'A numeric "id" is required.' );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return new WP_Error( 'wpmcp_not_found', 'No post found with id ' . $id );
		}
		if ( ! current_user_can_for_blog( get_current_blog_id(), 'edit_post', $id ) && ! $this->key_authenticated() ) {
			return new WP_Error( 'wpmcp_forbidden', 'You are not allowed to access post ' . $id );
		}
		return $post;
	}

	/**
	 * Whether the request authenticated with the plugin API key (full trust).
	 *
	 * @return bool
	 */
	private function key_authenticated() {
		// When no WP user is set, the request passed via API key in WPMCP_Auth.
		return ! is_user_logged_in();
	}

	/**
	 * Lightweight summary of a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function summarize_post( $post ) {
		return array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'slug'      => $post->post_name,
			'type'      => $post->post_type,
			'status'    => $post->post_status,
			'url'       => get_permalink( $post ),
			'modified'  => $post->post_modified_gmt,
			'parent'    => (int) $post->post_parent,
		);
	}

	/**
	 * Read ACF fields safely.
	 *
	 * @param int $post_id Post ID.
	 * @return array|stdClass
	 */
	private function read_acf( $post_id ) {
		if ( ! function_exists( 'get_fields' ) ) {
			return new stdClass();
		}
		$fields = get_fields( $post_id );
		if ( empty( $fields ) ) {
			return new stdClass();
		}
		return $fields;
	}

	/**
	 * Sanitise incoming HTML content, preserving block markup.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private function prepare_content( $content ) {
		// Allow users with unfiltered_html (or API-key trust) to pass raw markup;
		// otherwise run through wp_kses_post to strip dangerous tags.
		if ( $this->key_authenticated() || current_user_can( 'unfiltered_html' ) ) {
			return (string) $content;
		}
		return wp_kses_post( $content );
	}
}
