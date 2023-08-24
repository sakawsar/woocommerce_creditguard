<?php


add_action("woocommerce_loaded", "WC_Gateway_creditguard_metabox_init");
class WC_Gateway_creditguard_metabox
{
    public function __construct()
    {
        add_action("add_meta_boxes", [$this, "creditguard_add_meta_boxs"]);
        add_action("wp_ajax_creditguard_token_pay", [$this, "creditguard_token_pay_callback"]);
    }
    public function creditguard_add_meta_boxs()
    {
        $this->add_order_meta_box();
    }
    public function add_order_meta_box()
    {
        if (!isset($_GET["post"])) {
            return NULL;
        }
        $screens = ["shop_order "];
        foreach ($screens as $screen) {
            add_meta_box("creditguard_sectionid", __("Creditguard Payment information for delayed payment", "talpress-woocommerce-creditguard"), [$this, "creditguard_order_meta_box_callback"], $screen);
        }
    }
    public function creditguard_order_meta_box_callback()
    {
        if (!is_admin()) {
            return NULL;
        }
        wp_nonce_field("creditguard_meta_box", "creditguard_meta_box_nonce");
        $this->build_creditguard_pay_javascript_function();
        $this->build_meta_box_form();
    }
    public function creditguard_token_pay_callback()
    {
        $orderId = (int) $_POST["orderId"];
        $order = wc_get_order($orderId);
        $payment_gateway = wc_get_payment_gateway_by_order($orderId);
        $payment_gateway->log("creditguard_token_pay_callback Start");
        $post_string = $this->build_post_string_xml_with_token($payment_gateway, $order);
        $payment_gateway->log("[INFO]: build_post_sring_xml_with_token: " . $post_string);
        $post_string = $this->build_invoice_xml($payment_gateway, $order, $post_string);
        $payment_gateway->log("[INFO]:J5 request: " . $post_string);
        $response = "";
        $request_err = $payment_gateway->sendRequest($payment_gateway->creditguard_gateway_url, $post_string, $response);
        $to_echo = "failure";
        if (is_wp_error($request_err)) {
            $order->add_order_note(__("Creditguard delayed payment failed: ", "talpress-woocommerce-creditguard") . $request_err->get_error_message());
        } else {
            $payment_gateway->log("[INFO]:J5 response: " . $response);
            if (strpos(strtoupper($response), "HEB")) {
                $response = iconv("utf-8", "iso-8859-8", $response);
            }
            $xmlObj = simplexml_load_string($response);
            if ($xmlObj->response->result == "0000") {
                $payment_gateway->add_more_order_metadata($orderId, $xmlObj->response);
                $invoice_xml = $xmlObj->response->doDeal->invoice;
                $payment_gateway->log("[INFO]: invoice-xml: " . print_r($invoice_xml, true));
                $payment_gateway->get_invoice_data($orderId, $invoice_xml);
                $order->add_order_note(__("Creditguard delayed payment completed", "talpress-woocommerce-creditguard"));
                $order->payment_complete();
                $to_echo = "success";
            }
        }
        echo $to_echo;
        exit;
    }
    public function build_post_string_xml_with_token($payment_gateway, $order)
    {
        $payment_gateway->log("build_post_string_xml_with_token Start");
        $total = $order->get_total() * 100;
        $payment_gateway->log("[Info] total :" . $total);
        $creditType = "RegularCredit";
        $max_payments = get_post_meta($order->get_id(), "_payments", true);
        if (1 < $max_payments) {
            $creditType = "Payments";
        } else {
            $max_payments = "";
        }
        $payment_gateway->log("[Info] max_payments :" . $max_payments);
        $payment_gateway->log("[Info] creditType :" . $creditType);
        $language = $payment_gateway->get_language();
        $currency = $payment_gateway->creditguard_currency;
        if ($currency == "auto") {
            $currency = $order->get_currency();
        }
        $payment_gateway->log("[Info] currency :" . $currency);
        $order_token = get_post_meta($_POST["orderId"], "_creditguard_token", true);
        $order_expiration = get_post_meta($_POST["orderId"], "_creditguard_expiration", true);
        $order_authorization = get_post_meta($_POST["orderId"], "_creditguard_authorization", true);
        $post_string = "user=" . $payment_gateway->creditguard_username . "&password=" . $payment_gateway->creditguard_password . "&int_in=<ashrait> \r\n\t\t<request>\r\n\t\t<command>doDeal</command>\r\n\t\t<requestId></requestId>\r\n\t\t<version>2000</version>\r\n\t\t<language>" . $language . "</language>\r\n\t\t<doDeal>\r\n\t\t\t<terminalNumber>" . $payment_gateway->creditguard_term_no . "</terminalNumber>\r\n\t\t\t<cardId>" . $order_token . "</cardId>\r\n\t\t\t<cardExpiration>" . $order_expiration . "</cardExpiration>\r\n\t\t\t<transactionType>Debit</transactionType>\r\n\t\t\t<currency>" . $currency . "</currency>\r\n\t\t\t<transactionCode>Phone</transactionCode>\r\n\t\t\t<authNumber>" . $order_authorization . "</authNumber>\r\n\t\t\t<total>" . $total . "</total>\r\n\t\t\t##payments##\r\n\t\t\t<validation>AutoComm</validation>\r\n\t\t\t##invoice##\r\n\t\t</doDeal>\r\n\t\t</request>\r\n\t</ashrait>";
        $post_string = $this->add_payments_data($payment_gateway, $post_string);
        return $post_string;
    }
    public function build_invoice_xml(WC_Gateway_creditguard $payment_gateway, $order, $postString)
    {
        $payment_gateway->log("WC_Gateway_creditguard_metabox::build_invoice_xml [Info] start: postString:" . $postString);
        if ($payment_gateway->creditguard_invoice) {
            $postString = $payment_gateway->build_invoice_xml($order, $postString);
        } else {
            $postString = str_replace("##invoice##", "", $postString);
        }
        $payment_gateway->log("WC_Gateway_creditguard_metabox::build_invoice_xml [Info] end: postString:" . $postString);
        return $postString;
    }
    public function build_creditguard_pay_javascript_function()
    {
        $orderId = get_the_ID();
        $payment_complete_message = __("Creditguard payment completed", "talpress-woocommerce-creditguard");
        $payment_failed_message = __("Creditguard payment failed", "talpress-woocommerce-creditguard");
        echo "<script>\r\n\t\t\tfunction creditguard_pay() {\r\n\t\t\t\tjQuery('body').css('cursor', 'progress');\r\n\t\t\t\tvar data = {\r\n\t\t\t\t\t'action': 'creditguard_token_pay',\r\n\t\t\t\t\t'orderId':'" . $orderId . "',\r\n\t\t\t\t\t\r\n\t\t\t\t};\r\n\t\t\t\tjQuery.post(ajaxurl, data, function(response) {\r\n\t\t\t\t\tjQuery('body').css('cursor', 'default');\r\n\t\t\t\t\tif (response=='success'){\r\n\t\t\t\t\t\talert ('" . $payment_complete_message . "');\r\n\t\t\t\t\t\tlocation.reload();\r\n\t\t\t\t\t}else{\r\n\t\t\t\t\t\talert ('" . $payment_failed_message . "');\r\n\t\t\t\t\t}\r\n\t\t\t\t});\r\n\t\t\t\r\n\t\t}</script>";
    }
    public function build_meta_box_form()
    {
        $post_id = $_GET["post"];
        $order_token = get_post_meta($post_id, "_creditguard_token", true);
        $order_expiration = get_post_meta($post_id, "_creditguard_expiration", true);
        $order_authorization = get_post_meta($post_id, "_creditguard_authorization", true);
        $order_payments = get_post_meta($post_id, "_payments", true);
        $order_first_payment = get_post_meta($post_id, "_first_payment", true);
        $order_periodical_payment = get_post_meta($post_id, "_periodical_payment", true);
        $invoice_number = get_post_meta($post_id, "_invoice_number", true);
        $invoice_url = get_post_meta($post_id, "_invoice_url", true);
        $mail_to = get_post_meta($post_id, "_mail_to", true);
        $invoice_response_code = get_post_meta($post_id, "_invoice_response_code", true);
        $invoice_response_name = get_post_meta($post_id, "_invoice_response_name", true);
        $readonly = "disabled=\"disabled\"";
        echo "<div class=\"cg-payment\"><label for=\"creditguard_token\" class=\"cg-payment-lbl\">";
        _e("Token", "talpress-woocommerce-creditguard");
        echo "</label> ";
        echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . " id=\"creditguard_token\" name=\"creditguard_token\" value=\"" . esc_attr($order_token) . "\" size=\"25\" />";
        echo "</div><div class=\"cg-payment\"><label for=\"creditguard_expiration\" class=\"cg-payment-lbl\">";
        _e("Expiration", "talpress-woocommerce-creditguard");
        echo "</label> ";
        echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . " id=\"creditguard_expiration\" name=\"creditguard_expiration\" value=\"" . esc_attr($order_expiration) . "\" size=\"25\" />";
        echo "</div><div class=\"cg-payment\"><label for=\"creditguard_authorization\" class=\"cg-payment-lbl\">";
        _e("Authorization", "talpress-woocommerce-creditguard");
        echo "</label> ";
        echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"creditguard_authorization\" name=\"creditguard_authorization\" value=\"" . esc_attr($order_authorization) . "\" size=\"25\" />";
        echo "</div><div class=\"cg-payment\"><label for=\"creditguard_payments\" class=\"cg-payment-lbl\">";
        _e("Payments", "talpress-woocommerce-creditguard");
        echo "</label> ";
        echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"creditguard_payments\" name=\"creditguard_payments\" value=\"" . esc_attr($order_payments) . "\" size=\"25\" />";
        echo "</div><div class=\"cg-payment\"><label for=\"order_first_payment\" class=\"cg-payment-lbl\">";
        _e("First payment", "talpress-woocommerce-creditguard");
        echo "</label> ";
        echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"order_first_payment\" name=\"order_first_payment\" value=\"" . esc_attr($order_first_payment) . "\" size=\"25\" />";
        echo "</div><div class=\"cg-payment\"><label for=\"order_periodical_payment\" class=\"cg-payment-lbl\">";
        _e("Periodical payment", "talpress-woocommerce-creditguard");
        echo "</label> ";
        echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"order_periodical_payment\" name=\"order_periodical_payment\" value=\"" . esc_attr($order_periodical_payment) . "\" size=\"25\" />";
        echo "</div>";
        if ($invoice_number !== "") {
            echo "<div class=\"cg-payment\"><label for=\"invoice_number\" class=\"cg-payment-lbl\">";
            _e("Invoice number", "talpress-woocommerce-creditguard");
            echo "</label> ";
            echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"invoice_number\" name=\"invoice_number\" value=\"" . esc_attr($invoice_number) . "\" size=\"25\" />";
            echo "</div><div class=\"cg-payment\"><label for=\"invoice_url\" class=\"cg-payment-lbl\">";
            _e("Invoice url ", "talpress-woocommerce-creditguard");
            echo "</label>";
            echo "<a class=\"cg-payment-val\"  href=\"" . $invoice_url . "\" size=\"25\">" . $invoice_url . "</a>";
            echo "</div><div class=\"cg-payment\"><label for=\"mail_to\" class=\"cg-payment-lbl\">";
            _e("Mailed to ", "talpress-woocommerce-creditguard");
            echo "</label>";
            echo "<input class=\"cg-payment-val\" class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"mail_to\" name=\"mail_to\" value=\"" . $mail_to . "\" size=\"25\" />";
            echo "</div>";
        } else {
            $gateway = wc_get_payment_gateway_by_order($_GET["post"]);
            if ($gateway && $gateway->get_option("creditguard_invoice") == "yes") {
                echo "<div class=\"cg-payment\"><label for=\"invoice_response_code\" class=\"cg-payment-lbl\">";
                _e("Invoice response code", "talpress-woocommerce-creditguard");
                echo "</label> ";
                echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"invoice_response_code\" name=\"invoice_response_code\" value=\"" . $invoice_response_code . "\" size=\"25\" />";
                echo "</div><div class=\"cg-payment\"><label for=\"invoice_response_name\" class=\"cg-payment-lbl\">";
                _e("Invoice response name", "talpress-woocommerce-creditguard");
                echo "</label> ";
                echo "<input class=\"cg-payment-val\" type=\"text\" " . $readonly . "id=\"invoice_response_name\" name=\"invoice_response_name\" value=\"" . esc_attr($invoice_response_name) . "\" size=\"100\" />";
                echo "</div>";
            }
        }
        $orderId = (int) $_GET["post"];
        $order = wc_get_order($orderId);
        if ($order->get_status() != "on-hold") {
            return NULL;
        }
        echo "<div class=\"cg-payment\"><button type=\"button\" class=\"button\" onclick=\"creditguard_pay();\">שלם</button></div><style>.cg-payment .cg-payment-lbl { width: 10%; display: inline-block;    padding: 5px 0;}</style>";
    }
    public function add_payments_data(WC_Gateway_creditguard $payment_gateway, $post_string)
    {
        $order_payments = get_post_meta($_POST["orderId"], "_payments", true);
        $first_payment = get_post_meta($_POST["orderId"], "_first_payment", true);
        $periodical_payment = get_post_meta($_POST["orderId"], "_periodical_payment", true);
        $payment_gateway->log("[Info] \$order_payments :" . $order_payments);
        $payment_gateway->log("[Info] first_payment :" . $first_payment);
        $payment_gateway->log("[Info] periodical_payment :" . $periodical_payment);
        if (1 < $order_payments) {
            $payments = "<numberOfPayments>" . (int) ($order_payments - 1) . "</numberOfPayments>\r\n\t\t\t<periodicalPayment>" . trim($periodical_payment) . "</periodicalPayment>\r\n\t\t\t<firstPayment>" . trim($first_payment) . "</firstPayment>\r\n\t\t\t<creditType>Payments</creditType>";
        } else {
            $payments = "<creditType>RegularCredit</creditType>";
        }
        return str_replace("##payments##", $payments, $post_string);
    }
}
function WC_Gateway_creditguard_metabox_init()
{
    new WC_Gateway_creditguard_metabox();
}

?>