<?php

// Avoid name collision
if ( ! class_exists( 'DPPGiftCertificate' ) ) :

class DPPGiftCertificate {

    public $id;
    public $amount_total;
    public $amount_left;
    public $uses;
    public $date_purchased;
    public $code;
    public $recipient_email;

    function __construct() {

        // Create GCs if necessary
        add_action( 'wpsc_purchase_log_save', array( $this, 'create_gc_on_purchase' ));

        // Use GCs if necessary
        add_action( 'wpsc_confirm_checkout', array( $this, 'use_gcs' ) );
    }

    /**
     * Sets all instance variables.
     *
     * Finds a gc based on $code and sets all of the
     * instance variables to this gc's values. If the gc is
     * found based on the $code values, the function sets the
     * instance variables and returns false. If it is not found
     * no instance variables are set and the function returns false.
     *
     * @param $code string
     * @return bool
     */
    public function set_gc_values( $code ) {

        // Make sure the code is alphanumeric and it the appropriate length
        if ( ! ctype_alnum( $code ) || strlen( $code ) != DPP_GC_CODE_LENGTH )
            return false;

        // Attempt to locate GC
        if ( $gc = get_page_by_title( sanitize_title( $code ), OBJECT, DPP_GC_CPT ) ) {

            // The GC is valid; set the instance vars
            $data = dpp_get_gc_data( $gc->ID );
            $this->id = $gc->ID;
            $this->amount_total = $data[DPP_GC_PREFIX . '-amount-total'];
            $this->amount_left = $data[DPP_GC_PREFIX . '-amount-left'];
            $this->uses = maybe_unserialize( $data[DPP_GC_PREFIX . '-uses'] );
            $this->date_purchased = $gc->post_date;
            $this->code = $gc->post_title;
            $this->recipient_email = $data[DPP_GC_PREFIX . '-recipient-email'];

            return true;
        } else {
            return false;
        }
    }

    public function update_gc( $id, $data ) {
        global $wpdb;

        // TODO: validate values;
        if ( update_post_meta( $id, DPP_GC_PREFIX . '-amount-left', number_format( $data['amount-left'], 2 ) ) && update_post_meta( $id, DPP_GC_PREFIX . '-uses', $data['uses'] ) )
            return true;
        else
            return false;
    }

    public function create_gc( $purchase_log_id, $gc_item_id, $amount_total, $recipient_email = '' ) {
        global $wpdb;

        // Verify value and existence of the id
        if ( absint( $purchase_log_id ) ) {

            // Check for existence of the id
            if ( $log = $wpdb->get_row( 'SELECT id, date FROM ' . WPSC_TABLE_PURCHASE_LOGS . ' WHERE id=' . $purchase_log_id ) ) {
                $clean_purchase_log_id = $purchase_log_id;
                $clean_date = date('Y-m-d H:i:s', $log->date);
            } else {
                return false;
            }
        } else {
            return false;
        }

        // Validate item ID
        if ( absint( $gc_item_id ) ) {
            // Make sure that the ID is a post of type 'wpsc-product'
            if ( 'wpsc-product' == get_post_type( $gc_item_id ) )
                $clean_gc_item_id = $gc_item_id;
            else
                return false;
        } else {
            return false;
        }

        // Test amount total
        if ( $amount_total > 0 && preg_match( "/^\d{0,9}(\.\d{0,2})?$/", $amount_total ) )
            $clean_amount_total = number_format( $amount_total, 2 );
        else
            return false;

        // Test recipient_email
        if ( is_email( $recipient_email ) && !is_email_address_unsafe( $recipient_email ) || $recipient_email == '' )
            $clean_recipient_email = $recipient_email;
        else
            return false;

        // Prepare post data
        $post = array(
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_status' => 'publish',
            'post_title' => dpp_generate_code( DPP_GC_CODE_LENGTH ),
            'post_type' => DPP_GC_CPT
        );

        // Insert the post
        if ( $post_id = wp_insert_post( $post ) ) {
            // Prepare to insert the data
            $data = array(
                'purchase-log-id' => $clean_purchase_log_id,
                'gift-certificate-item-id' => $clean_gc_item_id,
                'amount-total' => $clean_amount_total,
                'amount-left' => $clean_amount_total,
                'uses' => '',
                'recipient-email' => $clean_recipient_email
            );

            // Add the meta data
            foreach ( $data as $key => $value )
                update_post_meta( $post_id, DPP_GC_PREFIX . '-' . $key, $value );

            return $post_id;

            // @todo: need to do some serious error catching and reporting if something goes wrong here
        } else {
            return false;
        }
    }

    public function create_gc_on_purchase($my_log) {
        global $wpsc_cart;

        $current_status = $my_log->get( 'processed' );

        if ( $current_status != 3 ){
            return false;
        }

        $my_session = $my_log->get('sessionid');
        $verify_session = get_transient('gc-'.$my_session.'-run');

        if ( $verify_session == 'yes' ) {
            return false;
        }

        $my_items = $my_log->get_cart_contents();

        if ( empty( $my_items ) ) {
            $cart_items = $wpsc_cart->cart_items;

            if ( empty( $cart_items ) ) {
                return false;
            }

            foreach ( $cart_items as $prd ) {
                $my_gc_items[] = array(
                    'prodid' => $prd->product_id,
                    'amount' => $prd->unit_price,
                    'quantity' => $prd->quantity
                );
            }
            set_transient('gc-'.$my_session.'-run','yes', 120);
        } else {
            foreach ( $my_items as $pdt ) {
                $my_gc_items[] = array('prodid' => $pdt->prodid, 'amount' => $pdt->price, 'quantity' => $pdt->quantity);
            }
            set_transient('gc-'.$my_session.'-run','yes', 120);
        }

        // Collect gift certificates

        $gcs = array();

        foreach ( $my_gc_items as $item ) {

            // Check for the gc meta data
            $make_gc = get_post_meta( $item['prodid'], '_'. DPP_GC_PREFIX . '-make-gc', true );

            // It is a gift certificate
            if ( isset( $make_gc ) && $make_gc == 'yes' ) {

                // Make a new GC for each one purchased
                for ( $i = 0; $i < $item['quantity']; $i++ ) {
                    $gcs[] = array(
                        'id' => $item['prodid'],
                        'amount' => $item['amount']
                    );
                }

                /*  Alternatively, a GC with multiple quantity can be combined into one larger coupon.
                    Might also be nice to have an option to enable either or
                $gcs[] = array(
                    'id' => $item['prodid'],
                    'amount' => number_format($item['price'] * $item['quantity'], 2)
                );*/
            }
        }

        // TODO: need to find a way to only add coupon(s) if they don't exist. This becomes difficult because when multiple coupons are purchased, they are identical except for their code
        // I think I can use wp_options to make this happen
        // Or I can store it in the session itself
        // Add Gift Certificate to database
        if ( count( $gcs ) > 0 ) {
            foreach ( $gcs as $value ) {
                $post_id = $this->create_gc( $my_log->get('id'), $value['id'], $value['amount'] );
                $this->send_email( $post_id );
            }
        }
    }

    function send_email( $post_id ) {
        global $wpdb;

        // Only send an email if one has not already been sent
        if ( get_post_meta( $post_id, DPP_GC_PREFIX . '-email-sent', true ) )
            return false;

        // Create the message
        //$msg = 'Congratulations! You have purchased a GC for ' . get_post_meta( $post_id, DPP_GC_PREFIX . '-amount-total', true ) .'. Your coupon code is
        //' . get_the_title( $post_id ) .'.';

        // Get the buyer details
        $buyers_email = wpsc_get_buyers_email( get_post_meta( $post_id, DPP_GC_PREFIX . '-purchase-log-id', true ) );
        $usersql = "SELECT `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`value`, `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`name`, `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`unique_name` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` LEFT JOIN `" . WPSC_TABLE_SUBMITED_FORM_DATA . "` ON `" . WPSC_TABLE_CHECKOUT_FORMS . "`.id = `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`form_id` WHERE `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`log_id`=" . get_post_meta( $post_id, DPP_GC_PREFIX . '-purchase-log-id', true ) . " ORDER BY `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`checkout_order`";
        $userinfo = $wpdb->get_results( $usersql, ARRAY_A );

        foreach ( (array) $userinfo as $input_row ) {

            if ( stristr( $input_row['unique_name'], 'shipping' ) ) {
                $shippinginfo[$input_row['unique_name']] = $input_row;
            } elseif ( stristr( $input_row['unique_name'], 'billing' ) ) {
                $billingdetails[$input_row['unique_name']] = $input_row;
            } else {
                $additionaldetails[$input_row['name']] = $input_row;
            }
        }

        $msg = $this->email( $billingdetails['billingfirstname']['value'] . ' ' . $billingdetails['billinglastname']['value'], get_post_meta( $post_id, DPP_GC_PREFIX . '-amount-total', true ), get_the_title( $post_id ) );

        // Attempt sending the email and record the results
        add_filter( 'wp_mail_content_type', create_function( '', 'return "text/html";' ) );
        if ( wp_mail( $buyers_email, 'GC Purchase', $msg ) )
            update_post_meta( $post_id, DPP_GC_PREFIX . '-email-sent', 1 );
        else
            update_post_meta( $post_id, DPP_GC_PREFIX . '-email-sent', 0 );
    }

    function use_gcs() {

        global $purchase_log, $errorcode, $sessionid, $cart, $wp_query;

        // Ensure that components necessary for the request are available

        if ( $errorcode != 0 || is_null($sessionid) )
            return false;

        // Check for existence of gcs in the session
        if ( ! isset( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] ) )
            return false;

        // TODO: consider adding a "status" field to the database. This would allow for an easy way to see if the GC is used or not
        // TODO: more testing of this is needed

        // Record use

        $updated = false;
        foreach ( $_SESSION[DPP_GC_PREFIX]['gift_certificates'] as $id => $gc ) {

            // Get current uses
            $uses = maybe_unserialize( $gc->uses );

            if ( ! is_array( $uses ) )
                $uses = array();

            // Get uses data
            $uses[] = array(
                $purchase_log->id => array(
                    'using' => $gc->using,
                    'reduction_type' => $gc->reduction_type
                )
            );

            // Calculate amount left
            $amount_left = (float) ( $gc->amount_left - $gc->using );

            // Gather data to submit
            $data = array(
                'uses' => maybe_serialize( $uses ),
                'amount-left' => $amount_left
            );

            // Update GC
            if ( $this->update_gc( $id, $data ) )
                $updated = true;

            // TODO: Once the GC is successfully added to the DB, it needs to be removed from the session
        }

        // Remove GCs
        if ( $updated )
            unset( $_SESSION[DPP_GC_PREFIX] );
    }

    function email( $name, $amount, $code ) {

        return '<html>
            <head>
            <title>Gift Certificate</title>
            <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
            </head>
            <body bgcolor="#bebebe" leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">
            <table width="510" height="671" border="0" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF" align="center">
                <tr>
                    <td width="510" height="5" colspan="7" valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="510" height="5" border="0"></td>
                </tr>
                <tr>
                    <td width="5" height="5" rowspan="12" valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="3" height="5" border="0"></td>
                    <td width="500" height="5" colspan="5" valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="500" height="5" border="0"></td>
                    <td width="5" height="5" rowspan="12" valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="3" height="5" border="0"></td>
                </tr>
                <tr>
                    <td colspan="3" valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/gc_f_top.jpg" width="462" height="119" border="0"></td>
                    <td width="10" height="522" colspan="2" rowspan="9" valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="10" height="522" border="0"></td>
                </tr>
                <tr>
                    <td colspan="2" rowspan="3"  valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/gc_f_bottom.jpg" width="291" height="181" border="0"></td>
                    <td height="38" valign="top">&nbsp;
                        </td>
                </tr>
                <tr>
                    <td height="75" style="border-bottom-style:dotted; border-bottom-width:1px;" valign="bottom">&nbsp;
                        ' . $name . '</td>
                </tr>
                <tr>
                    <td height="68" valign="top">&nbsp;
                        </td>
                </tr>
                <tr>
                    <td colspan="3" valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="462" height="69" border="0"></td>
                </tr>
                <tr>
                    <td valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/gc_amount.jpg" width="205" height="35" border="0"></td>
                    <td colspan="2" style="border-bottom-style:dotted; border-bottom-width:1px;" valign="bottom">&nbsp;
                        $' . $amount . '</td>
                </tr>
                <tr>
                    <td width="462" height="36" colspan="3" valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="462" height="36" border="0"></td>
                </tr>
                <tr>
                    <td valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/gc_number.jpg" width="205" height="32" border="0"></td>
                    <td colspan="2" style="border-bottom-style:dotted; border-bottom-width:1px;" valign="bottom">&nbsp;
                        ' . $code . '</td>
                </tr>
                <tr>
                    <td width="462" height="50" colspan="3" valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="462" height="50" border="0"></td>
                </tr>
                <tr>
                    <td colspan="5" height="143" valign="top">
                        <img src="' . DPP_IMAGES_PATH . '/gc_found_bottom.jpg" width="500" height="143" border="0"></td>
                </tr>
                <tr>
                    <td valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="205" height="1" border="0"></td>
                    <td valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="86" height="1" border="0"></td>
                    <td valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="171" height="1" border="0"></td>
                    <td valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="26" height="1" border="0"></td>
                    <td valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="5" height="1" border="0"></td>
                </tr>
                <tr>
                    <td width="510" height="5" colspan="7" valign="top" bgcolor="#aeaeae">
                        <img src="' . DPP_IMAGES_PATH . '/spacer.gif" width="510" height="5" border="0"></td>
                </tr>
            </table>
            </body>
            </html>';
    }
}
global $dpp_gc;
$dpp_gc = new DPPGiftCertificate();

endif; // Die silently