<?php

// https://stackoverflow.com/questions/48616541/add-custom-order-status-and-send-email-on-status-change-in-woocommerce

// register a custom post status 'awaiting-aeur-payment' for Orders
add_action( 'init', 'register_nxter_woo_post_status', 20 );
function register_nxter_woo_post_status() {
    register_post_status( 'wc-awaiting-aeur-payment', array(
        'label'                     => _x( 'Awaiting AEUR payment', 'Order status', 'wc-ardor-gateway' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true
    ) );
}

// Adding custom status 'awaiting-aeur-payment' to order edit pages dropdown
add_filter( 'wc_order_statuses', 'custom_wc_order_statuses', 20, 1 );
function custom_wc_order_statuses( $order_statuses ) {
    $order_statuses['wc-awaiting-aeur-payment'] = __( 'Awaiting AEUR payment', 'Order status', 'wc-ardor-gateway' );
    return $order_statuses;
}

// Adding custom status 'awaiting-aeur-payment' to admin order list bulk dropdown
add_filter( 'bulk_actions-edit-shop_order', 'custom_dropdown_bulk_actions_shop_order', 20, 1 );
function custom_dropdown_bulk_actions_shop_order( $actions ) {
    $actions['mark_awaiting-aeur-payment'] = __( 'Mark Awaiting AEUR payment', 'wc-ardor-gateway' );
    return $actions;
}

// Sending an email notification when order get 'awaiting-aeur-payment' status
add_action('woocommerce_order_status_awaiting-aeur-payment', 'backorder_status_custom_notification', 20, 2);
function backorder_status_custom_notification( $order_id, $order ) {
    // HERE below your settings
    $heading   = __('Your Awaiting AEUR payment order','wc-ardor-gateway');
    $subject   = '[{site_title}] Awaiting AEUR payment order ({order_number}) - {order_date}';

    // Getting all WC_emails objects
    $mailer = WC()->mailer()->get_emails();

    // Customizing Heading and subject In the WC_email processing Order object
    $mailer['WC_Email_Customer_Processing_Order']->heading = $heading;
    $mailer['WC_Email_Customer_Processing_Order']->settings['heading'] = $heading;
    $mailer['WC_Email_Customer_Processing_Order']->subject = $subject;
    $mailer['WC_Email_Customer_Processing_Order']->settings['subject'] = $subject;

    // Sending the customized email
    $mailer['WC_Email_Customer_Processing_Order']->trigger( $order_id );
}

?>
