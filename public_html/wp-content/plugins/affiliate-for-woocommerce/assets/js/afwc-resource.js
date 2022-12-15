jQuery(function(){
    const { _x } = wp.i18n;
    jQuery('#afwc_resources_wrapper').on('change, keyup', '#afwc_affiliate_link', function(){
        let start = afwcResourceParams.homeurl;
        let path = jQuery(this).val();
        let affiliate_id = jQuery('#afwc_id_change_wrap code').text();
        let affiliate_link = '';
        if ( -1 !== path.indexOf( '?' ) ) {
            affiliate_link = start + path + '&'+ afwcResourceParams.pname + affiliate_id ;
        } else {
            affiliate_link = start + path + '?'+ afwcResourceParams.pname + '=' + affiliate_id 
        }
        jQuery('#afwc_generated_affiliate_link').text( affiliate_link );
    });
    jQuery('#afwc_account_form').on('submit', function(e){
        e.preventDefault();
        if( ! afwcResourceParams.saveAccountDetailsURL ){
            return;
        }
        let formData      = jQuery(this).serialize();
        let statusElement = jQuery(this).find('.afwc_save_account_status');
        statusElement.removeClass('afwc_status_yes afwc_status_no').addClass('afwc_status_spinner');
        jQuery.ajax({
            url: afwcResourceParams.saveAccountDetailsURL,
            type: 'post',
            dataType: 'json',
            data: {
                security: afwcResourceParams.saveAccountSecurity || '',
                form_data: decodeURIComponent( formData )
            },
            success: function( response ) {
                if ( response.success ) {
                    if ( 'yes' === response.success ) {
                        statusElement.removeClass('afwc_status_spinner').addClass('afwc_status_yes');
                    } else if ( 'no' === response.success ) {
                        statusElement.removeClass('afwc_status_spinner').addClass('afwc_status_no');
                        if ( response.message ) {
                            alert( response.message );
                        }
                    }
                }
            }
        });
    });
    jQuery('#afwc_resources_wrapper').on( 'click', '#afwc_change_identifier', function( e ) {
        e.preventDefault();
        jQuery('#afwc_id_change_wrap, #afwc_id_save_wrap').toggle();
    });
    jQuery('#afwc_resources_wrapper').on( 'click', '#afwc_save_identifier', function( e ) {
        e.preventDefault();
        jQuery( '#afwc_id_msg' ).hide()
        let referralIdentifier = jQuery('#afwc_ref_url_id').val() || '';
        if ( '' !== referralIdentifier && afwcResourceParams.saveReferralURLIdentifier ) {

            if ( false === new RegExp(afwcResourceParams.identifierRegexPattern || '', 'g').test(referralIdentifier) ) {
                jQuery( '#afwc_id_msg' )
                    .html( _x( 'Only alphabets and numbers are allowed. The number should not be in the first place.', 'referral identifier validation message', 'affiliate-for-woocommerce' ) )
                    .addClass( 'afwc_error' ).show();
                return;
            }

            jQuery('#afwc_save_id_loader').show();
            // Ajax call to save id.
            jQuery.ajax({
                url: afwcResourceParams.saveReferralURLIdentifier,
                type: 'post',
                dataType: 'json',
                data: {
                    security: afwcResourceParams.saveIdentifierSecurity || '',
                    ref_url_id: referralIdentifier
                },
                success: function( response ) {
                    jQuery('#afwc_save_id_loader').hide();
                    if ( response.success ) {
                        if ( 'yes' === response.success ) {
                            jQuery('#afwc_id_change_wrap, #afwc_id_save_wrap').toggle();
                            if( response.message ) {
                                jQuery( '#afwc_id_msg' ).html( response.message ).addClass( 'afwc_success' ).removeClass( 'afwc_error' ).show();
                            }
                            if( jQuery('#afwc_id_change_wrap').length > 0 ){
                                jQuery('#afwc_id_change_wrap').find('code').text(referralIdentifier);
                            }
                            if( jQuery('.afwc_ref_id_span').length > 0 ){
                                jQuery('.afwc_ref_id_span').text(referralIdentifier);
                            }
                            if( jQuery('#afwc_affiliate_link_label').length > 0 && afwcResourceParams.homeurl && afwcResourceParams.pname){
                                jQuery('#afwc_affiliate_link_label').text(`${afwcResourceParams.homeurl}?${afwcResourceParams.pname}=${referralIdentifier}`);
                            }
                        } else if ( 'no' === response.success && response.message ) {
                            jQuery( '#afwc_id_msg' ).html( response.message ).addClass( 'afwc_error' ).removeClass( 'afwc_success' ).show();
                        }
                    }
                    setTimeout( function(){ jQuery( '#afwc_id_msg' ).hide(); }, 10000);
                }
            });
        }


    })
});
function afwc_copy_affiliate_link( obj ) {
    let element = jQuery("<input>");
    jQuery("body").append(element);
    element.val(jQuery(obj).text()).select();
    if( navigator && navigator.clipboard ) {
        navigator.clipboard.writeText(element.val());
    }
    element.remove();
}
