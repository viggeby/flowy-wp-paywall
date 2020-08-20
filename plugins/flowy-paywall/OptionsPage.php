<?php namespace Flowy;

class OptionsPage {


    function setup(){
        add_action( 'admin_menu', [$this, 'addOptionsPage'] );
        add_action( 'admin_init', [$this, 'registerSettings'] );
    }

    function addOptionsPage(){

        add_options_page( 'Flowy Paywall' , 'Flowy Paywall', 'manage_options', 'flowy_paywall', [$this, 'optionsPage']);

    }

    function registerSettings(){
        add_option( 'flowy_paywall_api_url', '');
        add_option( 'flowy_paywall_login_url', '');
        add_option( 'flowy_paywall_client_id', '');
        add_option( 'flowy_paywall_client_secret', '');
        add_option( 'flowy_paywall_api_product', '');
        add_option( 'flowy_paywall_api_category_type', '');
        add_option( 'flowy_paywall_buy_url', '');
        add_option( 'flowy_paywall_fetch_user_products', '');

        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_api_url', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_login_url', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_client_id', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_client_secret', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_api_product', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_api_category_type', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_buy_url', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_fetch_user_products', 'flowy_paywall_callback' );
    }

    function optionsPage()
    {

        do_settings_sections( 'my-page' );
    ?>
    <div class="wrap">

        <h1>Flowy Paywall</h1>
        <form method="post" action="options.php">
        <?php settings_fields( 'flowy_paywall_auth_settings' ); ?>

        <p>
        Please see to <a href="https://doc.mediaconnect.no/doc/ConnectID/#section/Introduction/ConnectID-specifications">documentation with login URLs</a>.
        </p>

        
       
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_url">API Url</label></th>
                <td><input type="text" id="flowy_paywall_api_url" name="flowy_paywall_api_url" value="<?php echo get_option('flowy_paywall_api_url'); ?>">
                <p class="description">Production: https://api.mediaconnect.no/capi/<br> Test: https://api-test.mediaconnect.no/capi/</p>
                </td>               
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_url">Login Url</label></th>
                <td><input type="text" id="flowy_paywall_api_url" name="flowy_paywall_login_url" value="<?php echo get_option('flowy_paywall_login_url'); ?>" />
                <p class="description">Production: https://connectid.no/user/ or https://connectid.se/user/<br>
                                    Test: https://api-test.mediaconnect.no/login/ (Note the /login/ path at the end)</p>
                </td>                
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_client_id">Client ID</label></th>
                <td><input type="text" id="flowy_paywall_client_id" name="flowy_paywall_client_id" value="<?php echo get_option('flowy_paywall_client_id'); ?>" />
                <p class="description">The Client ID is provided by Flowy.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_client_secret">Client Secret</label></th>
                <td><input type="text" id="flowy_paywall_client_secret" name="flowy_paywall_client_secret" value="<?php echo get_option('flowy_paywall_client_secret'); ?>" />
                <p class="description">The Client Secret is provided by Flowy.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_product">API Product Name</label></th>
                <td><input type="text" id="flowy_paywall_api_product" name="flowy_paywall_api_product" value="<?php echo get_option('flowy_paywall_api_product'); ?>" />
                <p class="description">This is the name of the product to send to the <a href="https://doc.mediaconnect.no/doc/ConnectID/#tag/Access/paths/~1v1~1customer~1access/post">Check Access API</a> when verifying a user subscription. You can check access against multiple products be separating them with a comma like "product1,product2,product3". (Tip: Put the most likely product first to improve performance as they will be checkd in sequence.)</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_category_type">API Category Type</label></th>
                <td><input type="text" id="flowy_paywall_api_category_type" name="flowy_paywall_api_category_type" value="<?php echo get_option('flowy_paywall_api_category_type'); ?>" />
                <p class="description">This is the name of the category tyupe to send to the <a href="https://doc.mediaconnect.no/doc/ConnectID/#tag/Access/paths/~1v1~1customer~1access/post">Check Access API</a> when verifying a user subscription.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_buy_url">Buy Subscription Url</label></th>
                <td><input type="text" id="flowy_paywall_buy_url" placeholder="" name="flowy_paywall_buy_url" value="<?php echo get_option('flowy_paywall_buy_url'); ?>" />
                <p class="description">Url for where to buy a subscription. Can also be used as a Base URL and suffixed with a name of a specific magazine using shortcodes.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_buy_url">Fetch user products</label></th>
                <td><input type="checkbox" id="flowy_paywall_fetch_user_products" placeholder="" name="flowy_paywall_fetch_user_products" <?php echo checked( 1, get_option( 'flowy_paywall_fetch_user_products' ), false )?> value="1" />
                <p class="description">Enable to fetch the users products when authenticating.</p>
                </td>
            </tr>
        </table>
        <?php  submit_button(); ?>
        </form>
    </div>
    <?php
    } 


}