/**

 * Created by Nurit on 04/03/2018.

 */

jQuery(document).ready(function(){

//jQuery('button.refund-items').ready(function(){

    tp_add_creditguard_refund_btns();

    jQuery( '#woocommerce-order-items' ).on('click','button.cg-refund-manager-key',tp_do_refund);



});



function tp_add_creditguard_refund_btns(){

    var btn = '<button id="cg_refund_manager_key" class="button button-primary cg-refund-manager-key">Creditguard Refund</button>';

    var inpt = '<label for="cg-refund-manager-key-val">Creditguard Refund key: </label><input type="text" class="cg-refund-manager-key-val" name="cg-refund-manager-key-val" id="cg-refund-manager-key-val" value="">';

    var ra = jQuery('.refund-actions');

    ra.prepend(btn);

    ra.prepend(inpt);

    jQuery('.do-api-refund').prop('disabled', true);

//    jQuery('.do-api-refund').hide();

//    jQuery('#refund-manager-key-val').val('');

}



function tp_do_refund(){

    var rmkv = jQuery('#cg-refund-manager-key-val');

    var key_val = rmkv.val();

//    rmkv.val('');

    var rfnd_amount = jQuery('#refund_amount').val();

    if ((key_val != '') && (rfnd_amount != '')) {

        var data = {

            'action': 'tp_cg_save_refund_manager_key',

            'manager_key': key_val

        };



        jQuery.post(woocommerce_admin_meta_boxes.ajax_url, data, function (data,response) {

            console.log(response);

            if (response == "success") {

                var api_refund_btn = jQuery('.do-api-refund');

                api_refund_btn.prop('disabled', false);

                api_refund_btn.click();

                location.reload(true);

            } else {

                alert('Wrong refund key');

            }

        });

    } else {

        var amount_msg = (rfnd_amount == '') ? 'refund amount' : '';

        var key_msg = (key_val == '') ? 'Creditguard refund key' : '';

        var and_msg = '';

        if ((key_msg + amount_msg) != '') {

            and_msg = ' and ';

        }

        if (key_msg != '') {

            key_msg = '"' + key_msg + '"';

        }

        if (amount_msg != '') {

            amount_msg = '"' + amount_msg + '"';

        }



        var msg = 'Missing: ' + key_msg + and_msg + amount_msg;

        alert(msg);

    }

}