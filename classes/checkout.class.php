<?php

// Avoid name collision

if ( ! class_exists( 'DPPCheckout' ) ) {

class DPPCheckout {

    private $calculated = false;
    private $calculation_attempts = 0;
    private $true_coupons_amount = 0;
    private $total_before_reduction;
    private $new_total;
    private $display_error_msg;

    /**
     * Initiates the addition of a new GC.
     *
     * Important that this function never does any of the GC logic. It merely validates the the call to add a new GC.
     * If everything looks good, it calls the "add_gc" function to validate and add a new GC to the session.
     *
     * @uses add_gc
     * @return void
     */
    function process_gc() {
        // If attempting to add a new GC, add it

        if ( isset( $_POST[DPP_GC_PREFIX . '-apply'] ) && wp_verify_nonce( $_POST[DPP_GC_PREFIX . '-apply'], plugins_url( __FILE__ ) ) ) {
            // Make sure code is there, then add_gc
            if ( isset( $_POST[DPP_GC_PREFIX . '-code'] ) && !empty( $_POST[DPP_GC_PREFIX . '-code'] ) ){
                $this->add_gc( $_POST[DPP_GC_PREFIX . '-code'] );}
            else {
                $this->display_error_msg = 'Please enter a gift certificate code';
            }
        }
    }

    /**
     * Validates and adds a GC to the session.
     *
     * This function validates the incoming request to add a GC to the session. It only adds the GC to the session.
     * It does not affect any of the other GCs already in the session. It is extremely important that this function
     * does not affect other coupons. The total needs to be calculated by "get_gc_total" as it's executed on a
     * safer hook that will allow it to flexibly compensate for changes in the cart (add/remove items, shipping,
     * coupons, taxes, etc.).
     *
     * @param $code The alphanumeric code for the coupon.
     * @return void|bool
     */
    function add_gc( $code ) {
        // Create new instance of GC class
        $gc = new DPPGiftCertificate();
        // Validate and set values
        if ( $gc->set_gc_values( $code ) ) {
            // Add the GC to the session if this GC has not already been added
            if ( ! isset( $_SESSION[DPP_GC_PREFIX][$gc->id] ) ) {
                // Add to session
                $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$gc->id] = $gc;

                // Add to coupons_name
                //$this->add_gc_to_coupons_name( $gc->id );
                $this->first_run = false;
            }
        }
        else {
            $this->display_error_msg = 'Please enter a valid gift certificate code';
        }
    }

    function get_gc_total( $total, $subtotal, $shipping, $tax, $coupons_amount ) {
        global $wpsc_cart;
        // If the cart is set to use coupons, exit
        if ( wpsc_uses_coupons() && ! empty( $wpsc_cart->coupons_name ) ) {
            return $total;
        }

        global $wpsc_cart;

        $this->calculation_attempts++;

        // Calculate the "true" coupon amount; i.e., the discount calculated as the "Coupon" as WPEC refers to it

        /**
         * Will have to ask users to change:
         * - <?php if(wpsc_uses_coupons() && (wpsc_coupon_amount(false) > 0)): ?>
         * to:
         * - <?php if( ( wpsc_uses_coupons() && (wpsc_coupon_amount(false) > 0) ) || dpp_gcs_applied() ): ?>
         *
         * This enables GC discount values to be displayed immediately after they are added
         */

        // Major problems occur when this total is calculated multiple times (i.e., the discount--particularly
        // the the coupons_amount--gets applied multiple times. The "calculated" class variables keeps track of this
        // to avoid applying the discount twice.
        if ( $this->calculated || !empty($this->display_error_msg)) {
            return $total;
        }

        // The actual WPEC coupon is stored separately so that it can be differentiated for the other discount
        if ( isset( $_POST['coupon_num'] ) ) {
            $_SESSION[DPP_GC_PREFIX]['true_coupons_amount'] = $coupons_amount;
        }

        // Need to set the true coupons amount to be used throughout script
        $this->true_coupons_amount = ( isset( $_SESSION[DPP_GC_PREFIX]['true_coupons_amount'] ) ) ? $_SESSION[DPP_GC_PREFIX]['true_coupons_amount'] : 0;

        // Set local total
        $this->new_total = $total;

        // Debug output
        if ( DPP_GC_DEBUG ) {
            echo 'Initial Total: ' . $this->new_total . '</br>';
            echo 'Initial Subtotal: ' . $subtotal . '</br>';
            echo 'Initial Shipping: ' . $shipping . '</br>';
            echo 'Initial Tax: ' . $tax . '</br>';
            echo 'Initial Coupons Amount: ' . $coupons_amount . '</br>';
            echo 'Initial True Coupons Amount: ' . $this->true_coupons_amount . '</br>';
            echo 'Initial Coupons Amount in $wpsc_cart: ' . $wpsc_cart->coupons_amount . '</br>';
            //echo 'Initial Coupons Name in $wpsc_cart: ' . var_dump($wpsc_cart->coupons_name) . '</br>';
        }

        // Calculate the reduction
        if ( isset( $_SESSION[DPP_GC_PREFIX] ) && ( isset( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] ) && count( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] ) > 0 ) ) {

            // Initialize reduction
            $reduction_max = 0;

            // Cycle through GCs and get reduction amounts
            foreach ( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] as $key => $gc )
                $reduction_max += $gc->amount_left;

            // Calculate the total
            $this->calculate_total( dpp_get_tax_option(), dpp_get_shipping_option(), $total, $subtotal, $shipping, $tax, $this->true_coupons_amount, $reduction_max );

            // Determine amounts of the individual GCs to use
            if ( $this->new_total == 0 ) {
                // The amount that was actually reduced
                $reduction = $this->total_before_reduction - $this->new_total;

                // Mark gift certificates with the amounts used from each
                $this->mark_used_amounts( $reduction );
            } else {

                // Use all of each GC
                foreach ( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] as $id => $gc ) {
                    $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->using = $gc->amount_left;
                    $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->reduction_type = 'full';
                }

                // The overall reduction is the same as the maximum reduction
                $reduction = $reduction_max;
            }

            // Adding the reduction amount to the coupon amount to make the discount show
            // Note: this is hacky. I don't want to do this, but there is no other way, other
            // than getting into the core, that will allow to tap into the "discount" flow
            //if ( ! $this->calculated )

            $wpsc_cart->coupons_amount = $reduction + $this->true_coupons_amount;

            /*
             * Situations
                - First visit to CO page
                - After enter GC
                - Any reload when a GC is in the session
                - After Coupon
                - Any reload when a Coupon is in the session
             */

            // Update session data
            $_SESSION[DPP_GC_PREFIX]['count'] = count( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] );
            $_SESSION[DPP_GC_PREFIX]['reduction'] = $reduction;
            $_SESSION[DPP_GC_PREFIX]['reduction_max'] = $reduction_max;
        }

        // Note that the total has been calculated with GC reductions so they are not doubled
        $this->calculated = true;
        return $this->new_total;
    }

    /**
     * Calculates the cart total.
     *
     * Total is primarily dependent upon the admin's choice to apply the discount
     * before or after the taxes are applied and whether or not to apply the discount to
     * the shipping rate.
     *
     * @param $tax_option string 'before' or 'after
     * @param $shipping_option string 'yes' or 'no'
     * @param $total float current cart total
     * @param $subtotal float current cart subtotal
     * @param $shipping float current cart shipping
     * @param $tax float current cart tax
     * @param $true_coupons_amount float current coupons_amount
     * @param $reduction float reduction amount
     * @todo  need to make sure all formulas are working
     *
     * @return string
     */
    function calculate_total( $tax_option, $shipping_option, $total, $subtotal, $shipping, $tax, $true_coupons_amount, $reduction ) {

        // Apply the discount after taxes have been applied and to the shipping rate
        if ( $tax_option == 'after' && $shipping_option == 'yes' ) {
            $this->total_before_reduction = $subtotal + $tax + $shipping - $true_coupons_amount;
            $new_total = $this->total_before_reduction - $reduction;
            $this->new_total = ( $new_total > 0 ) ? $new_total : 0;
        }

        // Apply the discount after taxes have been applied, but not to the shipping rate
        elseif ( $tax_option == 'after' && $shipping_option == 'no' ) {
            // Get the total before the reduction
            $total_before_reduction = $subtotal + $tax - $true_coupons_amount;
            $total_before_reduction = ( $total_before_reduction > 0 ) ? $total_before_reduction : 0;
            $this->total_before_reduction = $total_before_reduction + $shipping;

            // Apply the discount without the shipping factored in
            $new_total = $subtotal + $tax - $true_coupons_amount - $reduction;
            $new_total = ( $new_total > 0 ) ? $new_total : 0;

            // Add the shipping now that the discounts have been applied
            $this->new_total = $new_total + $shipping;
        }

        // Apply the discount before the taxes have been applied and to the shipping rate
        elseif ( $tax_option == 'before' && $shipping_option == 'yes' ) {

            // @todo need to generate total before reduction
            // Get tax rate for the cart
            global $wpsc_cart;
            $tax_rate = $wpsc_cart->percentage;

            // Reduce the total by the coupons and reduction amount before applying taxes
            $new_total = $subtotal - $true_coupons_amount - $reduction;

            // Get the remaining amount; basically, if the number is negative, it represents the amount of GC that is left
            $remaining_reduction = ( $new_total < 0 ) ? abs( $new_total ) : 0;

            // Get the current total; if less than or equal to 0, no taxes need to be added, otherwise, add taxes
            $new_total = ( $new_total <= 0 ) ? 0 : $new_total * ( 1 + $tax_rate / 100 );

            // Add in the shipping amount, but be sure to use the remaining GC amount if necessary
            $new_total = $new_total + $shipping - $remaining_reduction;

            // Get the final total
            $new_total = ( $new_total < 0 ) ? 0 : $new_total;

            return $new_total;
        }

        // Apply the discount before the taxes are applied, but not to the shipping rate
        elseif ( $tax_option == 'before' && $shipping_option == 'no' ) {

            // @todo need to generate total before reduction
            // Get tax rate for the cart
            global $wpsc_cart;
            $tax_rate = $wpsc_cart->percentage;

            // Reduce the total by the coupons and reduction amount before applying taxes
            $new_total = $subtotal - $true_coupons_amount - $reduction;

            // Get the current total; if less than or equal to 0, no taxes need to be added, otherwise, add taxes
            $new_total = ( $new_total <= 0 ) ? 0 : $new_total * ( 1 + $tax_rate / 100 );

            // Add in the shipping amount
            $new_total = $new_total + $shipping;
            return $new_total;
        } else {
            $this->total_before_reduction = $subtotal + $tax + $shipping - $true_coupons_amount;
            $new_total = $this->total_before_reduction - $reduction;
            $this->new_total = ( $new_total > 0 ) ? $new_total : 0;
        }
    }

    function mark_used_amounts( $running_total ) {

        // Loop through GCs, using them in order
        foreach ( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] as $id => $gc ) {

            // If there is still total to decrement, do so
            if ( $running_total > 0 ) {

                // If the running total is greater than the amount left on this GC, take it all
                if ( $running_total > $gc->amount_left ) {

                    // Subtract full amount
                    $running_total -= $gc->amount_left;

                    // Mark the amount being used
                    $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->using = $gc->amount_left;

                    // Mark GC as fully used
                    $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->reduction_type = 'full';
                }

                // The running total is less than the amount of the GC
                else {
                    // GC will use the amount of the running total
                    $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->using = $running_total;

                    // Mark as a partial reduction
                    $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->reduction_type = 'partial';

                    // Set running total to 0 to exit the loop
                    $running_total = 0;
                }
            }
            // Somehow, there is a GC that is not being used at all; record just in case
            else {
                $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->using = 0;
                $_SESSION[DPP_GC_PREFIX]['gift_certificates'][$id]->reduction_type = 'unused';
            }
        }
    }

    function form_field() {
        global $wpsc_cart;

        // Do not display if coupons are being used
        if ( wpsc_uses_coupons() && ! empty( $wpsc_cart->coupons_name ) ) {
            return;
        }
    ?>
            <form action="" method="post">
                <?php wp_nonce_field( plugins_url( __FILE__ ), DPP_GC_PREFIX . '-apply' ); ?>
                <?php if (!empty($this->display_error_msg)){
                    echo '<p style="color: red;">'.$this->display_error_msg.'</p>';
                } ?>
                <label for="<?php echo DPP_GC_PREFIX; ?>-code"><?php _e( 'Enter Gift Certificate Code', DPP_GC_DOMAIN ); ?>:</label>&nbsp;
                <input type="text" name="<?php echo DPP_GC_PREFIX;?>-code" id="<?php echo DPP_GC_PREFIX;?>-code" />&nbsp;
                <input type="submit" name="<?php echo DPP_GC_PREFIX;?>-submit" id="<?php echo DPP_GC_PREFIX;?>-submit" value="<?php _e( 'Apply Gift Certificate', DPP_GC_DOMAIN ); ?>" />
            </form>
        <?php
    }

    // Will show collected GCs

    function list_applied_gcs() {
        global $wpsc_cart;
        // TODO: possibly allow for GCs to be deleted here
        // TODO: clean up UI
        // TODO: show error if no more GCs can be applied
        if ( wpsc_uses_coupons() && ! empty( $wpsc_cart->coupons_name ) ) return;

        // Only display if they are available
        if ( isset($_SESSION[DPP_GC_PREFIX]['gift_certificates'] ) && count( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] ) > 0 ) {
            // Print out each GC
            foreach ( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] as $key => $gc )
                echo 'GC#: ' . $gc->code . ', Original Amount: ' . $gc->amount_total . ', Amount Left: ' . $gc->amount_left . '<br />';
        }
    }
}

} // Die silently

if ( class_exists( 'DPPCheckout' ) ) {
    $dpp_checkout = new DPPCheckout();
}

if ( isset( $dpp_checkout ) ) {
        // Process GCs
        add_action( 'init', array( $dpp_checkout, 'process_gc' ) );

        // Filter the total amount
        add_filter( 'wpsc_calculate_total_price', array( $dpp_checkout, 'get_gc_total' ), 200, 5 ); // Process late to make sure everything else is set first

        // List current GC's applied to cart
        add_action( 'wpsc_before_form_of_shopping_cart', array( $dpp_checkout, 'list_applied_gcs' ), 1 );

        // Calculate the total. This takes care of edge cases where the total does not get calculated before the discount is shown
        add_action( 'wpsc_before_form_of_shopping_cart', 'wpsc_cart_total', 1, 1 );

        // Add form field on checkout page
        add_action( 'wpsc_before_form_of_shopping_cart', array( $dpp_checkout, 'form_field' ), 200 ); // TODO: develop way to add field if action is not present
}