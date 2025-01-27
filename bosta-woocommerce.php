<?php

/**
 * Plugin Name: Bosta WooCommerce
 * Description: WooCommerce integration for Bosta eCommerce
 * Author: Bosta
 * Author URI: https://www.bosta.co/
 * Version: 2.9.2
 * Requires at least: 5.0
 * Tested up to: 5.8.3
 * WC requires at least: 2.6
 * WC tested up to: 4.4.1
 * Text Domain: bosta-woocommerce
 * Domain Path: /languages
 *
 */
include plugin_dir_path(__FILE__) . 'components/pickups/pickups.php';
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly

}

add_action('admin_print_styles', 'bosta_stylesheet');

function console_log($output, $with_script_tags = true)
{
    $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
        ');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}

function bosta_stylesheet()
{
    wp_enqueue_style('myCSS', plugins_url('/Css/main.css', __FILE__));
    wp_enqueue_style('pickupsCSS', plugins_url('components/pickups/pickups.css', __FILE__));
}

function get_new_cities()
{
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    $url = 'https://app.bosta.co/api/v0/businesses/' . $APIKey . '/info';
    $business_result = wp_remote_get($url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Requested-By' => 'WooCommerce',
        ),
    ));
    if (is_wp_error($business_result) || $business_result['response']['code'] !== 200) {
        $countryId = "60e4482c7cb7d4bc4849c4d5";
    } else {
        $business = json_decode($business_result['body']);
        $countryId = $business->country->_id;
    }

    $cities_url = 'https://app.bosta.co/api/v0/cities?context=dropoff&countryId=' . $countryId;
    // $cities_url = 'https://app.bosta.co/api/v0/cities?context=dropoff';
    $result = wp_remote_post($cities_url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Requested-By' => 'WooCommerce',
            'Accept-Language' => get_locale() === 'ar' ? 'ar' : 'en',
        ),
    ));

    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
        echo "<script>alert('Something went wrong: $error_message')</script>";
    } else {
        if ($result['response']['code'] !== 200) {
            $resultCities = [];
        } else {
            $result = json_decode($result['body']);
            $resultCities = array();
            for ($i = 0; $i < count($result); $i++) {
                $resultCities[$i] = get_locale() === 'ar' ? $result[$i]->nameAr : $result[$i]->name;
            }
        }
        return $resultCities;
    }
}
function get_new_states()
{
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    $url = 'https://app.bosta.co/api/v0/businesses/' . $APIKey . '/info';
    $business_result = wp_remote_get($url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Requested-By' => 'WooCommerce',
        ),
    ));

    if (is_wp_error($business_result) || $business_result['response']['code'] !== 200) {
        $countryId = "60e4482c7cb7d4bc4849c4d5";
    } else {
        $business = json_decode($business_result['body']);
        $countryId = $business->country->_id;
    }

    $states_url = 'https://app.bosta.co/api/v0/cities/getAllDistricts?context=dropoff&countryId=' . $countryId;

    $states = wp_remote_post($states_url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Requested-By' => 'WooCommerce',
            'Accept-Language' => get_locale() === 'ar' ? 'ar' : 'en',
        ),
    ));
    $resultStates = array();
    if (is_wp_error($states) && $states->get_error_message()) {
        $error_message = $states->get_error_message();
        echo "<script>alert('Something went wrong: $error_message')</script>";
    } else {
        if ($states['response']['code'] !== 200) {
            $states = [];
        } else {
            $states = json_decode($states['body']);
            for ($i = 0; $i < count($states); $i++) {
                $resultStates[$i] = $states[$i]->districts;
            }
            $states = $resultStates;
        }
        return $states;
    }
}
$resultCities = get_new_cities();
$resultStates = get_new_states();

add_filter('woocommerce_checkout_fields', 'bost_override_checkout_city_fields');
function bost_override_checkout_city_fields($fields)
{
    global $resultCities;
    for ($i = 0; $i < count($resultCities); $i++) {
        $resultCities[$i] = __($resultCities[$i], 'wps');
    }

    $city_args = wp_parse_args(array(
        'type' => 'select',
        'options' => $resultCities,
        'placeholder' => 'Select city',
        'input_class' => array(
            'wc-enhanced-select',
        ),
    ), $fields['shipping']['shipping_city']);

    $fields['shipping']['shipping_city'] = $city_args;
    $fields['billing']['billing_city'] = $city_args; // Also change for billing field
    wc_enqueue_js("
	jQuery( ':input.wc-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
		var select2_args = { minimumResultsForSearch: 5 };
		jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
	});");

    return $fields;
}

add_action('woocommerce_admin_order_data_after_billing_address', 'add_hidden_district_id_to_order_details', 10, 1);
function add_hidden_district_id_to_order_details($order){
    $district_id = get_post_meta($order->get_id(), '_district_id', true);
    echo '<input type="hidden" name="district_id" value="' . esc_attr($district_id) . '" />';
}

add_action('admin_head', 'woocommerce_admin_init');
function woocommerce_admin_init()
{
    global $resultStates;
    global $resultCities;
    $states = $resultStates;
    for ($i = 0; $i < count(array($resultStates)); $i++) {
        $resultStates[$i] = __($resultStates[$i], 'wps');
    }

    $options_a = $states;
    $screen = get_current_screen();

    if ($screen->post_type == "shop_order") {
?>

        <script type="text/javascript">
            jQuery(function($) {
                var opa = <?php echo json_encode($options_a); ?>,
                    select1 = 'select[name="_shipping_city"]',
                    select2 = 'select[name="_shipping_state"]';
                    select3 = 'select[name="_billing_city"]',
                    select4 = 'select[name="_billing_state"]';
                    
                var cities = <?php echo json_encode($resultCities); ?>;

                function dynamicSelectOptions(opt, type) {
                    var options = '';
                    $.each(opt, function(key, value) {
                        options += '<option value="' + value.zoneName + ' - ' + value.districtName + '" data-district-id="' + value.districtId + '">' + value.zoneName + ' - ' + value.districtName + '</option>';
                    });
                    updateDistrictId('district_id');
                    function updateDistrictId(inputName) {
                        $(select2).html(options);

                        var districtId = $(select2).find('option:first').data('district-id');
                        $('input[name="' + inputName + '"]').val(districtId);

                        $(select2).change(function() {
                            districtId = $(this).find('option:selected').data('district-id');
                            $('input[name="' + inputName + '"]').val(districtId);
                            console.log(districtId);
                        });
                    }
                }

                $(select1).change(function() {

                    for (let i = 0; i < cities.length; i++) {
                        if (cities[i] === $(this).val()) {
                            dynamicSelectOptions(opa[i], 'shipping');
                        }
                    }

                });

                $(select3).change(function() {

                    for (let i = 0; i < cities.length; i++) {
                        if (cities[i] === $(this).val()) {
                            dynamicSelectOptions(opa[i]);
                        }
                    }

                });
            });
        </script>

    <?php
    }
}

add_filter('woocommerce_admin_billing_fields', 'admin_order_pages_bosta_city_fields');
add_filter('woocommerce_admin_shipping_fields', 'admin_order_pages_bosta_city_fields');
function admin_order_pages_bosta_city_fields($fields)
{
    $fields['city']['type'] = 'select';
    global $resultCities;
    $cities = array();
    for ($i = 0; $i < count($resultCities); $i++) {
        $cities[$resultCities[$i]] = $resultCities[$i];
    }
    $fields['city']['options'] = $cities;
    $fields['city']['class'] = 'short';
    return $fields;
}

add_filter('woocommerce_states', 'bosta_cities_and_zones');
function bosta_cities_and_zones($states)
{
    global $resultStates;
    $bostaStates = array();
    for ($i = 0; $i < count($resultStates); $i++) {
        for ($x = 0; $x < count($resultStates[$i]); $x++) {
            $new_val = $resultStates[$i][$x]->zoneName . ' - ' . $resultStates[$i][$x]->districtName;
            array_push($bostaStates, $new_val);
        }
    }
    $states['EG'] = $bostaStates;
    return $states;
}
// add notice to config plugin
add_action('admin_notices', 'bosta_woocommerce_notice');
function bosta_woocommerce_notice()
{
    //check if woocommerce installed and activated
    if (!class_exists('WooCommerce')) {
        echo '<div class="error notice-warning text-bold">
              <p>
              <img src="' . esc_url(plugins_url('assets/images/bosta.svg', __FILE__)) . '" alt="Bosta" style="height:13px; width:25px;">
              <strong>' . sprintf(esc_html__('Bosta requires WooCommerce to be installed and active. You can download %s here.'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong>
              </p>
              </div>';
        return;
    }
}

add_action('admin_menu', 'bosta_setup_menu');
function bosta_setup_menu()
{
    //check if woocommerce is activated
    if (!class_exists('WooCommerce')) {
        return;
    }

    add_menu_page('Test Plugin Page', 'Bosta', 'manage_options', 'bosta-woocommerce', 'bosta_setting', esc_url(plugins_url('assets/images/bosta.svg', __FILE__)));

    // link to plugin settings
    add_submenu_page('bosta-woocommerce', 'Setting', 'Setting', 'manage_options', 'bosta-woocommerce', 'bosta_setting');

    // link to woocommerce orders
    add_submenu_page('bosta-woocommerce', 'Send Orders', 'Send Orders', 'manage_options', 'bosta-woocommerce-orders', 'bosta_orders');

    // create pickup request
    add_submenu_page('bosta-woocommerce', 'Create Pickup', 'Create Pickup', 'manage_options', 'bosta-woocommerce-create-edit-pickup', 'create_edit_pickup_form');

    //view pickups
    add_submenu_page('bosta-woocommerce', 'Pickup Requests', 'Pickup Requests', 'manage_options', 'bosta-woocommerce-view-pickups', 'view_scheduled_pickups');

    // link to bosta shipments
    add_submenu_page('bosta-woocommerce', 'Track Bosta Orders', 'Track Bosta Orders', 'manage_options', 'bosta-woocommerce-shipments', 'bosta_dashboard');
}

function bosta_setting()
{
    $redirect_url = admin_url('admin.php?') . 'page=wc-settings&tab=shipping&section=bosta';
    wp_redirect($redirect_url);
}

function bosta_orders()
{
    $redirect_url = admin_url('edit.php?') . 'post_type=shop_order&paged=1';
    wp_redirect($redirect_url);
}

function bosta_dashboard()
{
    $redirect_url = 'https://business.bosta.co/shipments';
    wp_redirect($redirect_url);
}

add_action('load-edit.php', 'wco_load', 20);
function wco_load()
{
    $screen = get_current_screen();
    if (!isset($screen->post_type) || 'shop_order' != $screen->post_type) {
        return;
    }

    add_filter("manage_{$screen->id}_columns", 'wco_add_columns');
    add_action("manage_{$screen->post_type}_posts_custom_column", 'wco_column_cb_data', 10, 2);
}

add_action('woocommerce_checkout_before_customer_details', 'bost_custom_checkout_fields', 20);
function bost_custom_checkout_fields()
{
    $domain = 'woocommerce';
    $checkout = WC()->checkout;
    $states = array();
    global $resultStates;
    $states = $resultStates;

    for ($i = 0; $i < count($resultStates); $i++) {
        $states[$i] = __($resultStates[$i], 'wps');
    }

    $options_a = $states;
    $required = esc_attr__('required', 'woocommerce');

    ?>
    <input type="hidden" name="district_id" value="" />
    <?php

    ?>
    <script>
        jQuery(function($) {
            var opa = <?php echo json_encode($options_a); ?>,
                select1 = 'select[name="billing_city"]',
                select2 = 'select[name="billing_state"]';
                select3 = 'select[name="shipping_city"]',
                select4 = 'select[name="shipping_state"]';

            var selectedCityVal = $("#billing_city option:selected").val();
            var selectedAreaVal = $("#billing_state option:selected").val();

            function dynamicSelectOptions(opt, cityIndex, type) {
                let index = 0;
                for (let i = 0; i < cityIndex; i++) {
                    index += opa[i].flat().length
                }

                var options = '';
                $.each( opt, function( key, value ){
                    const newKey=index+key;
                    options += '<option value="' + newKey + '" data-district-id="' + value.districtId + '">' + value.zoneName + ' - ' + value.districtName + '</option>';
                });
                
                function updateDistrictId(selectElement, inputName) {
                    $(selectElement).html(options);

                    var districtId = $(selectElement).find('option:first').data('district-id');
                    $('input[name="' + inputName + '"]').val(districtId);

                    $(selectElement).change(function() {
                        districtId = $(this).find('option:selected').data('district-id');
                        $('input[name="' + inputName + '"]').val(districtId);
                    });
                }

                if (type === 'billing') {
                    updateDistrictId(select2, 'district_id');
                } else {
                    updateDistrictId(select4, 'district_id');
                }
            }

            $(select1).change(function() {
                for (let i = 0; i < opa.length; i++) {
                    if ($(this).val() == i)
                        dynamicSelectOptions(opa[i], i, 'billing');
                }
            });

            $(select3).change(function() {
                for (let i = 0; i < opa.length; i++) {
                    if ($(this).val() == i)
                        dynamicSelectOptions(opa[i], i);
                }
            });
        });
    </script>
    <?php
}

add_action('woocommerce_checkout_update_order_meta', 'bosta_save_district_id_from_hidden_field', 10, 2);
add_action('woocommerce_process_shop_order_meta', 'bosta_save_district_id_from_hidden_field', 10, 2);
function bosta_save_district_id_from_hidden_field($order_id, $posted_data)
{   
    if (!empty($_POST['district_id'])) {
        $district_id = sanitize_text_field($_POST['district_id']);
        update_post_meta($order_id, '_district_id', $district_id);
    }
}

function wco_add_columns($columns)
{
    $order_total = $columns['order_total'];
    $order_date = $columns['order_date'];
    $order_status = $columns['order_status'];
    unset($columns['order_date']);
    unset($columns['order_status']);
    $columns["bosta_tracking_number"] = __("Bosta Tracking Number", "themeprefix");
    $columns['order_date'] = $order_date;
    $columns['order_status'] = $order_status;
    unset($columns['order_total']);
    $columns["bosta_status"] = __("Bosta Status", "themeprefix");
    $columns["bosta_delivery_date"] = __("Delivered at", "themeprefix");
    $columns["bosta_customer_phone"] = __("Customer phone", "themeprefix");
    $columns['order_total'] = $order_total;

    return $columns;
}

function wco_column_cb_data($colName, $orderId)
{
    if ($colName == 'bosta_status') {
        $status = get_post_meta($orderId, 'bosta_status', true);
        if (!empty($status)) {
            echo $status;
        } else {
            echo "---";
        }
    }

    if ($colName == 'bosta_tracking_number') {
        $trackingNumber = get_post_meta($orderId, 'bosta_tracking_number', true);
        if (!empty($trackingNumber)) {
            echo $trackingNumber;
        } else {
            echo "---";
        }
    }

    if ($colName == 'bosta_delivery_date') {
        $deliveryDate = get_post_meta($orderId, 'bosta_delivery_date', true);
        if (!empty($deliveryDate)) {
            echo date("D d-M-Y", strtotime($deliveryDate));
        } else {
            echo "---";
        }
    }

    if ($colName == 'bosta_customer_phone') {
        $customerPhone = get_post_meta($orderId, 'bosta_customer_phone', true);
        if (!empty($customerPhone)) {
            echo $customerPhone;
        } else {
            echo "---";
        }
    }
}

add_action('manage_posts_extra_tablenav', 'create_pickup_top_bar_button', 20, 1);
function create_pickup_top_bar_button($which)
{
    global $pagenow, $typenow;

    if ('shop_order' === $typenow && 'edit.php' === $pagenow && 'top' === $which) {
    ?>
        <br>
        <br>
        <br>
        <div class="alignleft actions custom">
            <button type="submit" name="create_pickup" style="height:32px;" class="orders-button" value="yes"><?php
                                                                                                                echo __('Create Pickup', 'woocommerce'); ?></button>
        </div>
    <?php
    }
    if ('shop_order' === $typenow && 'edit.php' === $pagenow && isset($_GET['create_pickup']) && $_GET['create_pickup'] === 'yes') {
        $redirect_url = admin_url('admin.php?') . 'page=bosta-woocommerce-create-edit-pickup';
        wp_redirect($redirect_url);
    }
}

add_filter('bulk_actions-edit-shop_order', 'sync_bosta', 20, 1);
function sync_bosta($actions)
{
    $actions['sync_to_bosta'] = __('Send To Bosta', 'woocommerce');
    return $actions;
}

add_filter('handle_bulk_actions-edit-shop_order', 'sync_bosta_handle', 10, 3);
function sync_bosta_handle($redirect_to, $action, $order_ids)
{
    if ($action != 'sync_to_bosta') {
        return;
    }
    global $resultCities;
    global $resultStates;
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    $ProductDescription = get_option('woocommerce_bosta_settings')['ProductDescription'];
    $OrderRef = get_option('woocommerce_bosta_settings')['OrderRef'];
    if (empty($APIKey)) {
        $redirect_url = admin_url('admin.php?') . 'page=wc-settings&tab=shipping&section=bosta';
        wp_redirect($redirect_url);
        return;
    }
    $args = array(
        'limit' => -1,
        'post__in' => $order_ids,
    );
    $allOrders = wc_get_orders($args);
    $formatedOrders = array();
    $AWBDesc = 0;
    foreach ($allOrders as $order) {
        if (empty(get_post_meta($order->id, 'bosta_tracking_number', true))) {
            $items = $order->get_items();
            $desc = 'Products: ';
            $itemsQuantity = 0;
            $descWithSku = '';
            foreach ($items as $item_id => $item_data) {
                $product = $item_data->get_product();
                $product_name = $product->get_name();
                $item_quantity = $item_data->get_quantity();
                $itemsQuantity += $item_data->get_quantity();
                $desc .= $product_name . '(' . $item_quantity . ') ';
                $product_sku = $product->get_sku();
                $AWBDesc .= $product_sku . '(' . $item_quantity . ') ,';
                $descWithSku .= 'Product sku: ' . $product_sku . ' => ' . $product_name . ' ' . '(' . $item_quantity . ') ,';
            }
            $order = json_decode($order);
            $newOrder = new stdClass();
            $newOrder->id = $order->id;
            $newOrder->type = 10;
            $newOrder->specs = new stdClass();
            $newOrder
                ->specs->packageDetails = new stdClass();
            $newOrder
                ->specs
                ->packageDetails->itemsCount = $itemsQuantity;
            if ($product_sku && $ProductDescription == 'yes') {
                $newOrder
                    ->specs
                    ->packageDetails->description = $descWithSku;
            } elseif ($product_sku && $ProductDescription == 'no') {
                $newOrder
                    ->specs
                    ->packageDetails->description = $AWBDesc;
            } elseif ($ProductDescription == 'yes') {
                $newOrder
                    ->specs
                    ->packageDetails->description = $desc;
            }
            $newOrder->notes = $order->customer_note;
            if ($OrderRef == 'yes') {
                $newOrder->businessReference = 'Woocommerce_' . $order->order_key;
            }
            $newOrder->receiver = new stdClass();
            $newOrder
                ->receiver->firstName = $order
                ->shipping->first_name;
            $newOrder
                ->receiver->lastName = $order
                ->shipping->last_name;
            if (count($order->meta_data) > 0 && $order->meta_data[0]->key == '_shipping_phone') {
                $newOrder
                    ->receiver->phone = $order->meta_data[0]->value;
            } else {
                $newOrder
                    ->receiver->phone = $order
                    ->billing->phone;
            }
            $newOrder
                ->receiver->email = $order
                ->billing->email;
            $newOrder->dropOffAddress = new stdClass();
            $newOrder
                ->dropOffAddress->firstLine = $order
                ->shipping->address_1;
            if (is_numeric($order
                ->shipping
                ->state)) {
                $shippingDistrictId = array_merge(...$resultStates)[$order
                    ->shipping
                    ->state]->districtId;
                $newOrder
                    ->dropOffAddress->districtId = $shippingDistrictId;
            } else {
                $result = preg_split("/\s*(?<!\w(?=.\w))[\-[\]()]\s*/", $order
                    ->shipping
                    ->state);
                $shippingDistrictName = $result[1];
                $shippingZoneName = $result[0];
                $shippingCityName = $order
                    ->shipping
                    ->city;
                $newOrder
                    ->dropOffAddress->districtName = $shippingDistrictName;
                $newOrder
                    ->dropOffAddress->zone = $shippingZoneName;
                $newOrder
                    ->dropOffAddress->city = $shippingCityName;
            }
            if ($order->payment_method == 'cod') {
                $newOrder->cod = (float) $order->total;
            }
            array_push($formatedOrders, $newOrder);
        }
    }
    for ($i = 0; $i < count($formatedOrders); $i++) {
        $id = $formatedOrders[$i]->id;
        unset($formatedOrders[$i]->id);
        $result = wp_remote_post('https://app.bosta.co/api/v0/deliveries', array(
            'timeout' => 30,
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'authorization' => $APIKey,
                'X-Requested-By' => 'WooCommerce',
            ),
            'body' => json_encode($formatedOrders[$i]),
        ));
        if ($result['response']['code'] != 201) {
            $result = json_decode($result['body']);
            if (gettype($result) == 'array') {
                $error = $result[0]->message;
            } else {
                $error = $result->message;
            }
            echo "Something went wrong: " . $error;
        } else {
            $result = json_decode($result['body']);
            if ($result->_id && empty(get_post_meta($id, 'bosta_delivery_id', true))) {
                add_post_meta($id, 'bosta_delivery_id', $result->_id);
            }
            if ($result
                ->state->value && empty(get_post_meta($id, 'bosta_status', true))
            ) {
                add_post_meta($id, 'bosta_status', $result
                    ->state
                    ->value);
            }
            if ($result->trackingNumber && empty(get_post_meta($id, 'bosta_tracking_number', true))) {
                add_post_meta($id, 'bosta_tracking_number', $result->trackingNumber);
            }
            if ($result->deliveryTime && empty(get_post_meta($id, 'bosta_delivery_date', true))) {
                add_post_meta($id, 'bosta_delivery_date', $result->deliveryTime);
            }
            if ($result
                ->receiver->phone && empty(get_post_meta($id, 'bosta_customer_phone', true))
            ) {
                add_post_meta($id, 'bosta_customer_phone', $result
                    ->receiver
                    ->phone);
            }
        }
    }
    $redirect_url = admin_url('edit.php?') . 'post_type=shop_order&paged=1';
    wp_redirect($redirect_url);
}

add_action('manage_posts_extra_tablenav', 'send_all_top_bar_button', 20, 1);
function send_all_top_bar_button($which)
{
    global $pagenow, $typenow;

    if ('shop_order' === $typenow && 'edit.php' === $pagenow && 'top' === $which) {
    ?>
        <div class="alignleft actions custom">
            <button type="submit" name="send_all_orders" style="height:32px;" class="orders-button" value="yes">
                <?php
                echo __('Send all orders to bosta', 'woocommerce');
                ?>
            </button>
        </div>
        <script type="text/JavaScript">
            document.getElementsByClassName("alignleft")[1].setAttribute("class", "alignright");
</script>
    <?php
    }
    if ('shop_order' === $typenow && 'edit.php' === $pagenow && isset($_GET['send_all_orders']) && $_GET['send_all_orders'] === 'yes') {
        echo $_GET['send_all_orders'];
        $orders = wc_get_orders(array(
            'limit' => -1,
            'return' => 'ids',
        ));
        sync_bosta_handle('', 'sync_to_bosta', $orders);
    }
}

add_action('manage_posts_extra_tablenav', 'fetch_status_top_bar_button', 20, 1);
function fetch_status_top_bar_button($which)
{
    global $pagenow, $typenow;

    if ('shop_order' === $typenow && 'edit.php' === $pagenow && 'top' === $which) {
    ?>
        <div class="alignright actions custom">
            <button type="submit" name="fetch_status" style="height:32px;" class="danger-button" value="yes"> <?php
                                                                                                                echo __('Refresh status ', 'woocommerce') . '<img src=' . esc_url(plugins_url('assets/images/refreshIcon.png', __FILE__)) . ' alt="Bosta" style="height:17px; width:20px;">'; ?></button>
        </div>
        <br>
        <br>
    <?php
    }
    if ('shop_order' === $typenow && 'edit.php' === $pagenow && isset($_GET['fetch_status']) && $_GET['fetch_status'] === 'yes') {
        echo $_GET['fetch_status'];
        $orders = wc_get_orders(array(
            'limit' => -1,
            'return' => 'ids',
        ));
        fetch_latest_status_action('fetch_latest_status', $orders);
    }
}

function fetch_latest_status_action($action, $order_ids)
{
    if ($action != 'fetch_latest_status') {
        return;
    }

    $orderArray = array();
    $trackingNumbers;
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    if (empty($APIKey)) {
        $redirect_url = admin_url('admin.php?') . 'page=wc-settings&tab=shipping&section=bosta';
        wp_redirect($redirect_url);
        return;
    }

    foreach ($order_ids as $order) {
        $trackingNumber = get_post_meta($order, 'bosta_tracking_number', true);
        if (!empty($trackingNumber)) {

            $orderObject = new stdClass();
            $orderObject->id = $order;
            $orderObject->trackingNumber = $trackingNumber;
            array_push($orderArray, $orderObject);
            $trackingNumbers .= ($trackingNumbers ? ',' : '') . $trackingNumber;
        }
    }
    $url = 'https://app.bosta.co/api/v0/deliveries/search?trackingNumbers=' . $trackingNumbers;
    $result = wp_remote_get($url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'authorization' => $APIKey,
            'X-Requested-By' => 'WooCommerce',
        ),
    ));

    if ($result['response']['code'] != 200) {
        var_dump($result);
        $result = json_decode($result['body']);
        if (gettype($result) == 'array') {
            $error = $result[0]->message;
        } else {
            $error = $result->message;
        }
        echo "Something went wrong: " . $error;
    } else {
        $result = json_decode($result['body']);
        $deliveries = $result->deliveries;
        for ($i = 0; $i < count($deliveries); $i++) {
            for ($j = 0; $j < count($orderArray); $j++) {
                if ($deliveries[$i]->trackingNumber == $orderArray[$j]->trackingNumber) {
                    update_post_meta($orderArray[$j]->id, 'bosta_status', $deliveries[$i]
                        ->state
                        ->value);
                    update_post_meta($orderArray[$j]->id, 'bosta_delivery_date', $deliveries[$i]
                        ->state
                        ->deliveryTime);
                    update_post_meta($orderArray[$j]->id, 'bosta_customer_phone', $deliveries[$i]
                        ->receiver
                        ->phone);
                }
            }
        }
        $redirect_url = admin_url('edit.php?') . 'post_type=shop_order&paged=1';
        wp_redirect($redirect_url);
    }
}

add_action('manage_posts_extra_tablenav', 'filter_by_bosta_status', 30, 6);

function filter_by_bosta_status($which)
{
    global $pagenow, $typenow;

    if ('shop_order' === $typenow && 'edit.php' === $pagenow && 'top' === $which) {
    ?>
        <p style="font-size: 15px;
   font-weight: 600;">Filter with bosta status:</p>
        <div class="bosta_status_search_tags">
            <input type="button" value="Created" class="createdStatus" id="createdStatus"
                onClick="document.location.href='edit.php?s=created&post_type=shop_order&paged=1'" />
            <input type="button" value="Delivered" class="deliveredcreatedStatus" id="Delivered"
                onClick="document.location.href='edit.php?s=delivered&post_type=shop_order&paged=1'" />
            <input type="button" value="Terminated" class="terminatedtatus" id="terminatedStatus"
                onClick="document.location.href='edit.php?s=terminated&post_type=shop_order&paged=1'" />
            <input type="button" value="Returned" class="returnedStatus" id="returnedStatus"
                onClick="document.location.href='edit.php?s=returned&post_type=shop_order&paged=1'" />
        </div>
    <?php
    }
}

add_filter('bulk_actions-edit-shop_order', 'print_awb', 20, 1);
function print_awb($actions)
{
    $actions['print_bosta_awb'] = __('Print Bosta AirWaybill', 'woocommerce');
    return $actions;
}

add_filter('handle_bulk_actions-edit-shop_order', 'print_awb_handle', 10, 3);
function print_awb_handle($redirect_to, $action, $order_ids)
{
    if ($action != 'print_bosta_awb') {
        return;
    }

    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    if (empty($APIKey)) {
        $redirect_url = admin_url('admin.php?') . 'page=wc-settings&tab=shipping&section=bosta';
        wp_redirect($redirect_url);
        return;
    }

    $ids = '';
    foreach ($order_ids as $order) {
        $deliveryId = get_post_meta($order, 'bosta_delivery_id', true);
        if (!empty($deliveryId)) {
            $ids .= ($ids ? ',' : '') . $deliveryId;
        }
    }

    $url = 'https://app.bosta.co/api/v0/deliveries/awb?ids=' . $ids;
    $result = wp_remote_get($url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'authorization' => $APIKey,
            'X-Requested-By' => 'WooCommerce',
        ),
    ));
    if ($result['response']['code'] != 200) {
        var_dump($result);
        $result = json_decode($result['body']);
        if (gettype($result) == 'array') {
            $error = $result[0]->message;
        } else {
            $error = $result->message;
        }
        echo "Something went wrong: " . $error;
    } else {
        $result = json_decode($result['body']);

        $decoded = base64_decode($result->data, true);

        header('Content-Type: application/pdf');
        header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        ob_clean();
        flush();
        echo $decoded;
    }
}

add_action('woocommerce_update_order', 'bosta_action_woocommerce_update_order', 10, 1);
function bosta_action_woocommerce_update_order($order_get_id)
{
    $bostaStatus = get_post_meta($order_get_id, 'bosta_status', true);
    if ($bostaStatus != 'Pickup requested' && $bostaStatus != 'Created') {
        return;
    }

    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    $deliveryId = get_post_meta($order_get_id, 'bosta_delivery_id', true);
    $order = wc_get_order($order_get_id);

    $order = json_decode($order);
    $newOrder = new stdClass();
    $newOrder->notes = $order->customer_note;
    $newOrder->receiver = new stdClass();
    $newOrder
        ->receiver->firstName = $order
        ->shipping->first_name;
    $newOrder
        ->receiver->lastName = $order
        ->shipping->last_name;
    if ($order->meta_data[0]->key == '_shipping_phone') {
        $newOrder
            ->receiver->phone = $order->meta_data[0]->value;
    } else {
        $newOrder
            ->receiver->phone = $order
            ->billing->phone;
    }
    $newOrder
        ->receiver->email = $order
        ->billing->email;
    $newOrder->dropOffAddress = new stdClass();
    $newOrder
        ->dropOffAddress->firstLine = $order
        ->shipping->address_1;

    $newOrder->dropOffAddress->city = $order->shipping->city;
    $newOrder->dropOffAddress->districtId = get_post_meta($order_get_id, '_district_id');

    if ($order->payment_method == 'cod') {
        $newOrder->cod = (float) $order->total;
    }

    $result = wp_remote_request('https://app.bosta.co/api/v0/deliveries/' . $deliveryId, array(
        'timeout' => 30,
        'method' => 'PUT',
        'headers' => array(
            'Content-Type' => 'application/json',
            'authorization' => $APIKey,
            'X-Requested-By' => 'WooCommerce',
        ),
        'body' => json_encode($newOrder),
    ));
};

add_action('woocommerce_update_order', 'bosta_action_woocommerce_update_order', 10, 1);

add_action('add_meta_boxes', 'bosta_add_custom_box');
if (!function_exists('bosta_add_custom_box')) {
    function bosta_add_custom_box()
    {
        add_meta_box('wporg_box_id', __('My Field', 'woocommerce'), 'wporg_custom_box_html', 'shop_order', 'side', 'core');
    }
}

add_action('add_meta_boxes', 'bosta_add_custom_box');
function wporg_custom_box_html($post)
{
    $screen = get_current_screen();
    if (!isset($screen->post_type) || 'shop_order' != $screen->post_type) {
        return;
    }

    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    $trackingNumber = get_post_meta($post->ID, 'bosta_tracking_number', true);
    if (empty($trackingNumber)) {
        return;
    }

    $url = 'https://app.bosta.co/api/v0/deliveries/search?trackingNumbers=' . $trackingNumber;
    $result = wp_remote_get($url, array(
        'timeout' => 30,
        'method' => 'GET',
        'headers' => array(
            'Content-Type' => 'application/json',
            'authorization' => $APIKey,
            'X-Requested-By' => 'WooCommerce',
        ),
    ));

    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
        echo "Something went wrong: $error_message";
    } else {
        $result = json_decode($result['body']);
        $delivery = $result->deliveries[0];
    }
    if ($delivery
        ->state->value != 'Created'
    ) {
    ?>
        <script>
            var div = document.createElement("div");
            var p = document.createElement("p");
            var textnode = document.createTextNode(" The order is being shipped by bosta. Any updating or deleting on the order info will not reflect to bosta system. For support email help@bosta.co");
            p.appendChild(textnode);
            div.appendChild(p);
            div.setAttribute('class', 'error error-note');
            const parent = document.getElementsByClassName("wrap")[0];
            parent.insertBefore(div, parent.children[3]);
        </script>
<?php
    }
}
add_action('wp_trash_post', 'bosta_custom_delete_function');
function bosta_custom_delete_function($id)
{
    $screen = get_current_screen();
    if (!isset($screen->post_type) || 'shop_order' != $screen->post_type) {
        return;
    }
    $bostaStatus = get_post_meta($id, 'bosta_status', true);
    if ($bostaStatus != 'Pickup requested' && $bostaStatus != 'Created') {
        return;
    }
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    $deliveryId = get_post_meta($id, 'bosta_delivery_id', true);
    $result = wp_remote_request('https://app.bosta.co/api/v0/deliveries/' . $deliveryId, array(
        'timeout' => 30,
        'method' => 'DELETE',
        'headers' => array(
            'Content-Type' => 'application/json',
            'authorization' => $APIKey,
            'X-Requested-By' => 'WooCommerce',
        ),
    ));
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bosta_plugin_action_links');
function bosta_plugin_action_links($links)
{
    $plugin_links = array(
        '<a href="' . menu_page_url('bosta-woocommerce', false) . '">' . __('Settings') . '</a>',
    );
    return array_merge($plugin_links, $links);
}
add_action('plugins_loaded', 'init_bosta_shipping_class');
function init_bosta_shipping_class()
{
    //check if woocommerce is activated
    if (!class_exists('WooCommerce')) {
        return;
    }
    if (!class_exists('bosta_Shipping_Method')) {
        class bosta_Shipping_Method extends WC_Shipping_Method
        {
            public function __construct()
            {
                $this->id = 'bosta';
                $this->method_title = __('Bosta Shipping', 'bosta');
                $this->method_description = __('Custom Shipping Method for bosta', 'bosta');
                $this->init();
                $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('bosta Shipping', 'bosta');
            }
            function init()
            {
                $this->init_form_fields();
                $this->init_settings();
                add_action('woocommerce_update_options_shipping_' . $this->id, array(
                    $this,
                    'process_admin_options',
                ));
            }
            function init_form_fields()
            {
                $this->form_fields = array(
                    'APIKey' => array(
                        'title' => __('APIKey', 'bosta'),
                        'type' => 'text',
                    ),
                    'ProductDescription' => array(
                        'label' => 'Enable Woocomerce product description',
                        'title' => __('Product description', 'bosta'),
                        'type' => 'checkbox',
                        'default' => 'yes',
                    ),
                    'OrderRef' => array(
                        'label' => 'Enable Woocomerce order reference',
                        'title' => __('Order reference', 'bosta'),
                        'type' => 'checkbox',
                        'default' => 'yes',
                    ),
                );
            }
        }
    }
}
add_action('woocommerce_shipping_init', 'init_bosta_shipping_class');
function add_bosta_shipping_method($methods)
{
    $methods[] = 'bosta_Shipping_Method';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'add_bosta_shipping_method');

add_filter('woocommerce_shop_order_search_fields', 'bosta_woocommerce_shop_order_search_order_custom_fields');
function bosta_woocommerce_shop_order_search_order_custom_fields($search_fields)
{
    $search_fields[] = 'bosta_tracking_number';
    $search_fields[] = '_order_total';
    $search_fields[] = 'bosta_customer_phone';
    $search_fields[] = 'bosta_status';
    return $search_fields;
}
//For adding order details data from bosta API
add_filter('woocommerce_admin_order_preview_get_order_details', 'bosta_admin_order_preview_add_custom_meta_data', 10, 2);
function bosta_admin_order_preview_add_custom_meta_data($data, $order)
{
    $trackingNumber = get_post_meta($order->id, 'bosta_tracking_number', true);
    $APIKey = get_option('woocommerce_bosta_settings')['APIKey'];
    if (!empty($trackingNumber)) {
        $url = "https://app.bosta.co/api/v0/deliveries/" . $trackingNumber;
        $result = wp_remote_get($url, array(
            'timeout' => 30,
            'method' => 'GET',
            'headers' => array(
                'Content-Type' => 'application/json',
                'authorization' => $APIKey,
                'X-Requested-By' => 'WooCommerce',
            ),
        ));
        if (is_wp_error($result)) {
            return false;
        } else {
            $orderDetails = json_decode($result['body']);
            $data['trackingNumber'] = $orderDetails->trackingNumber;
            $data['type'] = $orderDetails
                ->type->value;
            $data['status'] = $orderDetails
                ->state->value;
            $data['cod'] = $orderDetails->cod ? $orderDetails->cod : '0';
            $data['notes'] = $orderDetails->notes ? $orderDetails->notes : 'N/A';
            $data['itemsCount'] = $orderDetails
                ->specs
                ->packageDetails->itemsCount ? $orderDetails
                ->specs
                ->packageDetails->itemsCount : 'N/A';
            $data['createdAt'] = $orderDetails->createdAt ? $orderDetails->createdAt : 'N/A';
            $data['updatedAt'] = $orderDetails->updatedAt ? $orderDetails->updatedAt : 'N/A';
            $data['fullName'] = $orderDetails
                ->receiver->fullName ? $orderDetails
                ->receiver->fullName : 'N/A';
            $data['phone'] = $orderDetails
                ->receiver->phone ? $orderDetails
                ->receiver->phone : 'N/A';
            $data['dropOffAddressCity'] = $orderDetails
                ->dropOffAddress
                ->city->name;
            $data['dropOffAddressZone'] = $orderDetails
                ->dropOffAddress
                ->zone->name;
            $data['dropOffAddressDistrict'] = $orderDetails
                ->dropOffAddress
                ->district->name;
            $data['dropOffAddressFistLine'] = $orderDetails
                ->dropOffAddress->firstLine;
            $data['dropOffAddressBuilding'] = $orderDetails
                ->dropOffAddress->buildingNumber ? $orderDetails
                ->dropOffAddress->buildingNumber : 'N/A';
            $data['dropOffAddressFloor'] = $orderDetails
                ->dropOffAddress->floor ? $orderDetails
                ->dropOffAddress->floor : 'N/A';
            $data['dropOffAddressApartment'] = $orderDetails
                ->dropOffAddress->apartment ? $orderDetails
                ->dropOffAddress->apartment : 'N/A';
            $data['pickupAddressCity'] = $orderDetails
                ->pickupAddress
                ->city->name;
            $data['pickupAddressZone'] = $orderDetails
                ->pickupAddress
                ->zone->name;
            $data['pickupAddressDistrict'] = $orderDetails
                ->pickupAddress
                ->district->name;
            $data['pickupAddressFistLine'] = $orderDetails
                ->pickupAddress->firstLine;
            $data['pickupAddressBuilding'] = $orderDetails
                ->pickupAddress->buildingNumber ? $orderDetails
                ->pickupAddress->buildingNumber : 'N/A';
            $data['pickupAddressFloor'] = $orderDetails
                ->pickupAddress->floor ? $orderDetails
                ->pickupAddress->floor : 'N/A';
            $data['pickupAddressApartment'] = $orderDetails
                ->pickupAddress->apartment ? $orderDetails
                ->pickupAddress->apartment : 'N/A';
            $data['pickupRequestId'] = $orderDetails->pickupRequestId ? $orderDetails->pickupRequestId : 'N/A';
            $data['deliveryAttemptsLength'] = $orderDetails->deliveryAttemptsLength;
            $data['outboundActionsCount'] = $orderDetails->outboundActionsCount;

            if (!empty($orderDetails->sla)) {
                $data['promise'] = $orderDetails
                    ->sla
                    ->e2eSla->isExceededE2ESla ? 'Not met' : 'Met';
            } else {
                $data['promise'] = 'Not started yet';
            }
            for ($x = 0; $x < count($orderDetails->timeline); $x++) {
                $data["timeline_value_$x"] = $orderDetails->timeline[$x]->value;
                $data["timeline_date_$x"] = $orderDetails->timeline[$x]->date;
                $data["timeline_done_$x"] = $orderDetails->timeline[$x]->done == true ? 'status_done' : 'status_not_done';
                if ($orderDetails->timeline[$x]->done == true && $x !== count($orderDetails->timeline)) {
                    $data["timeline_next_action"] = $orderDetails->timeline[$x]->nextAction ? $orderDetails->timeline[$x]->nextAction : 'N/A';
                    $data["timeline_shipment_age"] = $orderDetails->timeline[$x]->nextAction ? $orderDetails->timeline[$x]->nextAction : 'N/A';
                }
            }
            for ($count = 0; $count < count($orderDetails->history); $count++) {
                $data["tracking_title_$count"] = $orderDetails->history[$count]->title;
                $data["tracking_date_$count"] = $orderDetails->history[$count]->date;
                for ($j = 0; $j < count($orderDetails->history[$count]->subs); $j++) {
                    $data["tracking_subs_title_$count$j"] = $orderDetails->history[$count]->subs[$j]->title;
                    $data["tracking_subs_date_$count$j"] = $orderDetails->history[$count]->subs[$j]->date;
                }
            }
        }
    }
    return $data;
}
// Display custom values in Order preview
add_action('woocommerce_admin_order_preview_start', 'custom_display_bosta_order_data_in_admin');
function custom_display_bosta_order_data_in_admin()
{
    echo "
       <h4 class='table-title'>Order Timeline</h4>
   <div class='timeline-table'>
      <div class='timeline-status'>
         ";
    for ($x = 0; $x < 7; $x++) {
        echo "
         <div><span class={{data.timeline_done_$x}}></span>  <span class={{data.timeline_done_$x}}_line></span> <br/><span class='timeline_title'>{{data.timeline_value_$x}}</span><br/>{{data.timeline_date_$x}} </div>
         ";
    };
    echo "
      </div>
      <span class='timeline-next-action'><span class='next-action-label'>Next Action: </span> {{data.timeline_next_action}}</span>
   </div>
   ";
    echo "
       <h4 class='table-title'>Order Tracking</h4>
   <div class='timeline-table'>
      <div class=''>
         ";
    for ($count = 0; $count < 6; $count++) {

        echo "<div class='tracking'><span class='tracking_title'>{{data.tracking_title_$count}}</span> <span class='tracking_date'>{{data.tracking_date_$count}}</span> </div>
         ";
        for ($i = 0; $i < 4; $i++) {
            echo "<div class='tracking'><span class='tracking_subs_title'>{{data.tracking_subs_title_$count$i}}</span> <span class='tracking_date'>{{data.tracking_subs_date_$count$i}}</span> </div>";
        }
    };
    echo "
      </div>
   </div>
   ";

    echo "
   <h4 class='table-title'>Order details</h4>
   <table class='order-details-table'>
      <tr>
         <th>Bosta tracking number</th>
         <th>Type</th>
         <th>Status</th>
      </tr>
      <tr>
         <td>{{data.trackingNumber}}</td>
         <td>{{data.type}}</td>
         <td>{{data.status}}</td>
      </tr>

      <tr>
         <th> Cash on delivery</th>
         <th>Created at</th>
         <th>Last update date</th>
      </tr>
      <tr>
         <td>{{data.cod}} LE</td>
         <td>{{data.createdAt}}</td>
         <td>{{data.updatedAt}}</td>
      </tr>
      <tr>
      <th>Items count</th>
         <th>Delivery Notes</th>
      </tr>
      <tr>
      <td class='last-field'> {{data.itemsCount}} </td>
         <td class='last-field'> {{data.notes}} </td>
      </tr>
   </table>
   ";
    echo "
   <h4 class='table-title'>Customer Info</h4>
   <table class='order-details-table'>
      <tr>
         <th>Customer name</th>
         <th>Phone number</th>
         <th>Area,City</th>
      </tr>
      <tr>
         <td>{{data.fullName}}</td>
         <td>{{data.phone}}</td>
         <td>, {{data.dropOffAddressZone}}-{{data.dropOffAddressDistrict}}, {{data.dropOffAddressCity}}</td>
      </tr>
      <tr>
         <th>Customer address</th>
         <th>Building number</th>
         <th>Floor, Apartment</th>
      </tr>
      <tr>
         <td class='last-field'>{{data.dropOffAddressFistLine}}</td>
         <td class='last-field'>{{data.dropOffAddressBuilding}}</td>
         <td class='last-field'>{{data.dropOffAddressFloor}}, {{data.dropOffAddressApartment}}</td>
      </tr>
   </table>
   ";
    echo "
   <h4 class='table-title'>Pickup Info</h4>
   <table class='order-details-table'>
      <tr>
         <th>Street name</th>
         <th>City</th>
         <th>Area</th>
      </tr>
      <tr>
         <td>{{data.pickupAddressFistLine}}</td>
         <td>{{data.pickupAddressCity}}</td>
         <td>{{data.pickupAddressZone}}, {{data.pickupAddressDistrict}}</td>
      </tr>
      <tr>
         <th>Building</th>
         <th>Floor, Apartment</th>
         <th>Pickup ID</th>
      </tr>
      <tr>
         <td class='last-field'>{{data.pickupAddressBuilding}}</td>
         <td class='last-field'>{{data.pickupAddressFloor}}, {{data.pickupAddressApartment}}</td>
         <td class='last-field'>{{data.pickupRequestId}}</td>
      </tr>
   </table>
   ";
    echo "
   <h4 class='table-title'>Bosta Performance</h4>
   <table class='order-details-table'>
      <tr>
         <th>Delivery attempts <br/><span class='subtext'>The number of times the Bosta tried to deliver the order to your customer.</span></th>
         <th>Outbound calls <br/><span class='subtext'>The number of calls made by the outbound team to verify the star actions and take corrective actions if needed to deliver the order on time</span></th>
         <th>Delivery promise <br/><span class='subtext'>Bosta promises next day delivery (calculated from the pickup date) for orders with Cairo as the pickup and drop city. The expected delivery period increases to two or three days depending on the distance between the pick up and the drop off cities i.e. Alexandria, Delta or Upper Egypt.</span></th>
      </tr>
      <tr>
         <td class='last-field'>{{data.deliveryAttemptsLength}} of 3 attempts</td>
         <td class='last-field'>{{data.outboundActionsCount}} Calls  </td>
         <td class='last-field'>{{data.promise}}  </td>
      </tr>
   </table>";
}
