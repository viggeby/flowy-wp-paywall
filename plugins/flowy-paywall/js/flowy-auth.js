class FlowyAuth {


    getCookie = (cookiename) => {
        if (typeof(cookiename) == 'string' && cookiename != '') {
            const COOKIES = document.cookie.split(';');
            for (var i = 0; i < COOKIES.length; i++) {

                const cookieVal = COOKIES[i].split('=');

                if (cookieVal[0].trim() === cookiename) {
                    return cookieVal[1];
                }
            }
        }
    
        return null;
    }

    isLoggedIn = () => {
        return this.getCookie('flowy_paywall') !== null;
    }

    getPreviousLoginCookie = () => {
        return  this.getCookie('flowy_paywall_previous_login') === '1';
    }

    getThirdPartyLoginStatus = () => {
        return this.getCookie('flowy_paywall_third_party_login') === '1';
    }


    checkSSO = () => {

        // Redirect if not logged in, we have been logged in before and we haven't checked status with IDP recently
        if ( this.isLoggedIn() === false  && this.getPreviousLoginCookie() === true && this.getThirdPartyLoginStatus() === false ){
            window.location = flowy_auth.get_try_authorize_url;
        }

    }

}


window.addEventListener('load', (e) => {

    new FlowyAuth().checkSSO();

}, {once: true});