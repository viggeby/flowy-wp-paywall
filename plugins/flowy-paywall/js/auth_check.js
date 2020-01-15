(function($){

    function FlowyPaywall(){
        this.init();
    }

    $.extend(FlowyPaywall.prototype, {

        init: function(){

            this.checkLogin();
        },

        checkLogin: function(){
            
            var loginUrl =  flowy_paywall.login_url + '/loginCheck?clientId=' + flowy_paywall.client_id + '&returnUrl=' + encodeURIComponent(flowy_paywall.return_url) + '&errorUrl=' + encodeURIComponent(flowy_paywall.return_url);
            
            $.ajax({
                url: loginUrl,
                context: document.body
            }).done(function(result) {

    console.log(result);

                $( this ).addClass( "done" );
            });
        }

    });

    window.flowy_paywall = new FlowyPaywall();

})(jQuery);