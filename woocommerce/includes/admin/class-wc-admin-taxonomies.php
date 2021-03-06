<?php
/**
 * Handles taxonomies in admin
 *
 * @class    WC_Admin_Taxonomies
 * @version  2.3.10
 * @package  WooCommerce/Admin
 * @category Class
 * @author   WooThemes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WC_Admin_Taxonomies class.
 */
class WC_Admin_Taxonomies {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Category/term ordering
		add_action( 'create_term', array( $this, 'create_term' ), 5, 3 );
		add_action( 'delete_term', array( $this, 'delete_term' ), 5 );

		// Add form
		add_action( 'product_cat_add_form_fields', array( $this, 'add_category_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_fields' ), 10 );
		add_action( 'created_term', array( $this, 'save_category_fields' ), 10, 3 );
		add_action( 'edit_term', array( $this, 'save_category_fields' ), 10, 3 );

		// Add columns
		add_filter( 'manage_edit-product_cat_columns', array( $this, 'product_cat_columns' ) );
		add_filter( 'manage_product_cat_custom_column', array( $this, 'product_cat_column' ), 10, 3 );

		// Add row actions.
		add_filter( 'product_cat_row_actions', array( $this, 'product_cat_row_actions' ), 10, 2 );
		add_filter( 'admin_init', array( $this, 'handle_product_cat_row_actions' ) );

		// Taxonomy page descriptions
		add_action( 'product_cat_pre_add_form', array( $this, 'product_cat_description' ) );
		add_action( 'after-product_cat-table', array( $this, 'product_cat_notes' ) );

		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $attribute ) {
				add_action( 'pa_' . $attribute->attribute_name . '_pre_add_form', array( $this, 'product_attribute_description' ) );
			}
		}

		// Maintain hierarchy of terms
		add_filter( 'wp_terms_checklist_args', array( $this, 'disable_checked_ontop' ) );
	}

	/**
	 * Order term when created (put in position 0).
	 *
	 * @param mixed  $term_id
	 * @param mixed  $tt_id
	 * @param string $taxonomy
	 */
	public function create_term( $term_id, $tt_id = '', $taxonomy = '' ) {
		if ( 'product_cat' != $taxonomy && ! taxonomy_is_product_attribute( $taxonomy ) ) {
			return;
		}

		$meta_name = taxonomy_is_product_attribute( $taxonomy ) ? 'order_' . esc_attr( $taxonomy ) : 'order';

		update_woocommerce_term_meta( $term_id, $meta_name, 0 );
	}

	/**
	 * When a term is deleted, delete its meta.
	 *
	 * @param mixed $term_id
	 */
	public function delete_term( $term_id ) {
		global $wpdb;

		$term_id = absint( $term_id );

		if ( $term_id && get_option( 'db_version' ) < 34370 ) {
			$wpdb->delete( $wpdb->woocommerce_termmeta, array( 'woocommerce_term_id' => $term_id ), array( '%d' ) );
		}
	}

	/**
	 * Category thumbnail fields.
	 */
	public function add_category_fields() {
		?>
		<div class="form-field term-display-type-wrap">
			<label for="display_type"><?php _e( 'Display type', 'woocommerce' ); ?></label>
			<select id="display_type" name="display_type" class="postform">
				<option value=""><?php _e( 'Default', 'woocommerce' ); ?></option>
				<option value="products"><?php _e( 'Products', 'woocommerce' ); ?></option>
				<option value="subcategories"><?php _e( 'Subcategories', 'woocommerce' ); ?></option>
				<option value="both"><?php _e( 'Both', 'woocommerce' ); ?></option>
			</select>
		</div>
		<div class="form-field term-thumbnail-wrap">
			<label><?php _e( 'Thumbnail', 'woocommerce' ); ?></label>
			<div id="product_cat_thumbnail" style="float: left; margin-right: 10px;"><img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>" width="60px" height="60px" /></div>
			<div style="line-height: 60px;">
				<input type="hidden" id="product_cat_thumbnail_id" name="product_cat_thumbnail_id" />
				<button type="button" class="upload_image_button button"><?php _e( 'Upload/Add image', 'woocommerce' ); ?></button>
				<button type="button" class="remove_image_button button"><?php _e( 'Remove image', 'woocommerce' ); ?></button>
			</div>
			<script type="text/javascript">

				// Only show the "remove image" button when needed
				if ( ! jQuery( '#product_cat_thumbnail_id' ).val() ) {
					jQuery( '.remove_image_button' ).hide();
				}

				// Uploading files
				var file_frame;

				jQuery( document ).on( 'click', '.upload_image_button', function( event ) {

					event.preventDefault();

					// If the media frame already exists, reopen it.
					if ( file_frame ) {
						file_frame.open();
						return;
					}

					// Create the media frame.
					file_frame = wp.media.frames.downloadable_file = wp.media({
						title: '<?php _e( 'Choose an image', 'woocommerce' ); ?>',
						button: {
							text: '<?php _e( 'Use image', 'woocommerce' ); ?>'
						},
						multiple: false
					});

					// When an image is selected, run a callback.
					file_frame.on( 'select', function() {
						var attachment           = file_frame.state().get( 'selection' ).first().toJSON();
						var attachment_thumbnail = attachment.sizes.thumbnail || attachment.sizes.full;

						jQuery( '#product_cat_thumbnail_id' ).val( attachment.id );
						jQuery( '#product_cat_thumbnail' ).find( 'img' ).attr( 'src', attachment_thumbnail.url );
						jQuery( '.remove_image_button' ).show();
					});

					// Finally, open the modal.
					file_frame.open();
				});

				jQuery( document ).on( 'click', '.remove_image_button', function() {
					jQuery( '#product_cat_thumbnail' ).find( 'img' ).attr( 'src', '<?php echo esc_js( wc_placeholder_img_src() ); ?>' );
					jQuery( '#product_cat_thumbnail_id' ).val( '' );
					jQuery( '.remove_image_button' ).hide();
					return false;
				});

				jQuery( document ).ajaxComplete( function( event, request, options ) {
					if ( request && 4 === request.readyState && 200 === request.status
						&& options.data && 0 <= options.data.indexOf( 'action=add-tag' ) ) {

						var res = wpAjax.parseAjaxResponse( request.responseXML, 'ajax-response' );
						if ( ! res || res.errors ) {
							return;
						}
						// Clear Thumbnail fields on submit
						jQuery( '#product_cat_thumbnail' ).find( 'img' ).attr( 'src', '<?php echo esc_js( wc_placeholder_img_src() ); ?>' );
						jQuery( '#product_cat_thumbnail_id' ).val( '' );
						jQuery( '.remove_image_button' ).hide();
						// Clear Display type field on submit
						jQuery( '#display_type' ).val( '' );
						return;
					}
				} );

			</script>
			<div class="clear"></div>
		</div>
		<?php
	}

	/**
	 * Edit category thumbnail field.
	 *
	 * @param mixed $term Term (category) being edited
	 */
	public function edit_category_fields( $term ) {

		$display_type = get_woocommerce_term_meta( $term->term_id, 'display_type', true );
		$thumbnail_id = absint( get_woocommerce_term_meta( $term->term_id, 'thumbnail_id', true ) );

		if ( $thumbnail_id ) {
			$image = wp_get_attachment_thumb_url( $thumbnail_id );
		} else {
			$image = wc_placeholder_img_src();
		}
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Display type', 'woocommerce' ); ?></label></th>
			<td>
				<select id="display_type" name="display_type" class="postform">
					<option value="" <?php selected( '', $display_type ); ?>><?php _e( 'Default', 'woocommerce' ); ?></option>
					<option value="products" <?php selected( 'products', $display_type ); ?>><?php _e( 'Products', 'woocommerce' ); ?></option>
					<option value="subcategories" <?php selected( 'subcategories', $display_type ); ?>><?php _e( 'Subcategories', 'woocommerce' ); ?></option>
					<option value="both" <?php selected( 'both', $display_type ); ?>><?php _e( 'Both', 'woocommerce' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Thumbnail', 'woocommerce' ); ?></label></th>
			<td>
				<div id="product_cat_thumbnail" style="float: left; margin-right: 10px;"><img src="<?php echo esc_url( $image ); ?>" width="60px" height="60px" /></div>
				<div style="line-height: 60px;">
					<input type="hidden" id="product_cat_thumbnail_id" name="product_cat_thumbnail_id" value="<?php echo $thumbnail_id; ?>" />
					<button type="button" class="upload_image_button button"><?php _e( 'Upload/Add image', 'woocommerce' ); ?></button>
					<button type="button" class="remove_image_button button"><?php _e( 'Remove image', 'woocommerce' ); ?></button>
				</div>
				<script type="text/javascript">

					// Only show the "remove image" button when needed
					if ( '0' === jQuery( '#product_cat_thumbnail_id' ).val() ) {
						jQuery( '.remove_image_button' ).hide();
					}

					// Uploading files
					var file_frame;

					jQuery( document ).on( 'click', '.upload_image_button', function( event ) {

						event.preventDefault();

						// If the media frame already exists, reopen it.
						if ( file_frame ) {
							file_frame.open();
							return;
						}

						// Create the media frame.
						file_frame = wp.media.frames.downloadable_file = wp.media({
							title: '<?php _e( 'Choose an image', 'woocommerce' ); ?>',
							button: {
								text: '<?php _e( 'Use image', 'woocommerce' ); ?>'
							},
							multiple: false
						});

						// When an image is selected, run a callback.
						file_frame.on( 'select', function() {
							var attachment           = file_frame.state().get( 'selection' ).first().toJSON();
							var attachment_thumbnail = attachment.sizes.thumbnail || attachment.sizes.full;

							jQuery( '#product_cat_thumbnail_id' ).val( attachment.id );
							jQuery( '#product_cat_thumbnail' ).find( 'img' ).attr( 'src', attachment_thumbnail.url );
							jQuery( '.remove_image_button' ).show();
						});

						// Finally, open the modal.
						file_frame.open();
					});

					jQuery( document ).on( 'click', '.remove_image_button', function() {
						jQuery( '#product_cat_thumbnail' ).find( 'img' ).attr( 'src', '<?php echo esc_js( wc_placeholder_img_src() ); ?>' );
						jQuery( '#product_cat_thumbnail_id' ).val( '' );
						jQuery( '.remove_image_button' ).hide();
						return false;
					});

				</script>
				<div class="clear"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * save_category_fields function.
	 *
	 * @param mixed  $term_id Term ID being saved
	 * @param mixed  $tt_id
	 * @param string $taxonomy
	 */
	public function save_category_fields( $term_id, $tt_id = '', $taxonomy = '' ) {
		if ( isset( $_POST['display_type'] ) && 'product_cat' === $taxonomy ) {
			update_woocommerce_term_meta( $term_id, 'display_type', esc_attr( $_POST['display_type'] ) );
		}
		if ( isset( $_POST['product_cat_thumbnail_id'] ) && 'product_cat' === $taxonomy ) {
			update_woocommerce_term_meta( $term_id, 'thumbnail_id', absint( $_POST['product_cat_thumbnail_id'] ) );
		}
	}

	/**
	 * Description for product_cat page to aid users.
	 */
	public function product_cat_description() {
		echo wpautop( __( 'Product categories for your store can be managed here. To change the order of categories on the front-end you can drag and drop to sort them. To see more categories listed click the "screen options" link at the top-right of this page.', 'woocommerce' ) );
	}

	/**
	 * Add some notes to describe the behavior of the default category.
	 */
	public function product_cat_notes() {
		$category_id   = get_option( 'default_product_cat', 0 );
		$category      = get_term( $category_id, 'product_cat' );
		$category_name = ( ! $category || is_wp_error( $category ) ) ? _x( 'Uncategorized', 'Default category slug', 'woocommerce' ) : $category->name;
		?>
		<div class="form-wrap edit-term-notes">
			<p>
				<strong><?php _e( 'Note:', 'woocommerce' ); ?></strong><br>
				<?php
					printf(
						/* translators: %s: default category */
						__( 'Deleting a category does not delete the products in that category. Instead, products that were only assigned to the deleted category are set to the category %s.', 'woocommerce' ),
						'<strong>' . esc_html( $category_name ) . '</strong>'
					);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Description for shipping class page to aid users.
	 */
	public function product_attribute_description() {
		echo wpautop( __( 'Attribute terms can be assigned to products and variations.<br/><br/><b>Note</b>: Deleting a term will remove it from all products and variations to which it has been assigned. Recreating a term will not automatically assign it back to products.', 'woocommerce' ) );
	}

	/**
	 * Thumbnail column added to category admin.
	 *
	 * @param mixed $columns
	 * @return array
	 */
	public function product_cat_columns( $columns ) {
		$new_columns = array();

		if ( isset( $columns['cb'] ) ) {
			$new_columns['cb'] = $columns['cb'];
			unset( $columns['cb'] );
		}

		$new_columns['thumb'] = __( 'Image', 'woocommerce' );

		$columns           = array_merge( $new_columns, $columns );
		$columns['handle'] = '';

		return $columns;
	}

	/**
	 * Adjust row actions.
	 *
	 * @param array  $actions Array of actions.
	 * @param object $term Term object.
	 * @return array
	 */
	public function product_cat_row_actions( $actions = array(), $term ) {
		$default_category_id = absint( get_option( 'default_product_cat', 0 ) );

		if ( $default_category_id !== $term->term_id && current_user_can( 'edit_term', $term->term_id ) ) {
			$actions['make_default'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				wp_nonce_url( 'edit-tags.php?action=make_default&amp;taxonomy=product_cat&amp;tag_ID=' . absint( $term->term_id ), 'make_default_' . absint( $term->term_id ) ),
				/* translators: %s: taxonomy term name */
				esc_attr( sprintf( __( 'Make &#8220;%s&#8221; the default category', 'woocommerce' ), $term->name ) ),
				__( 'Make default', 'woocommerce' )
			);
		}

		return $actions;
	}

	/**
	 * Handle custom row actions.
	 */
	public function handle_product_cat_row_actions() {
		if ( isset( $_GET['action'], $_GET['tag_ID'], $_GET['_wpnonce'] ) && 'make_default' === $_GET['action'] ) {
			$make_default_id = absint( $_GET['tag_ID'] );

			if ( wp_verify_nonce( $_GET['_wpnonce'], 'make_default_' . $make_default_id ) && current_user_can( 'edit_term', $make_default_id ) ) {
				update_option( 'default_product_cat', $make_default_id );
			}
		}
	}

	/**
	 * Thumbnail column value added to category admin.
	 *
	 * @param string $columns
	 * @param string $column
	 * @param int    $id
	 *
	 * @return string
	 */
	public function product_cat_column( $columns, $column, $id ) {
		if ( 'thumb' === $column ) {
			// Prepend tooltip for default category.
			$default_category_id = absint( get_option( 'default_product_cat', 0 ) );

			if ( $default_category_id === $id ) {
				$columns .= wc_help_tip( __( 'This is the default category and it cannot be deleted. It will be automatically assigned to products with no category.', 'woocommerce' ) );
			}

			$thumbnail_id = get_woocommerce_term_meta( $id, 'thumbnail_id', true );

			if ( $thumbnail_id ) {
				$image = wp_get_attachment_thumb_url( $thumbnail_id );
			} else {
				$image = wc_placeholder_img_src();
			}

			// Prevent esc_url from breaking spaces in urls for image embeds. Ref: https://core.trac.wordpress.org/ticket/23605
			$image    = str_replace( ' ', '%20', $image );
			$columns .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr__( 'Thumbnail', 'woocommerce' ) . '" class="wp-post-image" height="48" width="48" />';
		}
		if ( 'handle' === $column ) {
			$columns .= '<input type="hidden" name="term_id" value="' . esc_attr( $id ) . '" />';
		}
		return $columns;
	}

	/**
	 * Maintain term hierarchy when editing a product.
	 *
	 * @param  array $args
	 * @return array
	 */
	public function disable_checked_ontop( $args ) {
		if ( ! empty( $args['taxonomy'] ) && 'product_cat' === $args['taxonomy'] ) {
			$args['checked_ontop'] = false;
		}
		return $args;
	}
}

new WC_Admin_Taxonomies();
