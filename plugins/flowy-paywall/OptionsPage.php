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

        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_api_url', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_login_url', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_client_id', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_client_secret', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_api_product', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_api_category_type', 'flowy_paywall_callback' );
        register_setting( 'flowy_paywall_auth_settings', 'flowy_paywall_buy_url', 'flowy_paywall_callback' );
    }

    function optionsPage()
    {
    ?>
    <div class="wrap">

        <h1>Flowy Paywall</h1>
        <form method="post" action="options.php">
        <?php settings_fields( 'flowy_paywall_auth_settings' ); ?>
       
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_url">API Url</label></th>
                <td><input type="text" id="flowy_paywall_api_url" name="flowy_paywall_api_url" value="<?php echo get_option('flowy_paywall_api_url'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_url">Login Url</label></th>
                <td><input type="text" id="flowy_paywall_api_url" name="flowy_paywall_login_url" value="<?php echo get_option('flowy_paywall_login_url'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_client_id">Client ID</label></th>
                <td><input type="text" id="flowy_paywall_client_id" name="flowy_paywall_client_id" value="<?php echo get_option('flowy_paywall_client_id'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_client_secret">Client Secret</label></th>
                <td><input type="text" id="flowy_paywall_client_secret" name="flowy_paywall_client_secret" value="<?php echo get_option('flowy_paywall_client_secret'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_product">API Product Name</label></th>
                <td><input type="text" id="flowy_paywall_api_product" name="flowy_paywall_api_product" value="<?php echo get_option('flowy_paywall_api_product'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_api_category_type">API Category Type</label></th>
                <td><input type="text" id="flowy_paywall_api_category_type" name="flowy_paywall_api_category_type" value="<?php echo get_option('flowy_paywall_api_category_type'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="flowy_paywall_buy_url">Buy Subscription Url</label></th>
                <td><input type="text" id="flowy_paywall_buy_url" placeholder="" name="flowy_paywall_buy_url" value="<?php echo get_option('flowy_paywall_buy_url'); ?>" /></td>
            </tr>
        </table>
        <?php  submit_button(); ?>
        </form>
    </div>
    <?php
    } 


}