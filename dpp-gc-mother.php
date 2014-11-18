<?php
/*
Plugin Name: WordPress E-Commerce Gift Certificate Plugin
Description: Adds the ability to create and utilize gift certificates within the WordPress E-Commerce environment.
Author: Luis Cruz produced by Dew Point Productions, Inc.
Author URI:
Plugin URI: http://www.dewpointproductions.com
Version: 2.1

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

// Include the configuration
include('includes/configuration.php');

// Version check
global $wp_version;
$exit_msg = __("The WP eCommerce Gift Certificate plugin requires the use of Wordpress 3.0 or higher. Please update!", DPP_GC_DOMAIN );
if(version_compare($wp_version, "3.0", "<")) exit($exit_msg);

define( 'DPP_IMAGES_PATH', plugins_url( '/images', __FILE__ ) );

// @todo: test for correct version of WPEC

// Avoid name collision
if ( ! class_exists( 'DPPGiftCertificateMother' ) ) :

class DPPGiftCertificateMother {

    public $tax_options = array();

    public $shipping_options = array();

    /**
     * Class constructor
     */
    public function __construct() {
        // Add jquery for checkout page
        add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

        // General setup
        add_action( 'init', array( $this, 'setup' ) );

		// Add settings page
		add_action ( 'admin_menu', array( $this, 'add_settings_actions' ) );

		// Setup settings fields
		add_action( 'admin_init', array( $this, 'settings_init' ) );

        // Attach metabox
        add_action( 'add_meta_boxes_wpsc-product', array( $this, 'prepare_meta_box' ), 10, 1 );

        // Save post metabox
        add_action( 'save_post', array( $this, 'save_meta_box' ), 100, 2 );

        // Include other DPP Gift Certificate files
        include_once( 'includes/functions.php' );
        include_once( 'classes/gift-certificate.class.php' );
        include_once( 'classes/checkout.class.php' );
        include_once( 'dpp-admin.php' );
    }

    /**
     * Adds the script that allows for bypassing checkout.
     *
     * @return void
     */
    function wp_enqueue_scripts() {
        wp_enqueue_script(
            DPP_GC_PREFIX . '-checkout',
            plugins_url( 'js/checkout.js', __FILE__ ),
            array( 'jquery' ),
            DPP_GC_VERSION,
            false
        );
    }

    /**
     * Runs require processes on init.
     *
     * @return void
     */
    function setup() {
        // Prepare the class variables
        $this->prepare_vars();
    }

    /**
     * Sets up the class vars.
     *
     * @return void
     */
    function prepare_vars() {
        $this->tax_options = array(
            array(
                'key' => 'after',
                'value' => __( 'Apply Discount After Calculating Taxes', DPP_GC_DOMAIN )
            ),
            array(
                'key' => 'before',
                'value' => __( 'Apply Discount Before Calculating Taxes', DPP_GC_DOMAIN )
            )
        );
        $this->shipping_options = array(
            array(
                'key' => 'yes',
                'value' => __( 'Apply Discount to Shipping', DPP_GC_DOMAIN )
            ),
            array(
                'key' => 'no',
                'value' => __( 'Do Not Apply Discount to Shipping', DPP_GC_DOMAIN )
            )
        );
    }

    /**
     * Calls add_meta_box
     *
     * @param $post
     * @return void
     */
    function prepare_meta_box( $post ) {
        add_meta_box(
            DPP_GC_PREFIX . 'meta-box',
            __( 'Gift Certificate Options', DPP_GC_DOMAIN ),
            array( &$this, 'display_meta_box' ),
            'wpsc-product',
            'side',
            'high'
        );
    }

    /**
     * Generates the meta_box HTML
     *
     * @param $post
     * @param $args
     * @return void
     */
    function display_meta_box( $post, $args ) {
        // Secure the form
        wp_nonce_field( plugins_url( __FILE__ ), DPP_GC_PREFIX . '-meta-box' );

        // Determine if checked or not
        $checked = ( get_post_meta( $post->ID, '_' . DPP_GC_PREFIX . '-make-gc', true ) == 'yes' ) ? ' checked="checked"' : '';
        ?>
            <p>
                <input type="checkbox" name="_<?php echo DPP_GC_PREFIX; ?>-make-gc" value="yes"<?php echo $checked; ?> />&nbsp;&nbsp;
                <label for="_<?php echo DPP_GC_PREFIX;?>-make-gc"><?php _e( 'Make this product a Gift Certificate', DPP_GC_DOMAIN ); ?></label>
            </p>
        <?php
    }

    /**
     * Saves the post meta box
     *
     * @param $post_id
     * @param $post
     * @return
     */
    function save_meta_box( $post_id, $post ) {
        // Exit on autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;

        // check capabilities
        if ( ! current_user_can( 'edit_posts' ) ) return $post_id;

        // Check for nonce
        if ( isset( $_POST[DPP_GC_PREFIX . '-meta-box'] ) && check_admin_referer( plugins_url( __FILE__ ), DPP_GC_PREFIX . '-meta-box' ) ) {
            // Check for acceptable values
            if ( isset( $_POST['_'. DPP_GC_PREFIX . '-make-gc'] ) ) {
                if ( $_POST['_'. DPP_GC_PREFIX . '-make-gc'] == 'yes' )
                    update_post_meta( $post_id, '_'. DPP_GC_PREFIX . '-make-gc', $_POST['_'. DPP_GC_PREFIX . '-make-gc'] );
            } else {
                delete_post_meta( $post_id, '_'. DPP_GC_PREFIX . '-make-gc' );
            }
        }
    }

	/**
	 * Sets up the submenu page
	 *
	 * @access public
	 * @return void
	 */
	function add_settings_actions() {
		// Add the options page
		add_submenu_page( 'options-general.php', __( 'Gift Certificate Settings', DPP_GC_DOMAIN ), __( 'Gift Certificate Settings', DPP_GC_DOMAIN ), 'manage_options', DPP_GC_PREFIX . '-settings', array( &$this, 'settings_page' ) );
	}

	/**
	 * Defines the settings page
	 *
	 * @access public
	 * @return void
	 */
	function settings_page() {
	?>
		<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div><h2><?php _e( 'Gift Certificate Settings', DPP_GC_DOMAIN ); ?></h2>
		<form action="options.php" method="post">
		<?php settings_fields( DPP_GC_PREFIX . '-settings' ); ?>
		<?php do_settings_sections( DPP_GC_PREFIX . '-fields' ); ?>
		<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" /></p>
		</form></div>
	<?php
	}

	/**
	 * Registers settings and adds settings fields
	 *
	 * @access public
	 * @return void
	 */
	function settings_init() {
		register_setting( DPP_GC_PREFIX . '-settings', DPP_GC_OPTIONS, array( &$this, 'settings_validate' ) );
		add_settings_section( DPP_GC_PREFIX . '-options', __( 'General', DPP_GC_DOMAIN ), array( &$this, 'settings_page_text' ), DPP_GC_PREFIX . '-fields' );

		// Add transaction results page input
		add_settings_field( DPP_GC_PREFIX . '-transaction-results-page', __( 'Transaction Results Page ID', DPP_GC_DOMAIN ), array( &$this, 'transaction_results_page_field' ), DPP_GC_PREFIX . '-fields', DPP_GC_PREFIX . '-options' );
		add_settings_field( DPP_GC_PREFIX . '-tax-option', __( 'Tax Discount Settings', DPP_GC_DOMAIN ), array( &$this, 'tax_option_field' ), DPP_GC_PREFIX . '-fields', DPP_GC_PREFIX . '-options' );
        add_settings_field( DPP_GC_PREFIX . '-shipping-option', __( 'Shipping Discount Settings', DPP_GC_DOMAIN ), array( &$this, 'shipping_option_field' ), DPP_GC_PREFIX . '-fields', DPP_GC_PREFIX . '-options' );
	}

	/**
	 * Validates the settings before adding them to the database
	 *
	 * @access public
	 * @param array $input Values that will be sent to the database
	 * @return array $valid Array of validated values
	 */
	function settings_validate( $input ) {
		// Make sure the transaction result page ID is numeric
		$valid['transaction-results-page'] = ( isset( $input['transaction-results-page'] ) && is_numeric( $input['transaction-results-page'] ) ) ? $input['transaction-results-page'] : dpp_get_transaction_results_page();

        // Ensure that the tax option is of appropriate value
		if ( isset( $input['tax-option'] ) ) {
			$valid_tax_option = false;
			foreach ( $this->tax_options as $key => $value ) {
				if ( $value['key'] == $input['tax-option'] ) $valid_tax_option = true;
			}
            $valid['tax-option'] = ( $valid_tax_option ) ? $input['tax-option'] : $this->tax_options[0]['key'];
		}

        // Ensure that the shipping option is of appropriate value
		if ( isset($input['shipping-option'] ) ) {
			$valid_shipping_option = false;
			foreach ( $this->shipping_options as $key => $value ) {
				if ( $value['key'] == $input['shipping-option'] ) $valid_shipping_option = true;
			}
            $valid['shipping-option'] = ( $valid_shipping_option ) ? $input['shipping-option'] : $this->shipping_options[0]['key'];
		}

		return $valid;
	}

	/**
	 * Controls the text for the Rush Order settings section
	 *
	 * @access public
	 * @return void
	 */
	function settings_page_text() {
	?>
		<p><?php _e( 'Gift Certificate Options', DPP_GC_DOMAIN ); ?></p>
	<?php
	}

	/**
	 * Controls the input for transaction results page
	 *
	 * @access public
	 * @return void
	 */
	function transaction_results_page_field() {
		// Get the current email setting
		$current_value = dpp_get_transaction_results_page();
	?>
		<fieldset><legend class="screen-reader-text"><span><?php _e( 'Transaction Results Page', DPP_GC_DOMAIN ); ?></span></legend>
			<?php
				// Get all WP pages to display as select options
				$args = array(
					'post_type' => 'page',
					'posts_per_page' => -1,
                    'post_status' => 'any'
				);
				$query = new WP_Query( $args );
				$query->query;

				if ( $query->have_posts() ) : ?>
					<select name="<?php echo DPP_GC_OPTIONS; ?>[transaction-results-page]">
						<?php while( $query->have_posts() ) : $query->the_post(); global $post; ?>
							<option value="<?php echo $post->ID; ?>"<?php if ( $current_value == $post->ID ) : ?> selected="selected"<?php endif;?>><?php the_title(); ?> (ID = <?php echo $post->ID; ?>)</option>
						<?php endwhile; ?>
					</select>
				<?php endif; ?>
				<br />
			<span class="description"><?php _e( 'The page that contains the [transactionresults] shortcode', DPP_GC_DOMAIN ); ?></span>
		</fieldset>
	<?php
	}

	/**
	 * Controls the input for the tax options
	 *
	 * @access public
	 * @return void
	 */
	function tax_option_field() {
		// Get the current email setting
		$current_value = dpp_get_tax_option();
	?>
		<fieldset><legend class="screen-reader-text"><span><?php _e( 'Tax Option', DPP_GC_DOMAIN ); ?></span></legend>
            <?php foreach ( $this->tax_options as $key => $value ) : ?>
                <label title="<?php echo DPP_GC_OPTIONS; ?>-<?php echo $value['key']; ?>"><input type="radio" name="<?php echo DPP_GC_OPTIONS; ?>[tax-option]" value="<?php echo $value['key']; ?>" <?php if ( $current_value == $value['key'] ) : ?>checked='checked'<?php endif; ?> /> <span><?php echo $value['value']; ?></span></label><br />
            <?php endforeach; ?>
		</fieldset>
	<?php
	}

	/**
	 * Controls the input for the shipping options
	 *
	 * @access public
	 * @return void
	 */
	function shipping_option_field() {
		// Get the current email setting
		$current_value = dpp_get_shipping_option();
	?>
		<fieldset><legend class="screen-reader-text"><span><?php _e( 'Shipping Option', DPP_GC_DOMAIN ); ?></span></legend>
            <?php foreach ( $this->shipping_options as $key => $value ) : ?>
                <label title="<?php echo DPP_GC_OPTIONS; ?>-<?php echo $value['key']; ?>"><input type="radio" name="<?php echo DPP_GC_OPTIONS; ?>[shipping-option]" value="<?php echo $value['key']; ?>" <?php if ( $current_value == $value['key'] ) : ?>checked='checked'<?php endif; ?> /> <span><?php echo $value['value']; ?></span></label><br />
            <?php endforeach; ?>
		</fieldset>
	<?php
	}
}

// Instantiate the class
$DPPGiftCertificateMother = new DPPGiftCertificateMother();

else :
    exit(__( 'Class DPPGiftCertificateMother already exists.', DPP_GC_DOMAIN ));
endif;