<?php

// Avoid name collision

if( ! class_exists( 'DPPGiftAdmin' ) ) :

class DPPGiftAdmin {

    public function __construct() {

        // Register the main post type
        add_action( 'init', array( $this, 'register_post_type' ) );

        // Save the gift certificate data
        add_action( 'save_post', array( $this, 'save_meta_data' ), 10, 2 );

        // Create new columns values
        add_filter( 'manage_edit-' . DPP_GC_CPT . '_columns', array( $this, 'column_headers' ) );
        add_action( 'manage_' . DPP_GC_CPT . '_posts_custom_column', array( $this, 'column_values' ), 10, 2 );
        add_action( 'manage_edit-' . DPP_GC_CPT . '_sortable_columns', array( $this, 'sortable_column_headers' ) );

        // Add admin CSS
        add_action( 'admin_print_styles', array( $this, 'admin_css' ) );
    }

    function register_post_type() {

        $labels = array(

            'name' => _x( 'Gift Certificates', 'post type general name', DPP_GC_DOMAIN ),
            'singular_name' => _x( 'Gift Certificates', 'post type singular name', DPP_GC_DOMAIN ),
            'add_new' => _x( 'Add New', 'gift-certificate', DPP_GC_DOMAIN ),
            'add_new_item' => __( 'Add New Gift Certificate', DPP_GC_DOMAIN ),
            'edit_item' => __( 'Edit Gift Certificates', DPP_GC_DOMAIN ),
            'new_item' => __( 'New Gift Certificates', DPP_GC_DOMAIN ),
            'all_items' => __( 'Gift Certificates', DPP_GC_DOMAIN ),
            'view_item' => __( 'View Gift Certificate', DPP_GC_DOMAIN ),
            'search_items' => __( 'Search Gift Certificates', DPP_GC_DOMAIN ),
            'not_found' =>  __( 'No gift certificates found', DPP_GC_DOMAIN ),
            'not_found_in_trash' => __( 'No gift certificates found in Trash', DPP_GC_DOMAIN ),
            'parent_item_colon' => '',
            'menu_name' => __( 'Gift Certificates', DPP_GC_DOMAIN )
        );

        $args = array(

            'labels' => $labels,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=wpsc-product',
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'can_export' => false,
            'show_in_nav_menus' => false,
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => array( 'title' ),
            'register_meta_box_cb' => array( $this, 'add_meta_boxes' )
        );

        register_post_type( DPP_GC_CPT, $args );
    }

    function column_headers( $columns ) {

        $new_columns = array(

            'cb' => '<input type="checkbox" />',
            'gc-code' => __( 'Code', DPP_GC_DOMAIN ),
            'purchase-log-id' => __( 'Purchase Log ID', DPP_GC_DOMAIN ),
            'amount-total' => __( 'Amount Total', DPP_GC_DOMAIN ),
            'amount-left' => __( 'Amount Left', DPP_GC_DOMAIN ),
            'date' => __( 'Purchase Date', DPP_GC_DOMAIN )
        );

        return $new_columns;
    }

    function column_values( $column_name, $id ) {

        $data = dpp_get_gc_data( $id );

        switch ( $column_name ) {

            case 'gc-code':

                echo '<strong><a class="row-title" href="' . esc_attr( get_edit_post_link( $id ) ) . '" title="Edit Coupon ' . esc_attr( get_the_title( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></strong>';
                break;

            case 'purchase-log-id':

                echo $data[DPP_GC_PREFIX . '-purchase-log-id'];
                break;

            case 'amount-total':

                echo $data[DPP_GC_PREFIX . '-amount-total'];
                break;

            case 'amount-left':

                echo $data[DPP_GC_PREFIX . '-amount-left'];
                break;

            default:
                break;
        }

	}

    function sortable_column_headers( $columns ) {

        return $columns;

    }

    function admin_css() {

        global $pagenow, $post;

        // Only load on GC add/edit screens

        if ( ( 'post.php' == $pagenow && DPP_GC_CPT == $post->post_type ) || ( 'post-new.php' == $pagenow && DPP_GC_CPT == $_GET['post_type'] ) ) {

            wp_enqueue_style(
                DPP_GC_PREFIX . '-admin-css',
                plugins_url( 'css/admin-style.css', __FILE__ ),
                false,
                DPP_GC_VERSION,
                'all'
            );
        }
    }

    function add_meta_boxes( $post ) {

        add_meta_box(
            DPP_GC_CPT,
            __( 'Gift Certificate Details', DPP_GC_DOMAIN ),
            array( $this, 'display_meta_box' ),
            DPP_GC_CPT,
            'normal',
            'high'
        );
    }

    function display_meta_box() {

        global $post;

        // Get data

        $data = wp_parse_args(
            dpp_get_gc_data( $post->ID ),
            array(
                DPP_GC_PREFIX . '-purchase-log-id' => '',
                DPP_GC_PREFIX . '-gift-certificate-item-id' => '',
                DPP_GC_PREFIX . '-amount-total' => '',
                DPP_GC_PREFIX . '-amount-left' => '',
                DPP_GC_PREFIX . '-uses' => '',
                DPP_GC_PREFIX . '-recipient-email' => ''
            )
        );

        // Everyone likes a secure form
        wp_nonce_field( plugins_url( __FILE__ ), DPP_GC_CPT ); ?>

        <div class="<?php echo DPP_GC_PREFIX;?>-meta-column">
            <p>
                <label for="<?php echo DPP_GC_PREFIX;?>-amount-total"><?php _e( 'Amount Total', DPP_GC_DOMAIN ); ?>: </label>
                <input type="text" id="<?php echo DPP_GC_PREFIX;?>-amount-total" name="<?php echo DPP_GC_PREFIX;?>[amount-total]" value="<?php echo esc_html( $data[DPP_GC_PREFIX . '-amount-total'] ); ?>" />
                <em>Must be a numeric value</em>
            </p>
            <p>
                <label for="<?php echo DPP_GC_PREFIX;?>-amount-left"><?php _e( 'Amount Left', DPP_GC_DOMAIN ); ?>: </label>
                <input type="text" id="<?php echo DPP_GC_PREFIX;?>-amount-left" name="<?php echo DPP_GC_PREFIX;?>[amount-left]" value="<?php echo esc_html( $data[DPP_GC_PREFIX . '-amount-left'] ); ?>" />
                <em>Must be a numeric value</em>
            </p>
        </div>
        <div class="<?php echo DPP_GC_PREFIX;?>-meta-column">
            <p>
                <label for="<?php echo DPP_GC_PREFIX;?>-purchase-log-id"><?php _e( 'Purchase Log ID', DPP_GC_DOMAIN ); ?>: </label>
                <input type="text" id="<?php echo DPP_GC_PREFIX;?>-purchase-log-id" name="<?php echo DPP_GC_PREFIX;?>[purchase-log-id]" value="<?php echo absint( $data[DPP_GC_PREFIX . '-purchase-log-id'] ); ?>" />
                <em>This value should not be changed unless you know what you are doing.</em>
            </p>
            <p>
                <label for="<?php echo DPP_GC_PREFIX;?>-gift-certificate-item-id"><?php _e( 'Gift Certificate Item ID', DPP_GC_DOMAIN ); ?>: </label>
                <input type="text" id="<?php echo DPP_GC_PREFIX;?>-gift-certificate-item-id" name="<?php echo DPP_GC_PREFIX;?>[gift-certificate-item-id]" value="<?php echo absint( $data[DPP_GC_PREFIX . '-gift-certificate-item-id'] ); ?>" />
                <em>This value should not be changed unless you know what you are doing.</em>
            </p>
            <p>
                <label for="<?php echo DPP_GC_PREFIX;?>-recipient-email"><?php _e( 'Recipient Email', DPP_GC_DOMAIN ); ?>: </label>
                <input type="text" id="<?php echo DPP_GC_PREFIX;?>-recipient-email" name="<?php echo DPP_GC_PREFIX;?>[recipient-email]" value="<?php echo sanitize_email( $data[DPP_GC_PREFIX . '-recipient-email'] ); ?>" />
                <em>Must be a valid email address</em>
            </p>
        </div>
        <div class="<?php echo DPP_GC_PREFIX;?>-clearfloats"></div>
        <?php
    }

    function save_meta_data( $post_id, $post ) {

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

        if ( ! current_user_can( 'edit_post', $post_id ) )
            return;

        if ( isset( $_POST[DPP_GC_CPT] ) && check_admin_referer( plugins_url( __FILE__ ), DPP_GC_CPT ) ) {

            // Set defaults
            $defaults = array(
                DPP_GC_PREFIX . '-purchase-log-id' => '',
                DPP_GC_PREFIX . '-gift-certificate-item-id' => '',
                DPP_GC_PREFIX . '-amount-total' => '',
                DPP_GC_PREFIX . '-amount-left' => '',
                DPP_GC_PREFIX . '-uses' => '',
                DPP_GC_PREFIX . '-recipient-email' => ''
            );

            // Get previous values if they exist and use them as the defaults
            if ( $previous = dpp_get_gc_data( $post_id ) )
                $defaults = $previous;

            // Prepare an array of cleaned values
            $cleaned = array();

            if ( $amount = absint( $_POST[DPP_GC_PREFIX]['purchase-log-id'] ) )
                $cleaned[DPP_GC_PREFIX . '-purchase-log-id'] = $amount;

            if ( $amount = absint( $_POST[DPP_GC_PREFIX]['gift-certificate-item-id'] ) )
                $cleaned[DPP_GC_PREFIX . '-gift-certificate-item-id'] = $amount;

            if ( $_POST[DPP_GC_PREFIX]['amount-total'] > 0 && preg_match( "/^\d{0,9}(\.\d{0,2})?$/", $_POST[DPP_GC_PREFIX]['amount-total'] ) )
                $cleaned[DPP_GC_PREFIX . '-amount-total'] = number_format( $_POST[DPP_GC_PREFIX]['amount-total'], 2 );

            if ( $_POST[DPP_GC_PREFIX]['amount-left'] >= 0 && preg_match( "/^\d{0,9}(\.\d{0,2})?$/", $_POST[DPP_GC_PREFIX]['amount-left'] ) )
                $cleaned[DPP_GC_PREFIX . '-amount-left'] = number_format( $_POST[DPP_GC_PREFIX]['amount-left'], 2 );

            /* @todo: will need to add admin for uses at some point */
            if ( is_email( $_POST[DPP_GC_PREFIX]['recipient-email'] ) )
                $cleaned[DPP_GC_PREFIX . '-recipient-email'] = sanitize_email( $_POST[DPP_GC_PREFIX]['recipient-email'] );

            // Create array containing all values that combines the new values with the defaults
            $cleaned = wp_parse_args( $cleaned, $defaults );

            // Save all values individually
            foreach ( $cleaned as $key => $value )
                update_post_meta( $post_id, $key, $value );
        }

        return;
    }
}

$DPPGiftAdmin = new DPPGiftAdmin();

endif;