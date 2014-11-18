<?php

// TODO: Offer users the ability to generate prefixes/suffixes/etc. for the codes
function dpp_generate_code($length) {
    global $wpdb;

    // Use only 0-9 and A-Z
    $chars = DPP_GC_CODE_CHARS;
    $res = '';

    // Generate a random code $length long using the characters above
    for ($i = 0; $i < $length; $i++)
        $res .= $chars[mt_rand(0, strlen($chars)-1)];

    // Verify that the code doesn't exist
    if ( ! get_page_by_title( $res ) )
        return $res;
    else
        dpp_generate_code($length);
}

function dpp_get_gc_data( $post_id ) {
    return array(
        DPP_GC_PREFIX . '-purchase-log-id' => get_post_meta( $post_id, DPP_GC_PREFIX . '-purchase-log-id', true ),
        DPP_GC_PREFIX . '-gift-certificate-item-id' => get_post_meta( $post_id, DPP_GC_PREFIX . '-gift-certificate-item-id', true ),
        DPP_GC_PREFIX . '-amount-total' => get_post_meta( $post_id, DPP_GC_PREFIX . '-amount-total', true ),
        DPP_GC_PREFIX . '-amount-left' => get_post_meta( $post_id, DPP_GC_PREFIX . '-amount-left', true ),
        DPP_GC_PREFIX . '-uses' => get_post_meta( $post_id, DPP_GC_PREFIX . '-uses', true ),
        DPP_GC_PREFIX . '-recipient-email' => get_post_meta( $post_id, DPP_GC_PREFIX . '-recipient-email', true ),
    );
}

function dpp_gcs_applied() {
    if ( isset( $_SESSION[DPP_GC_PREFIX] ) && ( isset( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] ) && count( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] ) > 0 ) )
        return true;
    else
        return false;
}

/**
 * Get the current transaction page ID setting
 *
 * @access public
 * @return string Current value of the transaction ID page setting
 */
function dpp_get_transaction_results_page() {
    $options = get_option( DPP_GC_OPTIONS );
    if ( isset( $options['transaction-results-page'] ) )
        return $options['transaction-results-page'];
    else
        return '';
}

/**
 * Get the current tax option setting
 *
 * @access public
 * @return string Current value of the transaction ID page setting
 */
function dpp_get_tax_option() {
    $options = get_option( DPP_GC_OPTIONS );
    if ( isset( $options['tax-option'] ) )
        return $options['tax-option'];
    else
        return '';
}

/**
 * Get the current shipping option setting
 *
 * @access public
 * @return string Current value of the transaction ID page setting
 */
function dpp_get_shipping_option() {
    $options = get_option( DPP_GC_OPTIONS );
    if ( isset( $options['shipping-option'] ) )
        return $options['shipping-option'];
    else
        return '';
}