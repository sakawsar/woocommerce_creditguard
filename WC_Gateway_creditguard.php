<?php
add_action("plugins_loaded", "woocommerce_creditguard_init");
add_filter("woocommerce_payment_gateways", "add_creditguard_gateway");
add_action("wp_ajax_creditguard_view_log", "creditguard_view_log_callback");
add_action("wp_ajax_creditguard_delete_log", "creditguard_delete_log_callback");
function woocommerce_creditguard_init()
{
    if (!class_exists("WC_Payment_Gateway")) {
        return NULL;
    }
    load_plugin_textdomain("talpress-woocommerce-creditguard", false, dirname(plugin_basename(__FILE__)) . "/languages/");
    define("CREDITGUARD_PLUGIN_DIR", plugin_dir_url(__FILE__));
    class WC_Gateway_creditguard extends WC_Payment_Gateway
    {
        private $notify_url = NULL;
        private $creditguard_is_ssl = NULL;
        public $creditguard_term_no = NULL;
        public $creditguard_username = NULL;
        private $creditguard_tiered_payments = NULL;
        private $creditguard_allow_saved_cc = NULL;
        private $creditguard_use_product_descriptions = NULL;
        private $creditguard_debug = NULL;
        private $creditguard_emv = NULL;
        private $creditguard_iframe = NULL;
        private $creditguard_language = NULL;
        private $creditguard_merchant_id = NULL;
        public $creditguard_gateway_url = NULL;
        private $creditguard_payment_type = NULL;
        public $creditguard_currency = NULL;
        public $creditguard_invoice = NULL;
        public $creditguard_invoice_type = NULL;
        private $iframe_width = NULL;
        private $iframe_height = NULL;
        private $logfile = NULL;
        private $creditguard_maxPayments = NULL;
        public $creditguard_password = NULL;
        public $creditguard_encode_request = NULL;
        private $logger = NULL;
        public $creditguard_invoice_vat = NULL;
        public $creditguard_invoice_vat_rate = NULL;
        private $creditguard_url_additional_data = NULL;
        private $tpCore = NULL;
        const TB_TELPRESS_AUTH_PRODUCT_ID = "talpress-woocommerce-creditguard";
        public function __construct()
        {
            $this->id = "creditguard";
            $this->has_fields = false;
            $this->method_title = __("creditguard", "talpress-woocommerce-creditguard");
            $this->method_description = __("Allow payments using creditguard", "talpress-woocommerce-creditguard");
            $this->define_supported_functionality();
            $this->title = __($this->get_option("title"), "talpress-woocommerce-creditguard");
            $this->description = __($this->get_option("description"), "talpress-woocommerce-creditguard");
            $this->creditguard_term_no = $this->get_option("creditguard_term_no");
            $this->creditguard_username = $this->get_option("creditguard_username");
            $this->creditguard_password = $this->get_option("creditguard_password");
            $this->creditguard_merchant_id = $this->get_option("creditguard_merchant_id");
            $this->creditguard_gateway_url = $this->get_option("creditguard_gateway_url");
            $this->creditguard_gateway_url = $this->get_option("creditguard_gateway_url");
            $this->creditguard_gateway_url = $this->get_option("creditguard_gateway_url");
            $this->creditguard_payment_type = $this->get_option("creditguard_payment_type");
            $this->creditguard_is_ssl = $this->get_option("creditguard_is_ssl") == "yes";
            $this->creditguard_allow_saved_cc = $this->get_option("creditguard_allow_saved_cc") == "yes";
            $this->creditguard_use_product_descriptions = $this->get_option("creditguard_use_product_descriptions") == "yes";
            $this->creditguard_invoice_vat = $this->get_option("creditguard_invoice_vat") == "yes";
            $this->creditguard_encode_request = $this->get_option("creditguard_encode_request") == "yes";
            $this->creditguard_invoice = $this->get_option("creditguard_invoice") == "yes";
            $this->creditguard_invoice_type = $this->get_option("\$creditguard_invoice_type");
            $this->creditguard_iframe = $this->get_option("creditguard_iframe") == "yes";
            $this->iframe_width = $this->get_option("creditguard_iframe_width");
            $this->iframe_height = $this->get_option("creditguard_iframe_height");
            $this->creditguard_currency = $this->get_option("creditguard_currency");
            $this->creditguard_language = $this->get_option("creditguard_language");
            $this->creditguard_maxPayments = $this->get_option("creditguard_maxPayments");
            $this->creditguard_tiered_payments = $this->get_option("creditguard_tiered_payments");
            $this->creditguard_url_additional_data = $this->get_option("creditguard_url_additional_data");
            $this->creditguard_debug = $this->get_option("creditguard_debug") == "yes";
            $this->creditguard_emv = $this->get_option("creditguard_emv") == "yes" ? "2000" : "1001";
            $this->logfile = "talpress-woocommerce-creditguard";
            $this->init_logger();
            $this->build_notify_url();
            $this->add_hooks();
            $this->init_form_fields();
            $this->init_settings();
        }
        public function creditguard_callback_handler()
        {
            @ob_clean();
            $data = $_GET;
            if (empty($_GET)) {
                $data = $_POST;
            } else {
                if (count($_GET) == 1) {
                    $data = $_POST;
                }
            }
            $this->log("[INFO]: Return GET: " . print_r($data, true));
            if (isset($data["ErrorCode"]) && $data["ErrorCode"] != 0) {
                $this->display_error_and_die($data);
            }
            if (!empty($data)) {
                $order_id = $data["uniqueID"];
                $order_id_array = explode("-", $order_id);
                $order_id = $order_id_array[0];
                $this->log("[INFO]: Order id : " . $order_id);
                $order = wc_get_order($order_id);
                $this->validate_order($data, $order);
                WC()->cart->empty_cart();
                wp_redirect($this->get_return_url($order));
            } else {
                wp_die(__("Creditguard payment failure", "talpress-woocommerce-creditguard"));
            }
        }
        public function init_form_fields()
        {
            $this->form_fields = array_merge($this->generate_general_fields(), $this->generate_display_fields(), $this->generate_credentials_fields(), $this->generate_payments_fields(), $this->generate_advanced_fields(), $this->generate_log_fields());
        }
        public function generate_log_fields()
        {
            $fields = ["creditguard_debug" => ["title" => __("Debug", "talpress-woocommerce-creditguard"), "type" => "checkbox", "label" => __("Debugging Mode", "talpress-woocommerce-creditguard"), "default" => "no"]];
            return $fields;
        }
        public function generate_advanced_fields()
        {
            $fields = ["creditguard_payment_type" => ["title" => __("Payment Type", "talpress-woocommerce-creditguard"), "type" => "select", "options" => ["AutoComm" => __("Process", "talpress-woocommerce-creditguard"), "Verify" => __("Capture", "talpress-woocommerce-creditguard")], "default" => "Process"], "creditguard_allow_saved_cc" => ["title" => __("Allow Saved Tokens", "talpress-woocommerce-creditguard"), "type" => "checkbox", "description" => __("Allow returning customers to pay with a previously saved TOKEN", "talpress-woocommerce-creditguard"), "default" => "no"], "creditguard_is_ssl" => ["title" => __("Force SSL", "talpress-woocommerce-creditguard"), "type" => "checkbox", "description" => __("Use when site is using SSL", "talpress-woocommerce-creditguard"), "default" => "no"], "creditguard_emv" => ["title" => __("EMV", "talpress-woocommerce-creditguard"), "type" => "checkbox", "default" => "yes"], "creditguard_use_product_descriptions" => ["title" => __("Use product descriptions ", "talpress-woocommerce-creditguard"), "type" => "checkbox", "description" => __("Send the product descriptions instead of the order number", "talpress-woocommerce-creditguard"), "default" => "no"], "creditguard_invoice" => ["title" => __("Invoices", "talpress-woocommerce-creditguard"), "type" => "checkbox", "label" => __("Generate CreditGuard invoices", "talpress-woocommerce-creditguard"), "default" => "no"], "\$creditguard_invoice_type" => ["title" => __("Type", "talpress-woocommerce-creditguard"), "type" => "select", "options" => ["taxreceipt" => __("Tax Invoice", "talpress-woocommerce-creditguard"), "receipt" => __("Receipt", "talpress-woocommerce-creditguard"), "receiptdonation" => __("Donation", "talpress-woocommerce-creditguard")], "default" => "taxreceipt"], "creditguard_invoice_vat" => ["title" => __("VAT", "talpress-woocommerce-creditguard"), "type" => "checkbox", "label" => __("Include VAT", "talpress-woocommerce-creditguard"), "default" => "yes"], "creditguard_encode_request" => ["title" => __("Encoding", "talpress-woocommerce-creditguard"), "type" => "checkbox", "label" => __("Disable this option if you are using WPML with language parameter.", "talpress-woocommerce-creditguard"), "default" => "yes"],"creditguard_url_additional_data" => ["title" => __("Send additional data", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("Add more data to the URL", "talpress-woocommerce-creditguard")]];
            return $fields;
        }
        public function generate_display_fields()
        {
            $fields = ["creditguard_iframe" => ["title" => __("Use Iframe", "talpress-woocommerce-creditguard"), "type" => "checkbox", "label" => __("Load the secured Creditguard page in an iframe", "talpress-woocommerce-creditguard"), "default" => "yes"], "creditguard_iframe_width" => ["title" => __(" Iframe Width", "talpress-woocommerce-creditguard"), "type" => "number", "description" => __("Set the Iframe width in PX , leave blank for 100%", "talpress-woocommerce-creditguard"), "default" => "no"], "creditguard_iframe_height" => ["title" => __(" Iframe Height", "talpress-woocommerce-creditguard"), "type" => "number", "description" => __("Set the Iframe height in PX ", "talpress-woocommerce-creditguard"), "default" => "500"]];
            return $fields;
        }
        public function generate_credentials_fields()
        {
            $fields = ["creditguard_term_no" => ["title" => __("Terminal number", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("Creditguard terminal number", "talpress-woocommerce-creditguard"), "default" => "0962832"], "creditguard_username" => ["title" => __("Terminal username", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("Creditguard terminal username", "talpress-woocommerce-creditguard"), "default" => "Terminal Username"], "creditguard_password" => ["title" => __("Terminal password", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("Creditguard terminal password", "talpress-woocommerce-creditguard"), "default" => "Terminal Password"], "creditguard_merchant_id" => ["title" => __("Merchant id", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("Creditguard Merchand ID", "talpress-woocommerce-creditguard"), "default" => "Merchant ID"], "creditguard_gateway_url" => ["title" => __("Gateway URL", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("Creditguard gateway URL", "talpress-woocommerce-creditguard"), "default" => "https://cgpay2.creditguard.co.il/xpo/Relay"]];
            return $fields;
        }
        public function generate_payments_fields()
        {
            $fields = ["creditguard_maxPayments" => ["title" => __("Max Payments", "talpress-woocommerce-creditguard"), "type" => "number", "description" => __("Set the maximum allowed payments.", "talpress-woocommerce-creditguard"), "default" => "1"], "creditguard_tiered_payments" => ["title" => __("Tiered Payments", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("This Overrides the Max Payments setting !!! to use conditional payment simply enter a comma separated amounts e.g 200,400,600 -> this will set the payments to be 1 for lower than 200 , 2 for lower than 400 etc ...", "talpress-woocommerce-creditguard")]];
            return $fields;
        }
        public function generate_general_fields()
        {
            $fields = ["enabled" => ["title" => __("Enable/Disable", "talpress-woocommerce-creditguard"), "type" => "checkbox", "label" => __("Enable payment with CreditGuard", "talpress-woocommerce-creditguard"), "default" => "no"], "title" => ["title" => __("Checkout Title", "talpress-woocommerce-creditguard"), "type" => "text", "description" => __("Set the title for Checkout page.", "talpress-woocommerce-creditguard"), "default" => __("Secure payment with CreditGuard", "talpress-woocommerce-creditguard")], "description" => ["title" => __("Checkout Description", "talpress-woocommerce-creditguard"), "type" => "textarea", "description" => __("Description on checkout page.", "talpress-woocommerce-creditguard"), "default" => __("Secure payment with CreditGuard", "talpress-woocommerce-creditguard")], "creditguard_language" => ["title" => __("Language", "talpress-woocommerce-creditguard"), "type" => "select", "options" => ["Heb" => __("Hebrew", "talpress-woocommerce-creditguard"), "Eng" => __("English", "talpress-woocommerce-creditguard"), "auto" => __("Auto", "talpress-woocommerce-creditguard")], "default" => "auto"], "creditguard_currency" => ["title" => __("Currency", "talpress-woocommerce-creditguard"), "type" => "select", "options" => ["ILS" => __("New Israeli Shekel", "talpress-woocommerce-creditguard"), "USD" => __("United States Dollar", "talpress-woocommerce-creditguard"), "GBP" => __("Great Britain Pound", "talpress-woocommerce-creditguard"), "HKD" => __("Hong Kong Dollar", "talpress-woocommerce-creditguard"), "JPY" => __("Japanese Yen", "talpress-woocommerce-creditguard"), "EUR" => __("European currency unit", "talpress-woocommerce-creditguard"), "auto" => __("Auto", "talpress-woocommerce-creditguard")], "default" => "auto"]];
            return $fields;
        }
        public function receipt_page($order)
        {
            do_action("telpress_wc_creditguard_before_form");
            echo $this->generate_creditguard_form($order);
            do_action("telpress_wc_creditguard_after_form");
        }
        public function generate_creditguard_form($order_id)
        {
            $order = wc_get_order($order_id);
            $this->log("[INFO]: order_id: " . $order_id);
            $this->log("[INFO]: order_total: " . $order->get_total());
            $this->log("[INFO]: Notify URL: " . $this->notify_url);
            list($token, $expiration, $authorization) = $this->get_user_meta_data($order);
            $creditType = "RegularCredit";
            $order_total = $order->get_total();
            $is_free_trial = false;
            if ($order_total == 0 || (function_exists("wcs_is_subscription") ? wcs_is_subscription($order_id) : false)) {
                $is_free_trial = true;
                $order_total = 1;
                echo "<div class=\"trial-order-notice\">" . __("This is a verification order just to replace your billing information", "talpress-woocommerce-creditguard") . "</div>";
            }
            $max_payments = $this->getMaxPayments($order_total);
            if (1 < $max_payments) {
                $creditType = "Payments";
            } else {
                $max_payments = "";
            }
            $language = $this->get_language();
            $currency = $this->creditguard_currency;
            if ($currency == "auto") {
                $currency = $order->get_currency();
            }
            $total = $order_total * 100;
            $first_payment = get_post_meta($order_id, "_first_payment", true);
            $credit_card_type = "new";
            if (isset($_GET["credit_card_type"])) {
                $credit_card_type = $_GET["credit_card_type"];
            }
            if ($token != "" && $credit_card_type != "new" && $this->creditguard_allow_saved_cc) {
                $this->handle_saved_cc_payment($order_id, $language, $token, $expiration, $creditType, $currency, $total, $order, $first_payment);
            }
            $post_string = $this->build_post_XML($language, $total, $creditType, $currency, $max_payments, $order, $is_free_trial);
            $response = "";
            $request_err = $this->sendRequest($this->creditguard_gateway_url, $post_string, $response);
            if (is_wp_error($request_err)) {
                exit($request_err->get_error_message());
            }
            $mpiHostedPageUrl = $this->parse_result($response);
            return $this->build_form($mpiHostedPageUrl, $order);
        }
        public function payment_fields()
        {
            $token = get_user_meta(get_current_user_id(), "creditguard-token", true);
            $this->log("[INFO]: creditguard token: " . $token);
            if ($token == "" || !$this->creditguard_allow_saved_cc) {
                echo $this->description;
                echo "<br>";
            } else {
                $arr = [];
                if (isset($_POST["post_data"])) {
                    parse_str($_POST["post_data"], $arr);
                }
                $credit_card_type = !isset($arr["credit_card_type"]) ? "new" : $arr["credit_card_type"];
                $new_checked = $credit_card_type == "new" ? " checked=\"checked\"" : "";
                $saved_checked = "";
                $saved_disabled = "disabled=\"disabled\" style=\"background-color: lightgray;\"";
                if ($credit_card_type == "saved") {
                    $saved_checked = "checked=\"checked\"";
                    $saved_disabled = "";
                }
                echo "<input type=\"radio\" style=\"display:inline\" " . $new_checked . " id=\"credit_card_type_new\" name=\"credit_card_type\" value=\"new\" onclick=\"\r\n\t\t\t\tdocument.getElementById('payments').disabled = true;\r\n\t\t\t\tdocument.getElementById('payments').style.backgroundColor = 'lightgray'\">" . __("New credit card", "talpress-woocommerce-creditguard") . "</input >";
                echo "<br>";
                echo "<input type=\"radio\" style=\"display:inline\" id=\"credit_card_type_saved\"  name=\"credit_card_type\"  " . $saved_checked . "  value=\"saved\" onclick=\"\r\n\t\t\t\tdocument.getElementById('payments').disabled = false;\r\n\t\t\t\tdocument.getElementById('payments').style.backgroundColor = 'white'\">" . __("Saved credit card that ends with : ", "talpress-woocommerce-creditguard") . get_user_meta(get_current_user_id(), "creditguard-number", true) . "\r\n\t\t\t\t</input >";
                echo "<br><div id=\"saved_cc_area\" style=\" padding-right: 30px;\"><div id=\"saved_cc_area\" style=\" padding-right: 30px;\">";
                $maxPayments = $this->getMaxPayments($this->get_order_total());
                $this->log("[INFO]: Max payments : " . $maxPayments);
                if ($maxPayments == 1) {
                    echo "<div>";
                    return NULL;
                }
            }
            if (class_exists("TB_Payments_Table")) {
                echo TB_Payments_Table::get_payments_table();
                echo "<div>";
            } else {
                $maxPayments = $this->getMaxPayments($this->get_order_total());
                $post_payments = isset($arr["payments"]) ? $arr["payments"] : 1;
                _e("please select the number of payments", "talpress-woocommerce-creditguard");
                echo "<br>";
                echo "<label for=\"payments\">" . __("Payments : ", "talpress-woocommerce-creditguard") . "</label><select id=\"payments\" name=\"payments\" " . $saved_disabled . ">";
                for ($i = 1; $i <= $maxPayments; $i++) {
                    $selected = $post_payments == $i ? " selected " : "";
                    echo "<option " . $selected . " value=" . $i . ">" . $i . "</option>";
                }
                echo "</select><div><div>";
            }
        }
        public function get_title()
        {
            $title = __($this->get_option("title"), "talpress-woocommerce-creditguard");
            return $title;
        }
        public function log($msg)
        {
            if ($this->creditguard_debug) {
                $msg = $this->make_log_string($msg);
                $this->logger->add($this->logfile, $msg);
            }
        }
        public function get_attributes_data($item, $order)
        {
            $_product = $item->get_product();
            $attributes = $_product->get_attributes();
            $description = [];
            $this->log("[INFO]: attributes: " . print_r($attributes, true));
            foreach ($attributes as $attribute) {
                if (is_object($attribute)) {
                    $attribute_name = $attribute["name"];
                    $encoded_attribute_name = strtolower(urlencode($attribute_name));
                    $value = $_product->get_attribute($attribute_name);
                    if ($value && $value != "") {
                        $description[] = " " . $attribute_name . " - " . $value;
                    }
                    $attribute_value = $_product->get_attribute($encoded_attribute_name);
                    $value = $attribute_value;
                    if ($value && $value != "") {
                        $description[] = " " . $attribute_name . " - " . $value;
                    }
                    if (isset($attribute_value)) {
                        $value = $attribute_value;
                        if ($value != "") {
                            $description[] = " " . $attribute_name . " - " . $attribute_value;
                        }
                    }
                } else {
                    $att_key = key($attributes);
                    $description[] = " " . $att_key . " - " . $attribute;
                }
            }
            $res = implode("  ", $description);
            if (!empty($description)) {
                $res = "  " . $res;
            }
            return $res;
        }

        public function admin_options()
        {
            $title = __("Creditguard payment gateway", "talpress-woocommerce-creditguard");
            $server = $_SERVER["HTTP_HOST"];
			echo '<a href="https://www.talpress.co.il" target="_blank"><img style="max-width:200px;" class="alignleft" alt="TalPress" src="'. plugin_dir_url(__FILE__) . 'assets/images/talpress.png" /></a>';			echo '<a href="https://www.creditguard.co.il/" target="_blank"><img style="max-width:200px;" class="alignright"  alt="CreditGuard" src="'. plugin_dir_url(__FILE__) . 'assets/images/cg_logo.webp" /></a>';			echo '<div style="clear:both;"></div>';				echo '<div class="top-links">';				echo '<a href="https://docs.talpress.co.il/category/woocomerce-creditguard/" target="_blank">'.__('מדריך למשתמש', 'talpress-woocommerce-creditguard').'</a>';				echo '<a href="mailto:support@talpress.co.il" target="_blank">'.__('תמיכה טכנית', 'talpress-woocommerce-creditguard').'</a>';				echo '<a href="https://www.talpress.co.il" target="_blank">'.__('לאתר TalPress', 'talpress-woocommerce-creditguard').'</a>';				echo '<a href="https://www.creditguard.co.il/" target="_blank">'.__('לאתר קרדיט גארד', 'talpress-woocommerce-creditguard').'</a>';			echo '</div>';
            echo "\r\n\r\n            <ul class=\"tabs\">\r\n                <li class=\"tab-link current\" data-tab=\"general\" id=\"general-tab\">";
            _e("Main", "talpress-woocommerce-creditguard");
            echo "</li>\r\n                <li class=\"tab-link\" data-tab=\"credentials\" id=\"credentials-tab\">";
            _e("Credentials", "talpress-woocommerce-creditguard");
            echo "</li>\r\n                <li class=\"tab-link\" data-tab=\"payments\" id=\"payments-tab\">";
            _e("Payments", "talpress-woocommerce-creditguard");
            echo "</li>\r\n                <li class=\"tab-link\" data-tab=\"display\" id=\"display-tab\">";
            _e("iFrame", "talpress-woocommerce-creditguard");
            echo "</li>\r\n                <li class=\"tab-link\" data-tab=\"advanced\" id=\"advanced-tab\">";
            _e("More Options", "talpress-woocommerce-creditguard");
            echo "</li>\r\n                <li class=\"tab-link\" data-tab=\"log\" id=\"log-tab\">";
            _e("Debugging", "talpress-woocommerce-creditguard");
            echo "</li>\r\n            </ul>\r\n            <table id=\"general\" class=\"form-table tab-content current\">\r\n\t\t\t\t";
            $general_fields = $this->generate_general_fields();
            $this->generate_settings_html($general_fields);
            echo "            </table>\r\n            <table id=\"credentials\" class=\"form-table tab-content\">\r\n\t\t\t\t";
            $credentials_fields = $this->generate_credentials_fields();
            $this->generate_settings_html($credentials_fields);
            echo "            </table>\r\n            <table id=\"payments\" class=\"form-table tab-content\">\r\n\t\t\t\t";
            $payments_fields = $this->generate_payments_fields();
            $this->generate_settings_html($payments_fields);
            echo "            </table>\r\n            <table id=\"display\" class=\"form-table tab-content\">\r\n\t\t\t\t";
            $this->generate_settings_html($this->generate_display_fields());
            echo "            </table>\r\n\r\n            <table id=\"advanced\" class=\"form-table tab-content\">\r\n\t\t\t\t";
            $this->generate_settings_html($this->generate_advanced_fields());
            echo "            </table>\r\n            <table id=\"log\" class=\"form-table tab-content\">\r\n\t\t\t\t";
            $this->generate_settings_html($this->generate_log_fields());
            echo "                <tr>\r\n                    <td colspan=\"2\">\r\n\t\t\t\t\t\t";
            echo "<button onclick=\"view_log();return false;\" id=\"view-log\" class=\"logs-button\" />" . __("View log", "talpress-woocommerce-creditguard") . "</button>";
			echo "<button onclick=\"delete_log();return false;\" id=\"delete-log\" class=\"logs-button\" />" . __("Clear log", "talpress-woocommerce-creditguard") . "</button>";
            echo "<br><br><div><textarea class=\"checklog\" id=\"see-log\" cols=175 rows=20></textarea></div><br><br><br>";  
            echo "                    </td>\r\n                </tr>\r\n\r\n            </table>\r\t\t\t\t";					echo '<div class="legal-cg">'.__("הבהרה ומידע משפטי על שימוש בתוסף", "talpress-woocommerce-creditguard").'  <i class="fa fa-chevron-down"></i></div>';			echo '<div class="legal-content-cg">'.__("יובהר כי האחריות המלאה בגין אופן השימוש בתוסף חל על הלקוח בלבד , מכיוון שאופן הגדרת השימוש בתוסף משתנה מלקוח ללקוח. בנוסף, הסימנים המסחריים של חברת קרדיט גארד אינם שייכים לTalPress, והשימוש בתוסף לא מקנה בהם זכויות. יחד עם זאת אנו כאן לכל שאלה והדרכה.", "talpress-woocommerce-creditguard").'</div>';
        }
        public function getMaxPayments($total)
        {
            $this->log("[INFO]: getMaxPayments - order total: " . $total);
            $max_payments = $this->creditguard_maxPayments;
            if (trim($this->creditguard_tiered_payments) != "") {
                $max_payments = 1;
                $payment_levels = explode(",", $this->creditguard_tiered_payments);
                if ($total < $level) {
                } else {
                    $max_payments += 1;
                }
            }
            return $max_payments;
        }
        public function sendRequest($gateway_url, $request, &$response)
        {
            $this->log("[INFO]: Gateway URL : " . $this->creditguard_gateway_url);
            $this->log("[INFO]: request : " . $request);
            $poststring = "user=" . $this->creditguard_username;
            $poststring .= "&password=" . $this->creditguard_password;
            $request = urlencode($request);
            $poststring .= "&int_in=" . $request;
            $this->log("[INFO]: poststring  : " . $poststring);
            $CR = curl_init();
            curl_setopt($CR, CURLOPT_URL, $gateway_url);
            curl_setopt($CR, CURLOPT_POST, 1);
            curl_setopt($CR, CURLOPT_FAILONERROR, true);
            curl_setopt($CR, CURLOPT_POSTFIELDS, $poststring);
            curl_setopt($CR, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($CR, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($CR, CURLOPT_ENCODING, "UTF-8");
            $response = curl_exec($CR);
            $error = curl_error($CR);
            $err_no = curl_errno($CR);
            curl_close($CR);
            if (!empty($error)) {
                $this->log(sprintf("[INFO]: cURL error: errno: %s, error: %s", $err_no, $error));
                return new WP_Error("tp_CG_cURL_error", $err_no . ": " . $error);
            }
            $this->log("[INFO]: cURL result : " . $response);
            return "";
        }
        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            $params = "";
            if (isset($_POST["payments"])) {
                $params .= "&payments=" . $_POST["payments"];
            }
            if (isset($_POST["credit_card_type"])) {
                $params .= "&credit_card_type=" . $_POST["credit_card_type"];
            }
            return ["result" => "success", "redirect" => $order->get_checkout_payment_url(true) . $params];
        }
        public function process_refund($order_id, $amount = NULL, $reason = "")
        {
            if ($this->supports("refunds")) {
                return apply_filters("tp_creditguard_process_refund", false, $this, $order_id, $amount, $reason);
            }
            return false;
        }
        // public final function check_telpress_auth()
        // {
        //     if (empty($this->tpCore)) {
        //         if (!class_exists("TB_Core\\TB_Core")) {
        //             return false;
        //         }
        //         $this->tpCore = new TB_Core\TB_Core("talpress-woocommerce-creditguard");
        //     }
        //     if ($this->tpCore->isActive()) {
        //         return "gold";
        //     }
        //     return "none";
        // }
        public function handle_saved_cc_payment($order_id, $language, $token, $expiration, $creditType, $currency, $total, $order, $first_payment)
        {
            $postdata = "<ashrait> \r\n\t\t\t\t<request>\r\n\t\t\t\t<command>doDeal</command>\r\n\t\t\t\t<requestId></requestId>\r\n\t\t\t\t<version>" . $this->creditguard_emv . "</version>\r\n\t\t\t\t<language>" . $language . "</language>\r\n\t\t\t\t<doDeal>\r\n\t\t\t\t\t<terminalNumber>" . $this->creditguard_term_no . "</terminalNumber>\r\n\t\t\t\t\t<cardId>" . $token . "</cardId>\r\n\t\t\t\t\t<cardExpiration>" . $expiration . "</cardExpiration>\r\n\t\t\t\t\t<transactionType>Debit</transactionType>\r\n\t\t\t\t\t<creditType>" . $creditType . "</creditType>\r\n\t\t\t\t\t<currency>" . $currency . "</currency>\r\n\t\t\t\t\t<transactionCode>Phone</transactionCode>\r\n\t\t\t\t\t<total>" . $total . "</total>\r\n\t\t\t\t\t<validation>AutoComm</validation>\r\n\t\t\t\t\t<mpiValidation>" . $this->creditguard_payment_type . "</mpiValidation>\r\n\t\t\t\t</doDeal>\r\n\t\t\t\t</request>\r\n\t\t\t\t</ashrait>";
            $this->log("[INFO]: Sending saved credit card data : ");
            $response = "";
            $request_err = $this->sendRequest($this->creditguard_gateway_url, $postdata, $response);
            if (is_wp_error($request_err)) {
                $this->log("[INFO]: Sending saved credit card data failed: " . $request_err->get_error_message());
                $this->display_error_and_die(["ErrorCode" => $request_err->get_error_code(), "ErrorText" => $request_err->get_error_message()]);
            }
            if (strpos(strtoupper($response), "HEB")) {
                $response = iconv("utf-8", "iso-8859-8", $response);
            }
            $response = str_replace("&", "%26amp;", $response);
            $xmlObj = simplexml_load_string($response);
            if ($xmlObj->response->result == "0000") {
                $authNumber = (int) $xmlObj->response->doDeal->authNumber;
                $cardMask = (int) $xmlObj->response->doDeal->cardMask;
                $numberOfPayments = 1;
                $firstPayment = $order->get_total();
                $periodicalPayment = (int) $xmlObj->response->doDeal->periodicalPayment;
                if (0 < @count(@$xmlObj->response->doDeal->numberOfPayments->children())) {
                    $numberOfPayments = $xmlObj->response->doDeal->numberOfPayments[0];
                }
                if (0 < @count(@$xmlObj->response->doDeal->firstPayment->children())) {
                    $firstPayment = $xmlObj->response->doDeal->firstPayment[0];
                }
                $this->add_order_metadata($order_id, $cardMask, $numberOfPayments, $firstPayment, $periodicalPayment);
                $this->save_token($order->get_id(), $token, $expiration, $authNumber);
                if ($this->creditguard_payment_type == "Verify") {
                    $order->add_order_note(__("Creditguard payment via saved cc verified", "talpress-woocommerce-creditguard"));
                    $order->update_status("on-hold");
                } else {
                    $order->add_order_note(__("Creditguard payment via saved cc completed", "talpress-woocommerce-creditguard"));
                    $order->payment_complete();
                }
                wp_redirect($this->get_return_url($order));
                return [$response, $xmlObj];
            }
            $err_arr = ["ErrorCode" => "no-code", "ErrorText" => "CreditGuard result object not 0000"];
            if (isset($xmlObj->response->result)) {
                $err_arr["ErrorCode"] = $xmlObj->response->result;
                $err_arr["ErrorText"] = $xmlObj->response->userMessage;
            }
            $this->display_error_and_die($err_arr);
            return [$response, $xmlObj];
        }
        public function scheduled_subscription_payment($renewal_total, WC_Order $wc_order)
        {
            $this->log("[INFO]: scheduled_subscription_payment start ");
            $this->log("[INFO]: renewal-total: " . $renewal_total);
            $order = wc_get_order($wc_order);
            $this->log(sprintf("[INFO]:%s: token:%s, exp:%s", "WC_Gateway_creditguard::scheduled_subscription_payment", get_post_meta($order->get_id(), "_creditguard_token", true), get_post_meta($order->get_id(), "_creditguard_expiration", true)));
            $result = $this->process_subscription_payment($order, $renewal_total);
            if (is_wp_error($result)) {
                $subscription = reset(wcs_get_subscriptions_for_renewal_order($order->get_id()));
                $parent_order_wc = method_exists($subscription, "get_parent") ? $subscription->get_parent() : $subscription->order;
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($parent_order_wc);
                $this->log("[INFO]: after process_subscription_payment_failure_on_order");
            } else {
                WC_Subscriptions_Manager::process_subscription_payments_on_order($order->get_id());
                $this->log("[INFO]: after process_subscription_payments_on_order");
            }
            $this->log("[INFO]: scheduled_subscription_payment complete");
        }
        public function tp_failing_payment_method($wc_original_order, $wc_new_renewal_order)
        {
            $original_order = wc_get_order($wc_original_order);
            $new_renewal_order = wc_get_order($wc_new_renewal_order);
            $this->log(sprintf("[INFO]: updating parent meta, parent-order: \$s, renewal-order: %s", $original_order->get_id(), $new_renewal_order->get_id()));
            $this->update_parent_meta($new_renewal_order->get_id(), $original_order->get_id(), $new_renewal_order->get_user_id());
        }
        private function update_subscription_parent_order($new_order_id)
        {
            if (wcs_is_subscription($new_order_id)) {
                $subscription = wcs_get_subscription($new_order_id);
                $this->log("order id: " . $new_order_id . " is the actual subscription");
            } else {
                $subscriptions = wcs_order_contains_renewal($new_order_id) ? wcs_get_subscriptions_for_renewal_order($new_order_id) : wcs_get_subscriptions_for_order($new_order_id);
                if (empty($subscriptions)) {
                    $this->log("[INFO]: no subscription associated with order id: " . $new_order_id);
                    return NULL;
                }
                $subscription = reset($subscriptions);
            }
            $parent_order_wc = method_exists($subscription, "get_parent") ? $subscription->get_parent() : $subscription->order;
            $parent_order = wc_get_order($parent_order_wc);
            $parent_order_id = $parent_order->get_id();
            $this->log("id: " . $new_order_id . " parent id: " . $parent_order_id);
            if ($parent_order_id != $new_order_id) {
                $this->log("[INFO]: updating payment data in subscription parent-order");
                $this->update_parent_meta($new_order_id, $parent_order_id, $parent_order->get_user_id());
            }
        }
        private function update_parent_meta($new_order_id, $parent_order_id, $user_id)
        {
            $cardToken = get_post_meta($new_order_id, "_creditguard_token", true);
            $cardExp = get_post_meta($new_order_id, "_creditguard_expiration", true);
            $authNumber = get_post_meta($new_order_id, "_creditguard_authorization", true);
            $cardMask = get_user_meta($user_id, "creditguard-number", true);
            $this->log("[INFO]: parent order# : " . $parent_order_id);
            $this->log("[INFO]: payed order# : " . $new_order_id);
            $this->log("[INFO]: new token: " . $cardToken);
            $this->log("[INFO]: new authorization: " . $authNumber);
            $this->log("[INFO]: new expiration: " . $cardExp);
            $this->log("[INFO]: new card-mask: " . $cardMask);
            delete_post_meta($parent_order_id, "_creditguard_token");
            update_post_meta($parent_order_id, "_creditguard_token", $cardToken);
            delete_post_meta($parent_order_id, "_creditguard_expiration");
            update_post_meta($parent_order_id, "_creditguard_expiration", $cardExp);
            delete_post_meta($parent_order_id, "creditguard_authorization");
            update_post_meta($parent_order_id, "_creditguard_authorization", $authNumber);
            update_post_meta($parent_order_id, "creditguard_payment_order_id", $new_order_id);
            $this->update_user_token($user_id, $cardToken, $cardExp, $cardMask, $authNumber);
        }
        private function process_subscription_payment($order, $amount_to_charge)
        {
            $this->log("[INFO]: process_subscription_payment start ");
            $subscription = reset(wcs_get_subscriptions_for_renewal_order($order->get_id()));
            $parent_order_wc = method_exists($subscription, "get_parent") ? $subscription->get_parent() : $subscription->order;
            $parent_order = wc_get_order($parent_order_wc);
            $this->log("[INFO]: Order id: " . $order->get_id() . ", Parent order id: " . $parent_order->get_id());
            $cardToken = get_post_meta($parent_order->get_id(), "_creditguard_token", true);
            $cardExp = get_post_meta($parent_order->get_id(), "_creditguard_expiration", true);
            $authNumber = get_post_meta($parent_order->get_id(), "_creditguard_authorization", true);
            $order_payments = get_post_meta($parent_order->get_id(), "_payments", true);
            $first_payment = get_post_meta($parent_order->get_id(), "_first_payment", true);
            $periodical_payment = get_post_meta($parent_order->get_id(), "_periodical_payment", true);
            $this->log("[INFO]: cardToken : " . $cardToken);
            $this->log("[INFO]: cardExp : " . $cardExp);
            $this->log("[INFO]: authNumber : " . $authNumber);
            $this->log("[INFO]: numberOfPayments + 1 : " . $order_payments);
            $this->log("[INFO]: firstPayment : " . $first_payment);
            $this->log("[INFO]: periodicalPayment : " . $periodical_payment);
            $order_total = round($amount_to_charge, get_option("woocommerce_price_num_decimals")) * 100;
            $currency = $this->creditguard_currency;
            if ($currency == "auto") {
                $currency = $order->get_currency();
            }
            $this->log("[INFO]: currency : " . $currency);
            $post_data = "<ashrait> \r\n\t\t\t\t<request>\r\n\t\t\t\t<command>doDeal</command>\r\n\t\t\t\t<requestId></requestId>\r\n\t\t\t\t<version>" . $this->creditguard_emv . "</version>\r\n\t\t\t\t<language>" . $this->get_language() . "</language>\r\n\t\t\t\t<doDeal>\r\n\t\t\t\t\t<terminalNumber>" . $this->creditguard_term_no . "</terminalNumber>\r\n\t\t\t\t\t<cardId>" . $cardToken . "</cardId>\r\n\t\t\t\t\t<cardExpiration>" . $cardExp . "</cardExpiration>\r\n\t\t\t\t\t<transactionType>Debit</transactionType>\r\n\t\t\t\t\t<currency>" . $currency . "</currency>\r\n\t\t\t\t\t<transactionCode>Phone</transactionCode>\r\n\t\t\t\t\t<total>" . $order_total . "</total>\r\n\t\t\t\t\t##payments##\r\n\t\t\t\t\t<validation>AutoComm</validation>\r\n\t\t\t\t\t##invoice## \r\n\t\t\t\t\t##custom##\r\n\t\t\t\t</doDeal>\r\n\t\t\t\t</request>\r\n\t\t\t\t</ashrait>";
            $payments = "";
            if (1 < $order_payments) {
                $payments = "<numberOfPayments>" . (int) ($order_payments - 1) . "</numberOfPayments>\r\n\t\t\t\t<periodicalPayment>" . trim($periodical_payment) . "</periodicalPayment>\r\n\t\t\t\t<firstPayment>" . trim($first_payment) . "</firstPayment>\r\n\t\t\t\t<creditType>Payments</creditType>";
            } else {
                $payments = "<creditType>RegularCredit</creditType>";
            }
            $custom = [];
            $custom = apply_filters("tp_creditguard_custom", $custom, $order->get_id());
            if (!empty($custom)) {
                $post_custom = "";
                foreach ($custom as $item => $value) {
                    $post_custom .= "<" . $item . ">" . $value . "</" . $item . ">";
                }
                $post_string = str_replace("##custom##", $post_custom, $post_data);
            }
            $post_data = str_replace("##payments##", $payments, $post_data);
            $invoice_xml = "";
            $items_xml = "";
            $post_data = $this->build_invoice_xml($order, $post_data);
            $response = "";
            $request_err = $this->sendRequest($this->creditguard_gateway_url, $post_data, $response);
            if (is_wp_error($request_err)) {
                return $request_err;
            }
            if (strpos(strtoupper($response), "HEB")) {
                $response = iconv("utf-8", "iso-8859-8", $response);
            }
            $response = str_replace("&", "%26amp;", $response);
            $xmlObj = simplexml_load_string($response);
            $cg_result = $xmlObj->response->result->__toString();
            $cg_msg = $xmlObj->response->message->__toString();
            add_post_meta($order->get_id(), "CG_renewal_result", $cg_result);
            add_post_meta($order->get_id(), "CG_renewal_message", $cg_msg);
            add_post_meta($order->get_id(), "CG_renewal_time", time());
            update_post_meta($order->get_id(), "_payments", $order_payments);
            update_post_meta($order->get_id(), "_first_payment", $first_payment);
            $this->add_more_order_metadata($order->get_id(), $xmlObj->response);
            $order->add_order_note(sprintf("Renewal %s: %s[%s]", $cg_result == "0000" ? "OK" : "Failed", $cg_msg, $cg_result));
            if ($cg_result != "0000") {
                return new WP_Error("Renewal failed", __("renewal failed", "talpress-woocommerce-creditguard"));
            }
            $invoice_xml = $xmlObj->response->doDeal->invoice;
            $this->get_invoice_data($order->get_id(), $invoice_xml);
            $order->payment_complete();
            return $xmlObj->response->result;
        }
        public function add_hooks()
        {
            add_action("woocommerce_subscriptions_changed_failing_payment_method_" . $this->id, "tp_failing_payment_method", 10, 2);
            add_action("woocommerce_scheduled_subscription_payment_" . $this->id, [$this, "scheduled_subscription_payment"], 10, 2);
            add_action("processed_subscription_payments_for_order", [$this, "tp_scheduled_subscription_payment"]);
            add_action("woocommerce_receipt_creditguard", [$this, "receipt_page"]);
            add_action("woocommerce_update_options_payment_gateways_" . $this->id, [$this, "process_admin_options"]);
            add_action("woocommerce_api_wc_gateway_creditguard", [$this, "creditguard_callback_handler"]);
            if ($this->creditguard_iframe) {
                add_action("woocommerce_thankyou", [$this, "break_out_of_frames"]);
                add_action("woocommerce_before_checkout_form", [$this, "break_out_of_frames"]);
            }
        }
        public function break_out_of_frames()
        {
            if (!is_preview()) {
                echo "\n<script type=\"text/javascript\">\n<!--\nif (window != top) top.location.href = location.href;\n-->\n</script>\n\n\n<!--\nif (window != top) top.location.href = location.href;\n-->\n</script>\n\n";
            }
        }
        public function display_error_and_die($data)
        {
            global $woocommerce;
            $payment_error = __("Payment failure, please try again or contact the store administrator", "talpress-woocommerce-creditguard");
            $payment_error .= "<br/>";
            $payment_error .= __("Error code :", "talpress-woocommerce-creditguard");
            $payment_error .= "<br/>";
            $payment_error .= $data["ErrorCode"];
            $payment_error .= "<br/>";
            $payment_error .= __("Error text :", "talpress-woocommerce-creditguard");
            $payment_error .= "<br/>";
            $payment_error .= $data["ErrorText"];
            $payment_error .= "<br/>";
            $payment_error .= "<br/>";
            $checkout_url = wc_get_checkout_url();
            $payment_error .= sprintf(__("Click <a href=\"%s\">here </a>to return to the checkout page.", "talpress-woocommerce-creditguard"), $checkout_url);
            wp_die($payment_error);
        }
        public function get_language()
        {
            $language = $this->creditguard_language;
            $this->log("Configuration language : " . $language);
            if ($this->creditguard_language == "auto") {
                $language = get_bloginfo("language");
                $this->log("Site language : " . $language);
                if ($language == "en-US") {
                    $language = "ENG";
                }
                if ($language == "he-IL") {
                    $language = "HEB";
                }
            }
            $this->log("Returned language : " . $language);
            return $language;
        }
        public function update_user_token($user_id, $cardToken, $cardExp, $cardMask, $authorization)
        {
            $this->log("[INFO]: user id: " . $user_id);
            update_user_meta($user_id, "creditguard-token", $cardToken);
            update_user_meta($user_id, "creditguard-expiration", $cardExp);
            update_user_meta($user_id, "creditguard-number", substr($cardMask, -4));
            update_user_meta($user_id, "creditguard-authorization", $authorization);
        }
        public function build_invoice_xml($order, $post_string)
        {
            $invoice_xml = "";
            $items_xml = "";
            $vat = $this->creditguard_invoice_vat ? "1" : "0";
            $vat_rate_str = "<invoiceTaxRate/>";
            if ($this->creditguard_invoice) {
                $address = $order->get_billing_address_1() . " " . $order->get_billing_address_2();
                $invoice_xml = "<invoice>\r\n                <invoiceCreationMethod>wait</invoiceCreationMethod>\r\n                <invoiceDate>" . date("Y-m-d") . "</invoiceDate>\r\n                <ccDate>" . date("Y-m-d") . "</ccDate>\r\n                <invoiceSubject>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_invoice_subject", $order->get_id(), $order)) . "</invoiceSubject>\r\n                <invoiceDiscount>0</invoiceDiscount>\r\n                <invoiceDiscountRate/>\r\n                ##items##" . $vat_rate_str . "<clientAddress>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_client_address", $address, $order)) . "</clientAddress>\t\r\n                <clientOsekNum>" . apply_filters("tp_creditguard_gateway_client_oseknum", "", $order) . "</clientOsekNum>\r\n                <invoiceComments>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_invoice_comments", "", $order)) . "</invoiceComments>\r\n                <DocRemark>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_doc_remark", "", $order)) . "</DocRemark>\r\n                <GeneralRemark>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_general_remark", "", $order)) . "</GeneralRemark>\r\n                <companyInfo>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_company", $order->get_billing_first_name() . " " . $order->get_billing_last_name(), $order)) . "</companyInfo>\r\n                <mailTo>" . apply_filters("tp_creditguard_gateway_billing_email", $order->get_billing_email(), $order) . "</mailTo>\r\n                <invoiceType>" . $this->creditguard_invoice_type . "</invoiceType>\r\n                <isItemPriceWithTax>" . $vat . "</isItemPriceWithTax>\r\n            </invoice>";
                $items_xml = $this->build_items_xml($order);
            }
            $post_string = str_replace("##invoice##", $invoice_xml, $post_string);
            $post_string = str_replace("##items##", $items_xml, $post_string);
            return $post_string;
        }
        public function clean_result($result)
        {
            $this->log("[INFO]: clean result start : " . $result);
            if (strpos(strtoupper($result), "HEB")) {
                $result = iconv("utf-8", "iso-8859-8", $result);
            }
            $result = str_replace("&", "%26amp;", $result);
            $this->log("[INFO]: clean result end : " . $result);
            return $result;
        }
        public function parse_result($result)
        {
            $pattern = "/<mpiHostedPageUrl>.*<\\/mpiHostedPageUrl>/";
            preg_match($pattern, $result, $matches);
            $this->log("[INFO]: pattern : " . $pattern);
            $this->log("[INFO]: matches : " . print_r($matches, true));
            if (0 < sizeof($matches)) {
                $returned_url = $matches[0];
                $returned_url = str_replace("<mpiHostedPageUrl>", "", $returned_url);
                $returned_url = str_replace("</mpiHostedPageUrl>", "", $returned_url);
                $this->log("[INFO]: mpiHostedPageUrl : " . $returned_url);
                return $returned_url;
            }
            header("Content-Type: text/html; charset=utf-8");
            $xmlObj = simplexml_load_string($result);
            exit("<strong>Can't Create Transaction</strong> <br />Error Code: " . $xmlObj->response->result . "<br />" . "Message: " . $xmlObj->response->message . "<br />" . "Addition Info: " . $xmlObj->response->additionalInfo);
        }
        public function build_form($mpiHostedPageUrl, $order)
        {
            $iframe = "";
            $target = "";
            $iframe_gateway = "";
            if ($this->creditguard_iframe) {
                $width = !empty($this->creditguard_iframe_width) ? $this->iframe_width . "px" : "100%";
                $height = !empty($this->creditguard_iframe_height) ? $this->iframe_height . "px" : "800px";
                $iframe = "<iframe style=\"border:none;\" name=\"chekout_frame\" src=\"" . $mpiHostedPageUrl . "\" id=\"chekout_frame\" width=\"" . $width . "\" height=\"" . $height . "\"></iframe>  ";
                $target = "target=\"chekout_frame\" style=\"display:none\"";
            } else {
                wp_redirect($mpiHostedPageUrl);
            }
            $result = $iframe . "<form action=\"" . $mpiHostedPageUrl . "\" method=\"get\" id=\"creditguard_payment_form\" " . $target . ">\r\n\t\t\t\t<input type=\"submit\" class=\"button-alt\" id=\"submit_creditguard_payment_form\" value=\"" . __("Pay via Creditguard", "talpress-woocommerce-creditguard") . "\" /> <a class=\"button cancel\" href=\"" . $order->get_cancel_order_url() . "\">" . __("Cancel order &amp; restore cart", "talpress-woocommerce-creditguard") . "</a>\r\n\t\t\t\t</form>";
            $result .= "<script type=\"text/javascript\">jQuery(function(){jQuery(\"#submit_creditguard_payment_form\").click();});</script>";
            return $iframe;
        }
        public function build_post_XML($language, $total, $credit_type, $currency, $max_payments, $order, $is_free_trial)
        {
            $unique_id = $order->get_id() . "-" . rand(0, 100000);
            $creditguard_payment_type = $this->creditguard_payment_type;
            if ($is_free_trial) {
                $creditguard_payment_type = "Verify";
            }
            $items = $order->get_items();
            $description = $this->generate_description($order, $items);
            if (249 < strlen($description)) {
                $description = mb_substr($description, 0, 245) . "...";
            }
            $address = $this->prepare_str_for_xml($order->get_billing_address_1() . " " . $order->get_billing_address_2());
            $post_string = "\r\n<ashrait>\r\n    <request>\r\n\t<version>" . $this->creditguard_emv . "</version>\r\n\t<language>" . $language . "</language>\r\n\t<command>doDeal</command>\r\n        <doDeal>\r\n            <terminalNumber>" . $this->creditguard_term_no . "</terminalNumber>\r\n            <cardNo>CGMPI</cardNo>\r\n            <total>" . $total . "</total>\r\n            <transactionType>Debit</transactionType>\r\n            <creditType>" . $credit_type . "</creditType>\r\n            <currency>" . $currency . "</currency>\r\n            <transactionCode>Phone</transactionCode>\r\n            ##payments##\r\n            <validation>TxnSetup</validation>\r\n            <user>" . apply_filters("tp_creditguard_gateway_user", $order->get_id(), $order) . "</user>\r\n            <mid>" . $this->creditguard_merchant_id . "</mid>\r\n            <uniqueid>" . $unique_id . "</uniqueid>\r\n            <mpiValidation>" . $creditguard_payment_type . "</mpiValidation>\r\n            <description>" . $description . "</description>\r\n            <email>" . apply_filters("tp_creditguard_gateway_billing_email", $order->get_billing_email(), $order) . "</email>\r\n            <successUrl>" . $this->notify_url . "</successUrl>\r\n            <errorUrl>" . $this->notify_url . "</errorUrl>\r\n                <customerData>\r\n                    <firstName>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_billing_first_name", $order->get_billing_first_name(), $order)) . "</firstName>\r\n                    <lastName>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_billing_last_name", $order->get_billing_last_name(), $order)) . "</lastName>\r\n                    <address>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_billing_address", $address, $order)) . "</address>\r\n                    <city>" . $this->prepare_str_for_xml(apply_filters("tp_creditguard_gateway_billing_city", $order->get_billing_city(), $order)) . "</city>\r\n                    <email>" . apply_filters("tp_creditguard_gateway_billing_email", $order->get_billing_email(), $order) . "</email>\r\n                    <tel>" . $this->input_cleanup_phone(apply_filters("tp_creditguard_gateway_billing_phone", $order->get_billing_phone(), $order)) . "</tel>\r\n                    <userData1>" . apply_filters("tp_creditguard_gateway_user_data_1", "", $order) . " </userData1>\r\n                    <userData2>" . apply_filters("tp_creditguard_gateway_user_data_2", "", $order) . "</userData2>\r\n                    <userData3>" . apply_filters("tp_creditguard_gateway_user_data_3", "", $order) . "</userData3>\r\n                    <userData4>" . apply_filters("tp_creditguard_gateway_user_data_4", "", $order) . "</userData4>\r\n                    <userData5>" . apply_filters("tp_creditguard_gateway_user_data_5", "", $order) . "</userData5>\r\n                    <userData6>" . apply_filters("tp_creditguard_gateway_user_data_6", "", $order) . "</userData6>\r\n                    <userData7>" . apply_filters("tp_creditguard_gateway_user_data_7", "", $order) . "</userData7>\r\n                    <userData8>" . apply_filters("tp_creditguard_gateway_user_data_8", "", $order) . "</userData8>\r\n                    <userData9>" . apply_filters("tp_creditguard_gateway_user_data_9", "", $order) . "</userData9>\r\n                    <userData10>" . apply_filters("tp_creditguard_gateway_user_data_10", "", $order) . "</userData10>\r\n                </customerData>\r\n            ##invoice##\r\n            ##custom##\r\n        </doDeal>\r\n    </request>\r\n</ashrait>";
            if (!$is_free_trial) {
                $post_string = $this->build_invoice_xml($order, $post_string);
            }
            if (isset($_GET["payments"])) {
                $post_string = str_replace("##payments##", "<numberOfPayments>" . $_GET["payments"] . "-" . $_GET["payments"] . "</numberOfPayments>", $post_string);
            } else {
                $post_string = str_replace("##payments##", "<numberOfPayments>" . $max_payments . "</numberOfPayments>", $post_string);
            }
            $custom = [];
            $custom = apply_filters("tp_creditguard_custom", $custom, $order->get_id());
            if (!empty($custom)) {
                $post_custom = "";
                foreach ($custom as $item => $value) {
                    $post_custom .= "<" . $item . ">" . $value . "</" . $item . ">";
                }
                $post_string = str_replace("##custom##", $post_custom, $post_string);
            }
            return $post_string;
        }
        private function prepare_str_for_xml($input_to_clean)
        {
            $cleaned_str = $input_to_clean;
            if ($cleaned_str != "") {
                $cleaned_str = str_replace("\"", "", $cleaned_str);
                $cleaned_str = str_replace("'", "", $cleaned_str);
                $cleaned_str = str_replace("&lrr", "", $cleaned_str);
                $cleaned_str = preg_replace("/[^a-zA-Z0-9א-ת ]+/", " ", $cleaned_str);
                $this->log("[INFO]: after: " . $cleaned_str);
            }
            return $cleaned_str;
        }
        private function input_cleanup_phone($input_to_clean)
        {
            return $this->prepare_str_for_xml($input_to_clean);
        }
        public function generate_description($order, $items)
        {
            $description = $order->get_id();
            if ($this->creditguard_use_product_descriptions) {
                $description = "";
                foreach ($items as $item) {
                    $description .= $item->get_name() . " ";
                    $description .= $this->get_attributes_data($item, $order);
                }
                $description = remove_accents($description);
            }
            $description = preg_replace("/[^a-zA-Z0-9א-ת ]+/", " ", $description);
            return $description;
        }
        public function get_user_meta_data($order)
        {
            $token = get_user_meta($order->get_user_id(), "creditguard-token", true);
            $expiration = get_user_meta($order->get_user_id(), "creditguard-expiration", true);
            $autorization = get_user_meta($order->get_user_id(), "creditguard-authorization", true);
            return [$token, $expiration, $autorization];
        }
        public function build_inquireTransactions_string($language, $data)
        {
            $poststring = "<ashrait>\r\n\t\t\t\t\t\t\t\t\t<request>\r\n\t\t\t\t\t\t\t\t\t<version>" . $this->creditguard_emv . "</version>\r\n\t\t\t\t\t\t\t\t\t <language>" . $language . "</language>\r\n\t\t\t\t\t\t\t\t\t <command>inquireTransactions</command>\r\n\t\t\t\t\t\t\t\t\t <inquireTransactions>\r\n\t\t\t\t\t\t\t\t\t  <terminalNumber>" . $this->creditguard_term_no . "</terminalNumber>\r\n\t\t\t\t\t\t\t\t\t  <mainTerminalNumber/>\r\n\t\t\t\t\t\t\t\t\t  <queryName>mpiTransaction</queryName>\r\n\t\t\t\t\t\t\t\t\t  <mid>" . $this->creditguard_merchant_id . "</mid>\r\n\t\t\t\t\t\t\t\t\t  <mpiTransactionId>" . $data["txId"] . "</mpiTransactionId>\r\n\t\t\t\t\t\t\t\t\t </inquireTransactions>\r\n\t\t\t\t\t\t\t\t\t</request>\r\n\t\t\t\t\t\t\t\t   </ashrait>";
            return $poststring;
        }
        public function save_token_and_mark_order_as_on_hold($cardToken, $cardExp, $authNumber, $order)
        {
            $this->save_token($order->get_id(), $cardToken, $cardExp, $authNumber);
            $order->add_order_note(__("Creditguard payment verified", "talpress-woocommerce-creditguard"));
            if (!function_exists("wcs_is_subscription") || !wcs_is_subscription($order->get_id())) {
                $order->update_status("on-hold");
            }
        }
        public function complete_order($order)
        {
            $order->add_order_note(__("Creditguard payment completed", "talpress-woocommerce-creditguard"));
            $order->payment_complete();
        }
        public function add_more_order_metadata($orderId, $response_xml)
        {
            update_post_meta($orderId, "creditguard_tranId", (int) $response_xml->tranId);
            update_post_meta($orderId, "creditguard_cardId", (int) $response_xml->doDeal->cardId);
        }
        public function add_order_metadata($order_id, $cardMask, $numberOfPayments, $firstPayment, $periodicalPayment)
        {
            $this->log("[INFO]: add_order_metadata start");
            update_post_meta($order_id, "_ccnumber", substr($cardMask, -4));
            update_post_meta($order_id, "_payments", $numberOfPayments);
            update_post_meta($order_id, "_payment_gateway", "CreditGuard");
            update_post_meta($order_id, "_first_payment", $firstPayment);
            update_post_meta($order_id, "_periodical_payment", $periodicalPayment);
            $this->log("[INFO]: _ccnumber : " . substr($cardMask, -4));
            $this->log("[INFO]: _payments : " . $numberOfPayments);
            $this->log("[INFO]: _payment_gateway : CreditGuard");
            $this->log("[INFO]: _first_payment : " . $firstPayment);
            $this->log("[INFO]: _periodical_payment : " . $periodicalPayment);
            $this->log("[INFO]: add_order_metadata end");
        }
        public function init_logger()
        {
            if ($this->creditguard_debug && (!isset($this->logger) || empty($this->logger))) {
                $this->logger = new WC_Logger();
            }
        }
        public function build_notify_url()
        {
            if ($this->creditguard_is_ssl) {
                $this->notify_url = str_replace("http:", "https:", add_query_arg("wc-api", "WC_Gateway_Creditguard", home_url("/")));
            } else {
                $this->notify_url = str_replace("https:", "http:", add_query_arg("wc-api", "WC_Gateway_Creditguard", home_url("/")));
            }
            $this->notify_url = str_replace("&", "%26amp;", $this->notify_url);
        }
        public function define_supported_functionality()
        {
            $this->supports = ["products"];
            $this->supports[] = "refunds";
            $this->supports[] = "subscriptions";
            $this->supports[] = "subscription_cancellation";
            $this->supports[] = "multiple_subscriptions";
            $this->supports[] = "subscription_suspension";
            $this->supports[] = "subscription_reactivation";
            $this->supports[] = "subscription_amount_changes";
            $this->supports[] = "subscription_date_changes";
            $this->supports[] = "subscription_payment_method_change_customer";
            $this->supports[] = "subscription_payment_method_change_admin";
            $this->supports[] = "subscription_payment_method_change";
        }
        public function init_fields()
        {
        }
        public function save_token($order_id, $cardToken, $cardExp, $authNumber)
        {
            update_post_meta($order_id, "_creditguard_token", $cardToken);
            update_post_meta($order_id, "_creditguard_expiration", $cardExp);
            update_post_meta($order_id, "_creditguard_authorization", $authNumber);
            if (function_exists("wcs_is_subscription")) {
                $this->update_subscription_parent_order($order_id);
            }
        }
        public function build_items_xml($order)
        {
            $items = $order->get_items();
            $this->log("[INFO]: Items: " . print_r($items, true));
            $invoiceItemCode = [];
            $invoiceItemDescription = [];
            $invoiceItemQuantity = [];
            $invoiceItemPrice = [];
            $woocommerce_price_num_decimals = get_option("woocommerce_price_num_decimals");
            $exclude_vat = $this->creditguard_invoice_vat ? false : true;
            foreach ($items as $item) {
                $item_name = $item->get_name();
                $item_name = str_replace("\"", "", $item_name);
                $item_name = str_replace("'", "", $item_name);
                $item_name = str_replace("&lrr", "", $item_name);
                $item_name .= $this->get_attributes_data($item, $order);
                $item_name = preg_replace("/[^a-zA-Z0-9א-ת ]+/", " ", $item_name);
                $item_name = apply_filters("tp_creditguard_gateway_item_name", $item_name);
                $item_price = round($item->get_subtotal(), $woocommerce_price_num_decimals);
                $item_price += round($exclude_vat ? 0 : $item->get_subtotal_tax(), $woocommerce_price_num_decimals);
                $item_price = $item_price / $item->get_quantity();
                $item_price = round($item_price, $woocommerce_price_num_decimals) * 100;
                $invoiceItemCode[] = $item->get_product_id();
                $invoiceItemDescription[] = $this->prepare_str_for_xml($item_name);
                $invoiceItemQuantity[] = $item->get_quantity();
                $invoiceItemPrice[] = $item_price;
            }
            $shipping_tax = $exclude_vat ? 0 : $order->get_shipping_tax();
            $shipping = round($order->get_total_shipping(), $woocommerce_price_num_decimals) + round($shipping_tax, $woocommerce_price_num_decimals);
            $shipping = $shipping * 100;
            if (0 < $shipping) {
                $invoiceItemCode[] = 999999;
                $invoiceItemDescription[] = $order->get_shipping_method();
                $invoiceItemQuantity[] = 1;
                $invoiceItemPrice[] = $shipping;
            }
            $discount = round($order->get_total_discount($exclude_vat), $woocommerce_price_num_decimals) * 100;
            if (0 < $discount) {
                $invoiceItemCode[] = 999998;
                $invoiceItemDescription[] = __("Discount", "talpress-woocommerce-creditguard");
                $invoiceItemQuantity[] = 1;
                $invoiceItemPrice[] = -1 * $discount;
            }
            $wc_order = $order;
            if ($wc_order->get_items("fee")) {
                $i = 0;
                foreach ($wc_order->get_items("fee") as $item_id => $item_fee) {
                    $invoiceItemCode[] = 999997 - $i;
                    $invoiceItemDescription[] = $item_fee->get_name();
                    $invoiceItemQuantity[] = 1;
                    $invoiceItemPrice[] = round($item_fee->get_total() / 0, 2) * 100;
                    $i++;
                }
            }
            $invoice_vendor_user = "";
            $invoice_vendor_user = apply_filters("tp_creditguard_gateway_vendor_user", $invoice_vendor_user, $order);
            $invoice_vendor_key = apply_filters("tp_creditguard_gateway_invoice_vendor_key", "", $order);
            $invoice_esek_number = apply_filters("tp_creditguard_gateway_esek_number", "", $order);
            $invoice_client_number = apply_filters("tp_creditguard_gateway_invoice_client_number", "", $order);
            $this->log(sprintf("[INFO]: after invoice-vendor hooks: invoiceVendorUser: %s, invoiceVendorKey: %s, invoiceEsekNumber: %s, invoiceClientNumber: %s", $invoice_vendor_user, $invoice_vendor_key, $invoice_esek_number, $invoice_client_number));
            $items_xml = "";
            if ($invoice_vendor_user != "") {
                $items_xml = "\r\n<invoiceVendorUser>" . $invoice_vendor_user . "</invoiceVendorUser>\r\n<invoiceVendorKey>" . $invoice_vendor_key . "</invoiceVendorKey>\r\n<invoiceEsekNumber>" . $invoice_esek_number . "</invoiceEsekNumber>\r\n<invoiceClientNumber>" . $invoice_client_number . "</invoiceClientNumber>";
            }
            $invoice_codes = implode("|", $invoiceItemCode);
            $invoice_codes = apply_filters("tp_creditguard_gateway_invoice_codes", $invoice_codes, $order);
            $this->log(sprintf("[INFO]: after invoice-codes hook: invoiceItemCode: %s", $invoice_codes));
            $invoiceItemPrice = apply_filters("tp_creditguard_gateway_item_price_array", $invoiceItemPrice, $order->get_id());
            $items_xml .= "\r\n<invoiceItemCode>" . $invoice_codes . "</invoiceItemCode>\r\n<invoiceItemDescription>" . remove_accents(implode("|", $invoiceItemDescription)) . "</invoiceItemDescription>\r\n<invoiceItemQuantity>" . implode("|", $invoiceItemQuantity) . "</invoiceItemQuantity>\r\n<invoiceItemPrice>" . implode("|", $invoiceItemPrice) . "</invoiceItemPrice>";
            $this->log("[INFO]: items xml: " . print_r($items_xml, true));
            return $items_xml;
        }
        private function validate_order($data, $order)
        {
            $language = $this->get_language();
            $post_string = $this->build_inquireTransactions_string($language, $data);
            $this->log("[INFO]: Validation : ");
            $response = "";
            $request_err = $this->sendRequest($this->creditguard_gateway_url, $post_string, $response);
            if (is_wp_error($request_err)) {
                $this->display_error_and_die(["ErrorCode" => $request_err->get_error_code(), "ErrorText" => $request_err->get_error_message()]);
            }
            $this->log("[INFO]: inquireTransactions request returned with no curl error");
            $upper_response = strtoupper($response);
            if (strpos($upper_response, "HEB")) {
                if (!function_exists("iconv")) {
                    $this->log("[INFO]: iconv() is missing");
                    $this->display_error_and_die(["ErrorCode" => "no-code", "ErrorText" => "iconv() is missing"]);
                }
                $response = iconv("utf-8", "iso-8859-8", $response);
            }
            if (!function_exists("simplexml_load_string")) {
                $this->log("[INFO]: simplexml_load_string() is missing");
                $this->display_error_and_die(["ErrorCode" => "no-code", "ErrorText" => "simplexml_load_string() is missing"]);
            }
            $xmlObj = simplexml_load_string($response);
            $this->log("[INFO]: inquireTransactions XML obj: " . print_r($xmlObj, true));
            if (!isset($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->result)) {
                $this->display_error_and_die(["ErrorCode" => "no-code", "ErrorText" => "CreditGuard result object not set"]);
            } else {
                $this->log("[INFO]: Payment success, result: " . $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->result);
                $authNumber = $data["authNumber"];
                $cardToken = $data["cardToken"];
                $cardExp = $data["cardExp"];
                $cardMask = $data["cardMask"];
                $numberOfPayments = $data["numberOfPayments"] == "" ? 1 : $data["numberOfPayments"] + 1;
                $firstPayment = $data["firstPayment"] == "" ? $order->get_total() : $data["firstPayment"];
                $periodicalPayment = $data["periodicalPayment"];
                $invoice_xml = $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->invoice;
                $this->get_invoice_data($order->get_id(), $invoice_xml);
                $this->update_user_token($order->get_user_id(), $cardToken, $cardExp, $cardMask, $authNumber);
                $this->add_order_metadata($order->get_id(), $cardMask, $numberOfPayments, $firstPayment, $periodicalPayment);
                if ($this->creditguard_payment_type == "Verify") {
                    $this->save_token_and_mark_order_as_on_hold($cardToken, $cardExp, $authNumber, $order);
                } else {
                    $this->save_token($order->get_id(), $cardToken, $cardExp, $authNumber);
                    $this->complete_order($order);
                }
            }
        }
        public function get_invoice_data($order_id, $invoice_xml)
        {
            if ($this->creditguard_invoice) {
                $this->log("[INFO]: Getting invoice information : ");
                $invoice_number = $invoice_xml->invoiceDocNumber;
                $this->log("[INFO]: invoice_xml : " . print_r($invoice_xml, true));
                $this->log("[INFO]: invoice_number : " . $invoice_number);
                $invoice_url = $invoice_xml->invoiceDocUrl;
                $this->log("[INFO]: invoice_url : " . $invoice_url);
                $mailTo = $invoice_xml->mailTo;
                $this->log("[INFO]: mailTo : " . $mailTo);
                $invoice_response_code = $invoice_xml->invoiceResponseCode;
                $this->log("[INFO]: invoice_response_code : " . $invoice_response_code);
                $invoice_response_name = $invoice_xml->invoiceResponseName;
                $this->log("[INFO]: invoice_response_name : " . $invoice_response_name);
                update_post_meta($order_id, "_invoice_number", (int) $invoice_number);
                update_post_meta($order_id, "_invoice_url", (int) $invoice_url);
                update_post_meta($order_id, "_mail_to", (int) $mailTo);
                update_post_meta($order_id, "_invoice_response_code", (int) $invoice_response_code);
                update_post_meta($order_id, "_invoice_response_name", (int) $invoice_response_name);
            }
        }
        private function make_log_string($post_string)
        {
            $username = $this->creditguard_username;
            $password = $this->creditguard_password;
            $username = str_repeat("*", strlen($username) - 2) . substr($username, strlen($username) - 2, 2);
            $password = str_repeat("*", strlen($password) - 2) . substr($password, strlen($password) - 2, 2);
            $log_data = str_replace($this->creditguard_username, $username, $post_string);
            $log_data = str_replace($this->creditguard_password, $password, $log_data);
            return $log_data;
        }
    }
}
function add_creditguard_gateway($methods)
{
    $methods[] = "WC_Gateway_Creditguard";
    return $methods;
}
function creditguard_view_log_callback()
{
    $logs_base_dir = "/wp-content/uploads/wc-logs/";
    if (is_multisite()) {
        $logs_base_dir = "/wp-content/uploads/sites/" . get_current_blog_id() . "/wc-logs/";
    }
    // $logs = glob(WP_CONTENT_DIR . "/uploads/wc-logs/talpress-woocommerce-creditguard*.log");
    $logs = glob(get_home_path() . $logs_base_dir . "talpress-woocommerce-creditguard*.log");
    $data = "";
    if (!empty($logs)) {
        $handle = fopen($logs[0],'r') or die ('File opening failed');
        $requestsCount = 0;
        $num404 = 0;

        while (!feof($handle)) {
            $dd = fgets($handle);
            $requestsCount++;   
            echo $dd;
        }
        fclose($handle);
    } else {
        exit("Unable to open log file!");
    }
}
function creditguard_delete_log_callback()
{
    $logs_base_dir = "/wp-content/uploads/wc-logs/";
    if (is_multisite()) {
        $logs_base_dir = "/wp-content/uploads/sites/" . get_current_blog_id() . "/wc-logs/";
    }
    $logs = glob(get_home_path() . $logs_base_dir . "talpress-woocommerce-creditguard*.log");
    if (!empty($logs)) {
        unlink($logs[0]);
    }
}

?>