<?php
/*
 * Plugin Name: WooCommerce Extra Consent
 * Description: Adds an extra consent checkbox to the checkout page and stores customer information in the database.
 * Version: 1.0
 * Author: Ilija Iliev Strumjan
 * Text Domain: woocommerce-extra-consent
 * Domain Path: /languages
 * Requires Plugins: woocommerce
*/

// Активирање на додавката
register_activation_hook(__FILE__, 'wec_install');

// Load plugin text domain for translations
add_action('plugins_loaded', 'woocommerce_extra_consent_textdomain');
function woocommerce_extra_consent_textdomain() {
    load_plugin_textdomain('woocommerce-extra-consent', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Креирање на табела за клиенти
function wec_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wec_clients';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(15) NOT NULL,
        contacted tinyint(1) DEFAULT 0 NOT NULL,
        consent_message text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Додавање на чекбоксот на страницата за плаќање
add_action('woocommerce_review_order_before_submit', 'wec_add_consent_checkbox', 10);
function wec_add_consent_checkbox() {
    $options = get_option('wec_options');
    $consent_text = isset($options['consent_text']) ? $options['consent_text'] : __('I agree to be contacted', 'woocommerce-extra-consent');

    echo '<p class="form-row">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="wec_consent" id="wec_consent" /> 
            <span>' . esc_html($consent_text) . '</span>
        </label>
    </p>';
}

// Проверката на чекбоксот пред плаќање
function wec_validate_checkout_fields() {
    $options = get_option('wec_options');
    $consent_required = isset($options['consent_required']) ? (bool) $options['consent_required'] : false;

    if ($consent_required && empty($_POST['wec_consent'])) {
        $custom_error_message = $options['wec_custom_error_message'];
        if (empty($custom_error_message)) {
            wc_add_notice(__('You must agree to the consent to proceed with the purchase.', 'woocommerce-extra-consent'), 'error');
        } else {
            wc_add_notice(esc_html($custom_error_message), 'error');
        }
    }
}
add_action('woocommerce_checkout_process', 'wec_validate_checkout_fields');


// Зачувување на податоците на клиентите во базата
add_action('woocommerce_checkout_order_processed', 'wec_save_consent_data', 10, 1);
function wec_save_consent_data($order_id) {
    if (isset($_POST['wec_consent'])) {
        global $wpdb;

        $order = wc_get_order($order_id);
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        $options = get_option('wec_options');
        $consent_message = $options['consent_text'];

        $wpdb->insert(
            $wpdb->prefix . 'wec_clients',
            array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'contacted' => 0,
                'consent_message' => $consent_message
            )
        );
    }
}

// Администраторска страница за поставки
add_action('admin_menu', 'wec_admin_menu');
function wec_admin_menu() {
    // Првото подмени ќе биде главно мени
    add_menu_page(__('Extra Consent', 'woocommerce-extra-consent'), __('Extra Consent', 'woocommerce-extra-consent'), 'manage_options', 'wec-uncontacted-clients', 'wec_render_tabs', 'dashicons-yes', 56);

    // Подменија за останатите опции
    add_submenu_page('wec-uncontacted-clients', __('Uncontacted Clients', 'woocommerce-extra-consent'), __('Uncontacted Clients', 'woocommerce-extra-consent'), 'manage_options', 'wec-uncontacted-clients', 'wec_render_tabs');
    add_submenu_page('wec-uncontacted-clients', __('Contacted Clients', 'woocommerce-extra-consent'), __('Contacted Clients', 'woocommerce-extra-consent'), 'manage_options', 'wec-contacted-clients', 'wec_render_tabs');
    add_submenu_page('wec-uncontacted-clients', __('Settings', 'woocommerce-extra-consent'), __('Settings', 'woocommerce-extra-consent'), 'manage_options', 'wec-settings', 'wec_render_tabs');
}



function wec_render_tabs() {
    $tab = isset($_GET['page']) ? $_GET['page'] : 'uncontacted';

    // Прилагодување на активниот таб
    if ($tab == 'wec-uncontacted-clients') {
        $active_tab = 'uncontacted';
    } elseif ($tab == 'wec-contacted-clients') {
        $active_tab = 'contacted';
    } else {
        $active_tab = 'settings';
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Extra Consent Settings', 'woocommerce-extra-consent'); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=wec-uncontacted-clients&tab=uncontacted" class="nav-tab <?php echo $tab == 'uncontacted' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Uncontacted Clients', 'woocommerce-extra-consent'); ?></a>
            <a href="?page=wec-contacted-clients&tab=contacted" class="nav-tab <?php echo $tab == 'contacted' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Contacted Clients', 'woocommerce-extra-consent'); ?></a>
            <a href="?page=wec-settings&tab=settings" class="nav-tab <?php echo $tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'woocommerce-extra-consent'); ?></a>
        </h2>
        <div class="tab-content">
            <?php
            // Врз основа на активниот таб, го рендерираме соодветниот дел од интерфејсот
            if ($active_tab == 'settings') {
                wec_settings_page();
            } elseif ($active_tab == 'uncontacted') {
                wec_uncontacted_clients_page();
            } elseif ($active_tab == 'contacted') {
                wec_contacted_clients_page();
            }
            ?>
        </div>
    </div>
    <?php
}

// Формата за поставки
function wec_settings_page() {
    ?>
    <div class="wrap">
        <h4><?php _e('Extra Consent is an plugin that allows you to display an additional consent request on the Checkout page (for example for Viber membership, WhatsApp group or similar).<br>In Settings you can: set the text of the consent you are looking for; to determine whether the request will be mandatory or not (initially it is set to NOT be mandatory, but if you decide that it is mandatory then it will not be possible to proceed to payment until consent is given); to set an error message that will appear if the user has not given consent (appears only if the request is mandatory).<br>If the buyer agrees, then his data is automatically filled in the Uncontacted table. And when a buyer is contacted (or added to a group or otherwise processed) then just click on the checkbox next to his data and he will automatically move to the Contacted table.<br>Both tables also show the agreement that they entered into, so that if the text of the agreement you are looking are changed, you can know what the buyer agreed to.<br>If the user\'s email is already registered in the plugin database, then this additional field will no longer be displayed.', 'woocommerce-extra-consent'); ?></h4>
        <form method="post" action="options.php">
            <?php
            settings_fields('wec_settings_group');
            do_settings_sections('wec-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Регистрација на поставки
add_action('admin_init', 'wec_register_settings');
function wec_register_settings() {
    register_setting('wec_settings_group', 'wec_options');
    add_settings_section(
        'wec_main_settings',
        __('Main Settings', 'woocommerce-extra-consent'),
        null,
        'wec-settings'
    );

    add_settings_field(
        'consent_text',
        __('Consent Text', 'woocommerce-extra-consent'),
        'wec_consent_text_field',
        'wec-settings',
        'wec_main_settings'
    );

    add_settings_field(
        'consent_required',
        __('Is Consent Required?', 'woocommerce-extra-consent'),
        'wec_consent_required_field',
        'wec-settings',
        'wec_main_settings'
    );

    add_settings_field(
        'wec_custom_error_message',
        __('Custom Error Message', 'woocommerce-extra-consent'),
        'wec_custom_error_message_field',
        'wec-settings',
        'wec_main_settings'
    );
}

function wec_consent_text_field() {
    $options = get_option('wec_options');
    $consent_text = isset($options['consent_text']) ? esc_html($options['consent_text']) : '';
    echo '<textarea id="consent_text" name="wec_options[consent_text]" rows="5" cols="50">' . esc_textarea($consent_text) . '</textarea>';
}

function wec_consent_required_field() {
    $options = get_option('wec_options');
    $consent_required = isset($options['consent_required']) ? (bool) $options['consent_required'] : false;
    echo '<input type="checkbox" id="consent_required" name="wec_options[consent_required]" value="1" ' . checked(1, $consent_required, false) . ' />';
}

function wec_custom_error_message_field() {
    $options = get_option('wec_options');
    $consent_error_message = isset($options['wec_custom_error_message']) ? esc_html($options['wec_custom_error_message']) : '';
    echo '<textarea id="wec_custom_error_message" name="wec_options[wec_custom_error_message]" rows="3" cols="50">' . esc_textarea($consent_error_message) . '</textarea>';
}


// Табеларен приказ на неконтактирани клиенти
function wec_uncontacted_clients_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wec_clients';
    $clients = $wpdb->get_results("SELECT * FROM $table_name WHERE contacted = 0");

    echo '<div class="wrap"><h1>' . __('Uncontacted Clients', 'woocommerce-extra-consent') . '</h1>';
    echo '<table class="wp-list-table widefat fixed striped" style="table-layout: auto;"><thead><tr>
            <th>' . __('First Name', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Last Name', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Email', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Phone', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Consent Message', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Contacted', 'woocommerce-extra-consent') . '</th>
        </tr></thead><tbody>';

    foreach ($clients as $client) {
        echo '<tr>
                <td>' . esc_html($client->first_name) . '</td>
                <td>' . esc_html($client->last_name) . '</td>
                <td>' . esc_html($client->email) . '</td>
                <td>' . esc_html($client->phone) . '</td>
                <td>' . esc_html($client->consent_message) . '</td>
                <td><input type="checkbox" onchange="wec_update_contacted(' . $client->id . ')" /></td>
            </tr>';
    }

    echo '</tbody></table></div>';
}

// Скрипта за ажурирање на статусот контактиран
function wec_update_contacted() {
    ?>
    <script>
        function wec_update_contacted(client_id) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wec_mark_contacted',
                    client_id: client_id,
                },
                success: function(response) {
                    location.reload();
                }
            });
        }
    </script>
    <?php
}
add_action('admin_footer', 'wec_update_contacted');

// AJAX за ажурирање на статусот контактиран
add_action('wp_ajax_wec_mark_contacted', 'wec_mark_contacted');
function wec_mark_contacted() {
    global $wpdb;
    $client_id = intval($_POST['client_id']);
    $wpdb->update($wpdb->prefix . 'wec_clients', array('contacted' => 1), array('id' => $client_id));
    wp_die();
}

// Табеларен приказ на контактирани клиенти
function wec_contacted_clients_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wec_clients';
    $clients = $wpdb->get_results("SELECT * FROM $table_name WHERE contacted = 1");

    echo '<div class="wrap"><h1>' . __('Contacted Clients', 'woocommerce-extra-consent') . '</h1>';
    echo '<table class="wp-list-table widefat fixed striped" style="table-layout: auto;"><thead><tr>
            <th>' . __('First Name', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Last Name', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Email', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Phone', 'woocommerce-extra-consent') . '</th>
            <th>' . __('Consent Message', 'woocommerce-extra-consent') . '</th>
        </tr></thead><tbody>';

    foreach ($clients as $client) {
        echo '<tr>
                <td>' . esc_html($client->first_name) . '</td>
                <td>' . esc_html($client->last_name) . '</td>
                <td>' . esc_html($client->email) . '</td>
                <td>' . esc_html($client->phone) . '</td>
                <td>' . esc_html($client->consent_message) . '</td>
            </tr>';
    }

    echo '</tbody></table></div>';
}
?>
