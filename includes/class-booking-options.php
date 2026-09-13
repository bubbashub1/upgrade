<?php
if (!defined('ABSPATH')) exit;

/**
 * Booking option layer for BubbaHub bookings.
 * Adds a third external-booking route without replacing the existing booking engine.
 */
final class BubbaHubBookingOptions {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 26);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('rest_api_init', [__CLASS__, 'register_rest']);
        add_action('wp_footer', [__CLASS__, 'footer_script'], 30);
    }

    public static function defaults() {
        return ['external_url_field' => 'external_booking_url'];
    }

    public static function settings() {
        return wp_parse_args((array)get_option('bubbahub_booking_options', []), self::defaults());
    }

    public static function admin_menu() {
        add_submenu_page(
            'options-general.php',
            'BubbaHub Booking Options',
            'Booking Options',
            'manage_options',
            'bubbahub-booking-options',
            [__CLASS__, 'settings_page']
        );
    }

    public static function register_settings() {
        register_setting('bubbahub_booking_options', 'bubbahub_booking_options', [__CLASS__, 'sanitize']);
    }

    public static function sanitize($input) {
        return ['external_url_field' => sanitize_key($input['external_url_field'] ?? self::defaults()['external_url_field'])];
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::settings();
        ?>
        <div class="wrap">
            <h1>BubbaHub Booking Options</h1>
            <p>Choose the ACF field used on each venue for an external booking link. If the field contains a valid URL, customers will see a third option alongside Book Now and Reserve Spot.</p>
            <form method="post" action="options.php">
                <?php settings_fields('bubbahub_booking_options'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bh-external-url-field">External booking URL ACF field</label></th>
                        <td>
                            <input id="bh-external-url-field" class="regular-text" name="bubbahub_booking_options[external_url_field]" value="<?php echo esc_attr($s['external_url_field']); ?>">
                            <p class="description">Default: <code>external_booking_url</code>. Add this field to your Venue/Group ACF field group as a URL field.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save booking options'); ?>
            </form>
        </div>
        <?php
    }

    public static function external_url($venue_id) {
        $field = self::settings()['external_url_field'];
        $url = '';
        if (function_exists('get_field')) $url = get_field($field, $venue_id);
        if (!$url) $url = get_post_meta($venue_id, $field, true);
        return esc_url_raw(is_string($url) ? $url : '');
    }

    public static function register_rest() {
        register_rest_route('bubbahub/v1', '/booking/venue/(?P<id>\d+)/options', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function($request) {
                $id = absint($request['id']);
                if (!$id || !post_type_exists('post') && !get_post($id)) {
                    return new WP_Error('invalid_venue', 'Venue not found', ['status' => 404]);
                }
                return ['external_url' => self::external_url($id)];
            },
        ]);
    }

    public static function footer_script() {
        if (!shortcode_exists('bubbahub_booking')) return;
        $endpoint = esc_url_raw(rest_url('bubbahub/v1/booking/venue/'));
        ?>
        <script>
        (function(){
            if(window.BubbaHubBookingOptionsLoaded)return;
            window.BubbaHubBookingOptionsLoaded=true;
            function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
            function setup(w){
                var venue=w.querySelector('.bh-booking-venue');
                if(!venue)return;
                venue.addEventListener('change',function(){
                    var id=parseInt(venue.value||'0',10);
                    var old=w.querySelector('.bh-external-booking');
                    if(old)old.remove();
                    if(!id)return;
                    fetch('<?php echo $endpoint; ?>'+id+'/options',{credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        if(!data || !data.external_url)return;
                        var mode=w.querySelector('.bh-booking-mode');
                        if(!mode)return;
                        var a=document.createElement('a');
                        a.className='bh-mode bh-external-booking';
                        a.href=data.external_url;
                        a.target='_blank';
                        a.rel='noopener noreferrer';
                        a.textContent='Book externally';
                        mode.appendChild(a);
                    }).catch(function(){});
                });
                var pay=w.querySelector('.bh-mode[data-mode="pay_now"]');
                var reserve=w.querySelector('.bh-mode[data-mode="reserve"]');
                if(pay)pay.textContent='Book Now';
                if(reserve)reserve.textContent='Reserve Spot';
            }
            function scan(){document.querySelectorAll('.bh-booking-widget').forEach(setup);}
            if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);else scan();
            new MutationObserver(scan).observe(document.documentElement,{childList:true,subtree:true});
        })();
        </script>
        <style>
        .bh-booking-mode{flex-wrap:wrap}
        .bh-booking-mode .bh-external-booking{display:flex;align-items:center;justify-content:center;text-decoration:none}
        </style>
        <?php
    }
}

add_action('plugins_loaded', ['BubbaHubBookingOptions', 'init'], 20);
