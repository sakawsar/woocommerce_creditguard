<?php

add_action("show_user_profile", "woocommerce_talpress_creditguard_extra_user_profile_fields");
add_action("edit_user_profile", "woocommerce_talpress_creditguard_extra_user_profile_fields");
add_action("personal_options_update", "woocommerce_talpress_creditguard_save_extra_user_profile_fields");
add_action("edit_user_profile_update", "woocommerce_talpress_creditguard_save_extra_user_profile_fields");
function woocommerce_talpress_creditguard_extra_user_profile_fields($user)
{
    echo "  <h3>";
    _e("Creditguard profile information", "talpress-woocommerce-creditguard");
    echo "</h3>\r\n  <table class=\"form-table\">\r\n    <tr>\r\n      <th><label for=\"creditguard-token\">";
    _e("Token", "talpress-woocommerce-creditguard");
    echo "</label></th>\r\n      <td>\r\n        <input type=\"text\" name=\"creditguard-token\" id=\"creditguard-token\" class=\"regular-text\" \r\n            value=\"";
    echo esc_attr(get_the_author_meta("creditguard-token", $user->ID));
    echo "\" /><br />\r\n        <span class=\"description\">";
    _e("Creditguard Token for future payments", "talpress-woocommerce-creditguard");
    echo "</span>\r\n    </td>\r\n    </tr>\r\n\t<tr>\r\n      <th><label for=\"creditguard-expiration\">";
    _e("Expiration Date", "talpress-woocommerce-creditguard");
    echo "</label></th>\r\n      <td>\r\n        <input type=\"text\" name=\"creditguard-expiration\" id=\"creditguard-expiration\" class=\"regular-text\" \r\n            value=\"";
    echo esc_attr(get_the_author_meta("creditguard-expiration", $user->ID));
    echo "\" /><br />\r\n    </td>\r\n    </tr> \r\n\t<tr>\r\n      <th><label for=\"creditguard-number\">";
    _e("CC Number", "talpress-woocommerce-creditguard");
    echo "</label></th>\r\n      <td>\r\n        <input type=\"text\" name=\"creditguard-number\" id=\"creditguard-number\" class=\"regular-text\" \r\n            value=\"";
    echo esc_attr(get_the_author_meta("creditguard-number", $user->ID));
    echo "\" /><br />\r\n    </td>\r\n    </tr> \r\n\t<tr>\r\n      <th><label for=\"creditguard-authorization\">";
    _e("Authorization", "talpress-woocommerce-creditguard");
    echo "</label></th>\r\n      <td>\r\n        <input type=\"text\" name=\"creditguard-authorization\" id=\"creditguard-authorization\" class=\"regular-text\" \r\n            value=\"";
    echo esc_attr(get_the_author_meta("creditguard-authorization", $user->ID));
    echo "\" /><br />\r\n\t\t</td>\r\n    </tr> \r\n  </table>\r\n";
}
function woocommerce_talpress_creditguard_save_extra_user_profile_fields($user_id)
{
    $saved = false;
    if (current_user_can("edit_user", $user_id)) {
        update_user_meta($user_id, "creditguard-token", $_POST["creditguard-token"]);
        update_user_meta($user_id, "creditguard-expiration", $_POST["creditguard-expiration"]);
        update_user_meta($user_id, "creditguard-authorization", $_POST["creditguard-authorization"]);
        update_user_meta($user_id, "creditguard-number", $_POST["creditguard-number"]);
        $saved = true;
    }
    return true;
}

?>