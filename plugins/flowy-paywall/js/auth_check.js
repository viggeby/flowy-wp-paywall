(function($){

    function FlowyPaywall(){
        this.init();
    }

    $.extend(FlowyPaywall.prototype, {

        init: function(){

            // Only check if user is unauthenticated
            if ( flowy_paywall.login_status_is_unknown == 'true') {

                //  Check if a result is present or if we need to wait for result
                if (window.flowy_paywall_ajax_auth_result != undefined){
                    this.doLoginRedirectCheck(window.flowy_paywall_ajax_auth_result);
                }else{
                    $(window).on('window.flowy_paywall_ajax_auth_result', this.doLoginRedirectCheck);
                }
            }

            

        },

        doLoginRedirectCheck: function(authResult){

            // Set login hint to avoid multiple calls to third party
            $.ajax('?flowy_paywall_notify_login_status=' + authResult);

            // Redirect if user is logged in with 3rd party
            if (authResult) {
                window.location.replace(flowy_paywall.login_url);
            }
          
        }

    });

    window.flowy_paywall = new FlowyPaywall();

})(jQuery);