<?php
/**
 * Settings Dotpay payment module for WP e-Commerce
 */
$nzshpcrt_gateways[$num] = array(
    'name'          => __( 'Dotpay Payment', 'dotpay' ),
    'internalname'  => 'dotpay',
    'display_name'  => __( 'Dotpay Payment', 'dotpay' ),
    'function'      => 'dotpay_gateway',
    'submit_function' => 'dotpay_submit',
    'form'          => 'form_dotpay_payment',
    'image'         => WPSC_URL . '/images/dotpay.png',
    'available_currencies' => array('PLN','EUR','USD','GBP','JPY','CZK','SEK'),
    'dotpay_callback_status' => 'FAIL'
);

/**
 * Get and return set currency.
 * @param string $name
 * @return mixed
 */
function getCurrency($name = null) {
    global $wpdb;

    $sql = "SELECT * FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `" . WPSC_TABLE_CURRENCY_LIST . "`.id = " . get_option('currency_type');
    $return = currencyValidation($wpdb->get_row($sql,ARRAY_A));

    if($name && isset($return, $name)) {
        return $return[$name];
    } else {
        return $return;
    }
}

/**
 * Get total price transaction
 *
 * @param $sessionId
 * @return mixed
 */
function getPrice($sessionId) {
    global $wpdb;

    $sql = "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `" . WPSC_TABLE_PURCHASE_LOGS . "`.sessionid = " . $sessionId;
    $return = $wpdb->get_row($sql,ARRAY_A);

    return $return['totalprice'];
}

/**
 * Get currencies for Dotpay
 *
 * @return mixed
 */

function getCurrencies() {
    global $wpdb, $wpsc_gateways ;

    $available_currencies = $wpsc_gateways['dotpay']['available_currencies'];

    $sql = "SELECT * FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `" . WPSC_TABLE_CURRENCY_LIST . "`.code LIKE '" . join("' OR `" . WPSC_TABLE_CURRENCY_LIST . "`.code LIKE '", $available_currencies) . "'";

    return $wpdb->get_results($sql,ARRAY_A);
}

/**
 * Return wordpres language
 *
 * @return string
 */
function getLang() {
    $lang = explode('_', get_locale());
    return $lang[0];
}

/**
 * Return the currency accepted by Dotpay
 *
 * @param mixed $currency
 * @return array
 */
function currencyValidation($currency) {
    global $wpsc_gateways;

    $available_currencies = $wpsc_gateways['dotpay']['available_currencies'];

    if (isset($currency['code']) && in_array($currency['code'], $available_currencies)) {
        return $currency;
    }
}

/**
 * Checks if the received data are correct
 *
 * @param array $data
 * @return string
 */
function getErrorMessage($data) {


    if(!isset($data['control'])){
        return 'data controll doesnt recieve';
    }
    $total_price = getPrice($data['control']);


    $string = get_option('dotpay_pid') .
        (isset($data['id']) ? $data['id'] : '') .
        (isset($data['operation_number']) ? $data['operation_number'] : '') .
        (isset($data['operation_type']) ? $data['operation_type'] : '') .
        (isset($data['operation_status']) ? $data['operation_status'] : '') .
        (isset($data['operation_amount']) ? $data['operation_amount'] : '') .
        (isset($data['operation_currency']) ? $data['operation_currency'] : '') .
        (isset($data['operation_withdrawal_amount']) ? $data['operation_withdrawal_amount'] : '') .
        (isset($data['operation_commission_amount']) ? $data['operation_commission_amount'] : '') .
        (isset($data['operation_original_amount']) ? $data['operation_original_amount'] : '') .
        (isset($data['operation_original_currency']) ? $data['operation_original_currency'] : '') .
        (isset($data['operation_datetime']) ? $data['operation_datetime'] : '') .
        (isset($data['operation_related_number']) ? $data['operation_related_number'] : '') .
        (isset($data['control']) ? $data['control'] : '') .
        (isset($data['description']) ? $data['description'] : '') .
        (isset($data['email']) ? $data['email'] : '') .
        (isset($data['p_info']) ? $data['p_info'] : '') .
        (isset($data['p_email']) ? $data['p_email'] : '') .
        (isset($data['channel']) ? $data['channel'] : '') .
        (isset($data['channel_country']) ? $data['channel_country'] : '') .
        (isset($data['geoip_country']) ? $data['geoip_country'] : '');


    if ($_SERVER['REMOTE_ADDR'] <> '195.150.9.37' && !get_option('dotpay_test_mode')) {
        return 'Wrong address';
    }

    if (hash('sha256', $string) != $data['signature']){
        return 'Wrong signature';
    }

    if((float) $data['operation_original_amount'] != (float) $total_price){
        return 'Wrong amount';
    }

    return false;
}

/**
 * Definition and logic of dotpay gateway
 *
 * @param $separator
 * @param $sessionid
 */
function dotpay_gateway($separator, $sessionid) {
    global $wpdb, $wpsc_cart;

    $actions = array(
        'sandbox'   => 'https://ssl.dotpay.pl/test_payment/',
        'develop'   => 'https://ssl.dotpay.pl/'
    );

    $purchase_log = $wpdb->get_row("SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= ".$sessionid." LIMIT 1",ARRAY_A);

    $usersql = "SELECT `".WPSC_TABLE_SUBMITED_FORM_DATA."`.value,`"
        . WPSC_TABLE_CHECKOUT_FORMS."`.`name`,`"
        . WPSC_TABLE_CHECKOUT_FORMS."`.`unique_name` FROM`"
        . WPSC_TABLE_CHECKOUT_FORMS."` LEFT JOIN`"
        . WPSC_TABLE_SUBMITED_FORM_DATA."` ON`".WPSC_TABLE_CHECKOUT_FORMS."`.id =`"
        . WPSC_TABLE_SUBMITED_FORM_DATA."`.`form_id` WHERE`"
        . WPSC_TABLE_SUBMITED_FORM_DATA."`.`log_id`=".$purchase_log['id']." ORDER BY `"
        . WPSC_TABLE_CHECKOUT_FORMS."`.`checkout_order`";

    $userinfo = $wpdb->get_results($usersql, ARRAY_A);

    foreach ($userinfo as $key => $value ) {
        if(($value['unique_name'] == 'billingfirstname') && $value['value'] != '') {
            $firstname = $value['value'];
        }

        if(($value['unique_name'] == 'billinglastname') && $value['value'] != '') {
            $lastname = $value['value'];
        }

        if(($value['unique_name'] == 'billingaddress') && $value['value'] != '') {
            $street = $value['value'];
        }

        if(($value['unique_name'] == 'billingcity') && $value['value'] != '') {
            $city = $value['value'];
        }

        if(($value['unique_name'] == 'billingemail') && $value['value'] != '') {
            $email = $value['value'];
        }

        if(($value['unique_name'] == 'billingcountry') && $value['value'] != '') {
            $country = $value['value'];
        }

        if(($value['unique_name'] == 'billingpostcode') && $value['value'] != '') {
            $postcode = $value['value'];
        }

        if(($value['unique_name'] == 'billingphone') && $value['value'] != '') {
            $phone = $value['value'];
        }
    }

    //Dotpay post variable
    $data = array(
        'id' => (int) get_option('dotpay_accountId'),
        'amount' => (float) $wpsc_cart->total_price,
        'currency' => getCurrency('code'),
        'description' => __('Order:') . ' ' . $purchase_log['id'],
        'lang' => get_option('dotpay_lang')!="" ? get_option('dotpay_lang') : getLang(),
        'api_version' => get_option('dotpay_apiVersion') != '' ? get_option('dotpay_apiVersion') : 'dev'
    );

    $additional = array(
        'channel' => get_option('dotpay_channel',''),
        'ch_lock' => 0,
        'URL' =>  get_option('dotpay_return_url') != '' ? get_option('dotpay_return_url') : get_option('transact_url'),
        'type' => "" . (string) get_option('dotpay_type', 0) . "",
        'URLC' => get_option('permalink_structure')=='' ? get_option('siteurl') . "/index.php?page_id=" . get_option('dotpay_callback_page_id') : get_option('siteurl') . "/index.php/dotpay/callback/",
        'control' => $sessionid,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'street' => $street,
        'city' => $city,
        'postcode' => $postcode,
        'phone' => $phone,
        'country' => $country,
        'p_info' => get_option('blogname'),
        'p_email' => get_option('dotpay_p_email')
    );

    if((int) get_option('dotpay_test_mode') == 1) {
        $action = $actions['sandbox'];
    } else {
        $action = $actions['develop'];
    }

    $output = "
        <div style=\"display: none; width: 300px;\">
            <form id=\"dotpayprzelewy\" action=\"" . $action . "\" name=\"dotpayprzelewy\" method=\"POST\">
                <input type=\"text\" name=\"id\" value=\"" . $data['id'] . "\" />
                <input type=\"text\" name=\"amount\" value=\"" . $data['amount'] . "\" />
                <input type=\"text\" name=\"currency\" value=\"" . $data['currency'] . "\" />
                <textarea name=\"description\">   ". $data['description'] . "</textarea>
                <input type=\"text\" name=\"lang\" value=\"" . $data['lang'] . "\" />
                <input type=\"text\" name=\"api_version\" value=\"" . $data['api_version'] . "\" />
    ";

    foreach ($additional as $key => $value) {
        if ($value != '') {
            $output .= "<input type=\"text\" name=\"" . $key . "\" value=\"" . $value . "\" />";
        }
    }

    $output .= "
                <!--<input type=\"submit\" name=\"btn_submit\" value=\"submit\" />-->
            </form>
        </div>
    ";

    echo $output;
    echo "<script language=\"javascript\" type=\"text/javascript\">
        document.getElementById('dotpayprzelewy').submit();</script>";

}

/**
 * Setup for Dotpay gateway
 *
 * @return string
 */
function form_dotpay_payment() {

    $test_mode = get_option('dotpay_test_mode',1);

    $output = "
    <tr>
        <td>" . __('User ID','dotpay') . "</td>
        <td><input type=\"text\" size=\"40\" value=\"" . get_option('dotpay_accountId'). "\" name=\"dotpay_accountId\" /></td>
    </tr>
    <tr>
        <td>" . __('User Token','dotpay') . "</td>
        <td><input type=\"text\" size=\"40\" value=\"" . get_option('dotpay_pid'). "\" name=\"dotpay_pid\" /></td>
    </tr>
    <tr>
        <td>" . __('Mode','dotpay') . "</td>
        <td>
            <input" . ($test_mode ? " checked=\"checked\"" : "") . " type='radio' name='dotpay_test_mode' value='1' id='dotpay_sandbox' /> <label for='dotpay_sandbox'>" . __('Sandbox (For testing)', 'dotpay' ) . "</label> &nbsp;
			<input" . (!$test_mode ? " checked=\"checked\"" : "") . " type='radio' name='dotpay_test_mode' value='0' id='dotpay_production' /> <label for='dotpay_production'>" . __('Production', 'dotpay' ) . "</label>
        </td>
    </tr>
    <tr>
		<td>
			" . __( 'Select Currency:', 'wpsc' ) . "
		</td>
		<td>
			<select name='currency_type'>\n";

    $current_currency = getCurrency();
    $currency_list = getCurrencies();

    foreach ( $currency_list as $currency_item ) {

        $selected_currency = '';

        if ($current_currency['code'] == $currency_item['code']) {
            $selected_currency = "selected='selected'";
        }
        $output .= "<option " . $selected_currency . " value='" . $currency_item['id']. "'>" . $currency_item['country'] . " (" . $currency_item['code'] . ")</option>";
    }
    $output .= "
			</select>
		</td>
    </tr>
    ";

    return $output;
}

/**
 * Submit proccess in save config
 *
 * @return bool
 */
function dotpay_submit() {
    $options  = array(
        'dotpay_accountId' => sanitize_text_field( isset($_POST['dotpay_accountId']) ? $_POST['dotpay_accountId'] : ""),
        'dotpay_pid' => sanitize_text_field( isset($_POST['dotpay_pid']) ? $_POST['dotpay_pid'] : ""),
        'dotpay_type' => sanitize_text_field( isset($_POST['dotpay_type']) ? $_POST['dotpay_type'] : 0),
        'dotpay_test_mode' => sanitize_text_field( isset($_POST['dotpay_test_mode']) ? $_POST['dotpay_test_mode'] : ""),
        'dotpay_lang' => sanitize_text_field( isset($_POST['dotpay_lang']) ? $_POST['dotpay_lang'] : ""),
        'dotpay_channel' => sanitize_text_field( isset($_POST['dotpay_channel']) ? $_POST['dotpay_channel'] : ""),
        'dotpay_p_email' => sanitize_text_field( isset($_POST['dotpay_p_email']) ? $_POST['dotpay_p_email'] : ""),
        'dotpay_apiVersion' => sanitize_text_field( isset($_POST['dotpay_apiVersion']) ? $_POST['dotpay_apiVersion'] : ""),
        'dotpay_return_url' => sanitize_text_field( isset($_POST['dotpay_return_url']) ? $_POST['dotpay_return_url'] : ""),
        'dotpay_notify_url' => sanitize_text_field( isset($_POST['dotpay_notify_url']) ? $_POST['dotpay_notify_url'] : ""),
        'currency_type' => sanitize_text_field( isset($_POST['currency_type']) ? $_POST['currency_type'] : ""),
    );
    foreach($options as $key => $option) {
        update_option($key, $option);
    }

    return true;
}

/**
 * Callback for Dotpay
 */
function dotpay_callback() {
    global $wpsc_gateways;

    $error = getErrorMessage($_POST);

    if ($error){
        $wpsc_gateways['dotpay']['dotpay_callback_status'] = $error;
        return false;
    }

    $status = $_POST['operation_status'];

    if ($status=='completed' && isset($_POST['control'])) {

        $order = array(
            'processed'  => 2,
            'sessionid'  => $_POST['control'],
            'date'       => time(),
        );

        wpsc_update_purchase_log_details( $_POST['control'], $order, 'sessionid' );
        transaction_results($_POST['control'], false);

    }

    if ($status=='rejected' && isset($_POST['control'])) {

        $order = array(
            'processed'  => 6,
            'sessionid'  => $_POST['control'],
            'date'       => time(),
        );

        wpsc_update_purchase_log_details( $_POST['control'], $order, 'sessionid' );
        transaction_results($_POST['control'], false);

    }

    $wpsc_gateways['dotpay']['dotpay_callback_status'] = 'OK';

}

/**
 * Setup sessionId for callback page
 */
function dotpay_results() {

    $statusPost = isset($_POST['status']) ? $_POST['status']: null;
    $statusGet = isset($_GET['status']) ? $_GET['status'] : null;

    if( $statusPost or $statusGet ) {
        $_GET['sessionid'] = wpsc_get_customer_meta('checkout_session_id');
    }
}

add_action('init', 'dotpay_callback');
add_action('init', 'dotpay_results');
load_plugin_textdomain('dotpay');