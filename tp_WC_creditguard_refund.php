<?php

add_action("woocommerce_loaded", "tp_WC_creditguard_refund_init");
class tp_WC_creditguard_refund
{
    public function __construct()
    {
        add_action("wp_ajax_nopriv_tp_cg_save_refund_manager_key", [$this, "tp_save_refund_manager_key_callback"]);
        add_action("wp_ajax_tp_cg_save_refund_manager_key", [$this, "tp_save_refund_manager_key_callback"]);
        add_filter("tp_creditguard_process_refund", [$this, "tp_creditguard_premium_process_refund"], 10, 5);
    }
    public function creditguard_add_refund_manager_key()
    {
        wp_enqueue_script("tpcg_refunds_js", plugin_dir_url(__FILE__) . "assets/js/tpcg_refunds.js");
    }
    public function tp_save_refund_manager_key_callback()
    {
        $manager_key = isset($_POST["manager_key"]) ? $_POST["manager_key"] : "no-key";
        WC()->session->set("manager_key", $manager_key);
        echo WC()->session->get("manager_key");
        exit;
    }
    public function tp_creditguard_premium_process_refund($res, WC_Gateway_creditguard $payment_gateway, $order_id, $amount, $reason)
    {
        $manager_key = WC()->session->get("manager_key");
        WC()->session->set("manager_key", "");
        return $this->handle_refund_request($payment_gateway, $order_id, $amount, $manager_key, $reason);
    }
    public function handle_refund_request($payment_gateway, $order_id, $amount, $manager_key, $reason)
    {
        $payment_gateway->log("handle_refund_request Start");
        $order = wc_get_order($order_id);
        $order_token = get_post_meta($_POST["order_id"], "_creditguard_token", true);
        $order_expiration = get_post_meta($_POST["orderId"], "_creditguard_expiration", true);
        $order_authorization = get_post_meta($_POST["orderId"], "_creditguard_authorization", true);
        if ($order_token == "" || $order_expiration == "" || $order_expiration == "") {
            list($order_token, $order_expiration, $order_authorization) = $payment_gateway->get_user_meta_data($order);
        }
        if ($order_token == "") {
            $payment_gateway->log("[INFO]: no token, can't refund");
            return false;
        }
        $post_string = $this->build_refund_xml_req($payment_gateway, $order, $order_token, $order_expiration, $order_authorization, $amount);
        $post_string = $payment_gateway->build_invoice_xml($order, $post_string);
        $payment_gateway->log("[INFO]: refund request: " . $post_string);
        $res = "";
        $response_err = $payment_gateway->sendRequest($payment_gateway->creditguard_gateway_url, $post_string, $res);
        if (is_wp_error($response_err)) {
            $payment_gateway->log("[INFO]: Sending refund request failed: " . $response_err->get_error_message());
            return false;
        }
        if (strpos(strtoupper($res), "HEB")) {
            $res = iconv("utf-8", "iso-8859-8", $res);
        }
        $xmlObj = simplexml_load_string($res);
        if ($xmlObj->response->result == "0000") {
            $payment_gateway->log("[INFO]: refund complete OK");
            add_post_meta($order->get_id(), "refund_tranId", (int) $xmlObj->response->tranId);
            $order->add_order_note(__("Creditguard refund transaction-id: ", "talpress-woocommerce-creditguard") . $xmlObj->response->tranId);
            return true;
        }
        $payment_gateway->log("[INFO]: refund complete Failed");
        return false;
    }
    private function build_refund_xml_req($payment_gateway, $order, $order_token, $order_expiration, $order_authorization, $total)
    {
        $payment_gateway->log(sprintf("[INFO]:token: %s, exp: %s, auth: %s", $order_token, $order_expiration, $order_authorization));
        $total = $total * 100;
        $language = $payment_gateway->get_language();
        $currency = $payment_gateway->creditguard_currency;
        if ($currency == "auto") {
            $currency = $order->get_currency();
        }
        $post_string = "user=" . $payment_gateway->creditguard_username . "&password=" . $payment_gateway->creditguard_password . "&int_in=";
        $post_string .= "<ashrait>\r\n\t\t\t\t\t\t\t<request>\r\n\t\t\t\t\t\t\t\t<command>doDeal</command>\r\n\t\t\t\t\t\t\t\t<requestId></requestId>\r\n\t\t\t\t\t\t\t\t<version>2000</version>\r\n\t\t\t\t\t\t\t\t<language>" . $language . "</language>\r\n\t\t\t\t\t\t\t\t<doDeal>\r\n\t\t\t\t\t\t\t\t\t<terminalNumber>" . $payment_gateway->creditguard_term_no . "</terminalNumber>\r\n\t\t\t\t\t\t\t\t\t<cardId>" . $order_token . "</cardId>\r\n\t\t\t\t\t\t\t\t\t<cardExpiration>" . $order_expiration . "</cardExpiration>\r\n\t\t\t\t\t\t\t\t\t<transactionType>Credit</transactionType>\r\n\t\t\t\t\t\t\t\t\t<currency>" . $currency . "</currency>\r\n\t\t\t\t\t\t\t\t\t<transactionCode>Phone</transactionCode>\r\n\t\t\t\t\t\t\t\t\t<authNumber>" . $order_authorization . "</authNumber>\r\n\t\t\t\t\t\t\t\t\t<total>" . $total . "</total>\r\n\t\t\t\t\t\t\t\t\t<creditType>RegularCredit</creditType>\r\n\t\t\t\t\t\t\t\t\t<validation>AutoComm</validation>\r\n\t\t\t\t\t\t\t\t\t##invoice##\r\n\t\t\t\t\t\t\t\t</doDeal>\r\n\t\t\t\t\t\t\t</request>\r\n\t\t\t\t\t\t</ashrait>";
        return $post_string;
    }
}
function tp_WC_creditguard_refund_init()
{
    new tp_WC_creditguard_refund();
}

?>