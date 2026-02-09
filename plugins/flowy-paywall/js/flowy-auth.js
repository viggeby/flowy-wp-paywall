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

        // Obsolete loginCheck (connectid.no/loginCheck) no longer used. Session is re-validated server-side
        // via API (checkAccess) with refresh_token on 401; user must click login again if session is invalid.
        // No redirect to get_try_authorize_url.
    }

}


window.addEventListener('load', (e) => {

    new FlowyAuth().checkSSO();

}, {once: true});