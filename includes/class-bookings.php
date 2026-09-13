<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Booking Engine.
 * Ninja Forms is the form layer; this class owns booking, availability,
 * reservation emails and optional GetPaid invoices. No AI/API key required.
 */
final class BubbaHubBookings {
    const TABLE_SUFFIX = 'bubbahub_bookings';
    const REST_NS = 'bubbahub/v1';

    public static function init() {
        add_action('init', [__CLASS__, 'ensure_schema'], 5);
        add_action('rest_api_init', [__CLASS__, 'register_rest']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 25);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_shortcode('bubbahub_booking', [__CLASS__, 'shortcode']);
        add_action('ninja_forms_after_submission', [__CLASS__, 'ninja_after_submission'], 20, 1);
        add_action('wpinv_complete_payment', [__CLASS__, 'getpaid_payment_complete'], 20, 1);
        add_action('wpinv_update_status', [__CLASS__, 'getpaid_status_changed'], 20, 3);
    }

    public static function defaults() {
        return [
            'venue_post_type' => 'auto',
            'class_field' => 'booking_classes',
            'date_field' => 'booking_dates',
            'ticket_field' => 'booking_tickets',
            'getpaid_item_id' => 0,
            'booking_hold_minutes' => 30,
            'ninja_form_id' => 0,
            'admin_email' => get_option('admin_email'),
        ];
    }

    public static function settings() {
        return wp_parse_args((array) get_option('bubbahub_booking_settings', []), self::defaults());
    }

    public static function ensure_schema() {
        global $wpdb;
        if (get_option('bubbahub_booking_schema_version', '0') === '1') return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token varchar(64) NOT NULL,
            form_id bigint(20) unsigned NOT NULL DEFAULT 0,
            submission_id bigint(20) unsigned NOT NULL DEFAULT 0,
            venue_id bigint(20) unsigned NOT NULL,
            class_key varchar(190) NOT NULL,
            class_label varchar(255) NOT NULL,
            booking_date date NOT NULL,
            tickets_json longtext NULL,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'GBP',
            customer_first_name varchar(190) NULL,
            customer_last_name varchar(190) NULL,
            customer_email varchar(190) NOT NULL,
            customer_phone varchar(80) NULL,
            mode varchar(20) NOT NULL DEFAULT 'reserve',
            status varchar(30) NOT NULL DEFAULT 'reserved',
            invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
            expires_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY venue_date (venue_id,booking_date),
            KEY status_date (status,booking_date),
            KEY invoice_id (invoice_id),
            KEY customer_email (customer_email)
        ) {$charset};");
        update_option('bubbahub_booking_schema_version', '1', false);
    }

    public static function venue_post_type() {
        $wanted = sanitize_key(self::settings()['venue_post_type'] ?? 'auto');
        if ($wanted !== 'auto' && post_type_exists($wanted)) return $wanted;
        foreach (['venue', 'venues', 'group', 'groups'] as $candidate) {
            if (post_type_exists($candidate)) return $candidate;
        }
        return 'post';
    }

    public static function admin_menu() {
        add_submenu_page('options-general.php', 'BubbaHub Bookings', 'BubbaHub Bookings', 'manage_options', 'bubbahub-bookings', [__CLASS__, 'settings_page']);
    }

    public static function register_settings() {
        register_setting('bubbahub_booking', 'bubbahub_booking_settings', [__CLASS__, 'sanitize_settings']);
    }

    public static function sanitize_settings($input) {
        $d = self::defaults();
        return [
            'venue_post_type' => sanitize_key($input['venue_post_type'] ?? $d['venue_post_type']),
            'class_field' => sanitize_key($input['class_field'] ?? $d['class_field']),
            'date_field' => sanitize_key($input['date_field'] ?? $d['date_field']),
            'ticket_field' => sanitize_key($input['ticket_field'] ?? $d['ticket_field']),
            'getpaid_item_id' => absint($input['getpaid_item_id'] ?? 0),
            'booking_hold_minutes' => max(1, min(1440, absint($input['booking_hold_minutes'] ?? 30))),
            'ninja_form_id' => absint($input['ninja_form_id'] ?? 0),
            'admin_email' => sanitize_email($input['admin_email'] ?? get_option('admin_email')),
        ];
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::settings();
        $venue_type = self::venue_post_type();
        $items = get_posts(['post_type' => 'wpi_item', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC']);
        ?>
        <div class="wrap">
            <h1>BubbaHub Bookings</h1>
            <p>Booking logic is handled by BubbaHub; Ninja Forms is the customer-facing form layer. No OpenAI API is required.</p>
            <form method="post" action="options.php">
                <?php settings_fields('bubbahub_booking'); ?>
                <table class="form-table" role="presentation">
                    <tr><th>Venue post type</th><td><input class="regular-text" name="bubbahub_booking_settings[venue_post_type]" value="<?php echo esc_attr($s['venue_post_type']); ?>"><p class="description">Use <code>auto</code> to use venue/venues/group/groups. Detected now: <code><?php echo esc_html($venue_type); ?></code>.</p></td></tr>
                    <tr><th>Classes ACF field</th><td><input class="regular-text" name="bubbahub_booking_settings[class_field]" value="<?php echo esc_attr($s['class_field']); ?>"><p class="description">Repeater, select, text or JSON. Repeater rows can use name/title/class/label and optional key/price.</p></td></tr>
                    <tr><th>Dates ACF field</th><td><input class="regular-text" name="bubbahub_booking_settings[date_field]" value="<?php echo esc_attr($s['date_field']); ?>"><p class="description">Repeater/list. Rows can use date, start_date or datetime plus capacity/max/available.</p></td></tr>
                    <tr><th>Tickets ACF field</th><td><input class="regular-text" name="bubbahub_booking_settings[ticket_field]" value="<?php echo esc_attr($s['ticket_field']); ?>"><p class="description">Repeater rows can use name/label/title, price and max.</p></td></tr>
                    <tr><th>GetPaid item</th><td><select name="bubbahub_booking_settings[getpaid_item_id]"><option value="0">— Not configured —</option><?php foreach ($items as $item): ?><option value="<?php echo esc_attr($item->ID); ?>" <?php selected((int) $s['getpaid_item_id'], (int) $item->ID); ?>><?php echo esc_html($item->post_title . ' (#' . $item->ID . ')'); ?></option><?php endforeach; ?></select><p class="description">Create one generic GetPaid item such as “BubbaHub Booking”. The booking total overrides its price.</p></td></tr>
                    <tr><th>Payment hold</th><td><input type="number" min="1" max="1440" name="bubbahub_booking_settings[booking_hold_minutes]" value="<?php echo esc_attr($s['booking_hold_minutes']); ?>"> minutes</td></tr>
                    <tr><th>Ninja Form ID</th><td><input type="number" min="0" name="bubbahub_booking_settings[ninja_form_id]" value="<?php echo esc_attr($s['ninja_form_id']); ?>"><p class="description">Optional. Restricts automatic booking processing to one form.</p></td></tr>
                    <tr><th>Admin notification email</th><td><input type="email" class="regular-text" name="bubbahub_booking_settings[admin_email]" value="<?php echo esc_attr($s['admin_email']); ?>"></td></tr>
                </table>
                <?php submit_button('Save booking settings'); ?>
            </form>
            <hr>
            <h2>Ninja Forms setup</h2>
            <ol>
                <li>Create customer fields for first name, last name, email and phone.</li>
                <li>Add two hidden fields with keys <code>bh_booking_payload</code> and <code>bh_booking_token</code>.</li>
                <li>Add an HTML field containing <code>[bubbahub_booking]</code>.</li>
                <li>Add the normal Ninja Forms submit button.</li>
                <li>Optionally add Ninja Forms' Record Submission action.</li>
            </ol>
        </div>
        <?php
    }

    public static function get_field($post_id, $field) {
        if (function_exists('get_field')) {
            $value = get_field($field, $post_id);
            if ($value !== false && $value !== null && $value !== '') return $value;
        }
        return get_post_meta($post_id, $field, true);
    }

    public static function normalize_rows($value) {
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim === '') return [];
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) return $decoded;
            return preg_split('/\r\n|\r|\n|,/', $trim, -1, PREG_SPLIT_NO_EMPTY);
        }
        return is_array($value) ? $value : [];
    }

    public static function row_value($row, $keys, $default = '') {
        if (!is_array($row)) return $default;
        foreach ((array) $keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== '' && $row[$key] !== null) return $row[$key];
        }
        return $default;
    }

    public static function classes_for_venue($venue_id) {
        $rows = self::normalize_rows(self::get_field($venue_id, self::settings()['class_field']));
        $out = [];
        foreach ($rows as $index => $row) {
            if (is_scalar($row)) {
                $label = sanitize_text_field((string) $row);
                if ($label) $out[] = ['key' => sanitize_title($label), 'label' => $label, 'price' => 0];
                continue;
            }
            $label = sanitize_text_field((string) self::row_value($row, ['name','title','class','label','class_name'], ''));
            if (!$label) continue;
            $key = sanitize_key((string) self::row_value($row, ['key','slug','id'], sanitize_title($label) ?: 'class-'.$index));
            $out[] = ['key' => $key, 'label' => $label, 'price' => (float) self::row_value($row, ['price','cost','fee'], 0)];
        }
        return $out;
    }

    public static function dates_for_venue($venue_id) {
        $rows = self::normalize_rows(self::get_field($venue_id, self::settings()['date_field']));
        $out = [];
        foreach ($rows as $row) {
            $raw = is_scalar($row) ? (string) $row : (string) self::row_value($row, ['date','start_date','datetime','session_date'], '');
            $timestamp = $raw ? strtotime($raw) : false;
            if (!$timestamp) continue;
            $date = wp_date('Y-m-d', $timestamp);
            if ($date < current_time('Y-m-d')) continue;
            $out[] = [
                'date' => $date,
                'label' => wp_date(get_option('date_format'), $timestamp),
                'capacity' => is_array($row) ? (int) self::row_value($row, ['capacity','max','spaces','limit'], 0) : 0,
                'available_override' => is_array($row) ? (int) self::row_value($row, ['available','spaces_available'], 0) : 0,
            ];
        }
        return $out;
    }

    public static function tickets_for_venue($venue_id) {
        $rows = self::normalize_rows(self::get_field($venue_id, self::settings()['ticket_field']));
        $out = [];
        foreach ($rows as $index => $row) {
            if (is_scalar($row)) {
                $label = sanitize_text_field((string) $row);
                if ($label) $out[] = ['key' => sanitize_title($label), 'label' => $label, 'price' => 0, 'max' => 0];
                continue;
            }
            $label = sanitize_text_field((string) self::row_value($row, ['name','label','title','ticket'], ''));
            if (!$label) continue;
            $key = sanitize_key((string) self::row_value($row, ['key','slug','id'], sanitize_title($label) ?: 'ticket-'.$index));
            $out[] = ['key' => $key, 'label' => $label, 'price' => (float) self::row_value($row, ['price','cost','fee'], 0), 'max' => (int) self::row_value($row, ['max','maximum','limit','quantity_limit'], 0)];
        }
        return $out;
    }

    public static function active_booked_count($venue_id, $date) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='expired' WHERE status='pending_payment' AND expires_at IS NOT NULL AND expires_at < %s", current_time('mysql')));
        $rows = $wpdb->get_col($wpdb->prepare("SELECT tickets_json FROM {$table} WHERE venue_id=%d AND booking_date=%s AND status IN ('pending_payment','reserved','confirmed')", $venue_id, $date));
        $count = 0;
        foreach ($rows as $json) {
            $tickets = json_decode((string) $json, true);
            if (!is_array($tickets)) continue;
            foreach ($tickets as $ticket) $count += max(0, (int) ($ticket['quantity'] ?? 0));
        }
        return $count;
    }

    public static function availability($venue_id, $date) {
        foreach (self::dates_for_venue($venue_id) as $d) {
            if ($d['date'] !== $date) continue;
            $booked = self::active_booked_count($venue_id, $date);
            if ($d['available_override'] > 0) $available = max(0, $d['available_override'] - $booked);
            elseif ($d['capacity'] > 0) $available = max(0, $d['capacity'] - $booked);
            else $available = null;
            return ['available' => $available, 'booked' => $booked, 'capacity' => $d['capacity']];
        }
        return ['available' => 0, 'booked' => 0, 'capacity' => 0];
    }

    public static function venues() {
        $posts = get_posts(['post_type' => self::venue_post_type(), 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $out = [];
        foreach ($posts as $post) $out[] = ['id' => (int) $post->ID, 'label' => get_the_title($post->ID)];
        return $out;
    }

    public static function register_rest() {
        register_rest_route(self::REST_NS, '/booking/venue/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function($request) {
                $id = absint($request['id']);
                if (!$id || get_post_type($id) !== self::venue_post_type()) return new WP_Error('invalid_venue', 'Venue not found', ['status' => 404]);
                $dates = self::dates_for_venue($id);
                foreach ($dates as &$date) $date['availability'] = self::availability($id, $date['date']);
                return ['venue' => ['id' => $id, 'label' => get_the_title($id)], 'classes' => self::classes_for_venue($id), 'dates' => $dates, 'tickets' => self::tickets_for_venue($id)];
            },
        ]);
    }

    public static function verify_payload($payload) {
        $venue_id = absint($payload['venue_id'] ?? 0);
        $class_key = sanitize_key($payload['class_key'] ?? '');
        $date = sanitize_text_field($payload['date'] ?? '');
        $tickets = is_array($payload['tickets'] ?? null) ? $payload['tickets'] : [];
        if (!$venue_id || get_post_type($venue_id) !== self::venue_post_type()) return new WP_Error('invalid_venue', 'Please select a valid venue.');
        $class = null;
        foreach (self::classes_for_venue($venue_id) as $candidate) if ($candidate['key'] === $class_key) $class = $candidate;
        if (!$class) return new WP_Error('invalid_class', 'Please select a valid class.');
        $valid_date = false;
        foreach (self::dates_for_venue($venue_id) as $d) if ($d['date'] === $date) $valid_date = true;
        if (!$valid_date) return new WP_Error('invalid_date', 'Please select an available date.');
        $catalog = self::tickets_for_venue($venue_id);
        $clean = [];
        $total = 0;
        $ticket_count = 0;
        foreach ($tickets as $ticket) {
            $key = sanitize_key($ticket['key'] ?? '');
            $qty = max(0, min(99, (int) ($ticket['quantity'] ?? 0)));
            if (!$key || !$qty) continue;
            foreach ($catalog as $item) {
                if ($item['key'] !== $key) continue;
                if ($item['max'] > 0 && $qty > $item['max']) return new WP_Error('ticket_limit', $item['label'].' has a maximum quantity of '.$item['max'].'.');
                $clean[] = ['key' => $key, 'label' => $item['label'], 'quantity' => $qty, 'price' => (float) $item['price']];
                $total += (float) $item['price'] * $qty;
                $ticket_count += $qty;
                continue 2;
            }
        }
        if (!$ticket_count) return new WP_Error('no_tickets', 'Please select at least one ticket.');
        $availability = self::availability($venue_id, $date);
        if ($availability['available'] !== null && $ticket_count > $availability['available']) return new WP_Error('sold_out', 'There are not enough spaces remaining for this date.');
        return ['venue_id' => $venue_id, 'class_key' => $class_key, 'class_label' => $class['label'], 'date' => $date, 'tickets' => $clean, 'total' => round($total, 2), 'currency' => 'GBP'];
    }

    public static function create_booking($payload, $customer, $mode = 'reserve', $form_id = 0, $submission_id = 0) {
        global $wpdb;
        $verified = self::verify_payload($payload);
        if (is_wp_error($verified)) return $verified;
        $mode = $mode === 'pay_now' ? 'pay_now' : 'reserve';
        $token = sanitize_text_field($payload['token'] ?? '') ?: wp_generate_password(40, false, false);
        $settings = self::settings();
        $expires = $mode === 'pay_now' ? wp_date('Y-m-d H:i:s', current_time('timestamp') + ((int) $settings['booking_hold_minutes'] * 60)) : null;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $inserted = $wpdb->insert($table, [
            'token' => $token,
            'form_id' => absint($form_id),
            'submission_id' => absint($submission_id),
            'venue_id' => $verified['venue_id'],
            'class_key' => $verified['class_key'],
            'class_label' => $verified['class_label'],
            'booking_date' => $verified['date'],
            'tickets_json' => wp_json_encode($verified['tickets']),
            'total' => $verified['total'],
            'currency' => $verified['currency'],
            'customer_first_name' => sanitize_text_field($customer['first_name'] ?? ''),
            'customer_last_name' => sanitize_text_field($customer['last_name'] ?? ''),
            'customer_email' => sanitize_email($customer['email'] ?? ''),
            'customer_phone' => sanitize_text_field($customer['phone'] ?? ''),
            'mode' => $mode,
            'status' => $mode === 'pay_now' ? 'pending_payment' : 'reserved',
            'expires_at' => $expires,
        ], ['%s','%d','%d','%d','%s','%s','%s','%s','%f','%s','%s','%s','%s','%s','%s','%s','%s']);
        if (!$inserted) return new WP_Error('booking_save_failed', 'The booking could not be saved. Please try again.');
        $booking_id = (int) $wpdb->insert_id;
        if ($mode === 'reserve') {
            self::send_emails($booking_id);
            return ['booking_id' => $booking_id, 'token' => $token, 'status' => 'reserved', 'payment_url' => ''];
        }
        $invoice = self::create_getpaid_invoice($booking_id, $verified, $customer);
        if (is_wp_error($invoice)) {
            $wpdb->update($table, ['status' => 'failed'], ['id' => $booking_id], ['%s'], ['%d']);
            return $invoice;
        }
        $invoice_id = is_object($invoice) ? (int) $invoice->ID : (int) $invoice;
        $wpdb->update($table, ['invoice_id' => $invoice_id], ['id' => $booking_id], ['%d'], ['%d']);
        self::send_emails($booking_id);
        return ['booking_id' => $booking_id, 'token' => $token, 'status' => 'pending_payment', 'invoice_id' => $invoice_id, 'payment_url' => get_permalink($invoice_id)];
    }

    public static function create_getpaid_invoice($booking_id, $verified, $customer) {
        if (!function_exists('wpinv_insert_invoice')) return new WP_Error('getpaid_missing', 'GetPaid is not active. Choose Reserve Now or activate GetPaid.');
        $item_id = (int) self::settings()['getpaid_item_id'];
        if (!$item_id) return new WP_Error('getpaid_item_missing', 'Please select the generic GetPaid booking item in BubbaHub Bookings settings.');
        $data = [
            'status' => 'wpi-pending',
            'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
            'cart_details' => [[
                'id' => $item_id,
                'quantity' => 1,
                'custom_price' => number_format((float) $verified['total'], 2, '.', ''),
                'meta' => ['bubbahub_booking_id' => $booking_id, 'venue_id' => $verified['venue_id'], 'booking_date' => $verified['date'], 'class' => $verified['class_label']],
            ]],
            'user_info' => [
                'first_name' => sanitize_text_field($customer['first_name'] ?? ''),
                'last_name' => sanitize_text_field($customer['last_name'] ?? ''),
                'phone' => sanitize_text_field($customer['phone'] ?? ''),
                'email' => sanitize_email($customer['email'] ?? ''),
                'country' => 'GB',
            ],
            'user_note' => 'BubbaHub booking #'.$booking_id.' — '.get_the_title($verified['venue_id']).' — '.$verified['class_label'].' — '.$verified['date'],
            'private_note' => 'BubbaHub booking ID: '.$booking_id,
        ];
        return wpinv_insert_invoice($data, true);
    }

    public static function send_emails($booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $booking_id), ARRAY_A);
        if (!$b) return;
        $venue = get_post((int) $b['venue_id']);
        $venue_name = $venue ? get_the_title($venue->ID) : 'Venue';
        $tickets = json_decode($b['tickets_json'], true) ?: [];
        $lines = [];
        foreach ($tickets as $t) $lines[] = $t['label'].' × '.(int) $t['quantity'].' — £'.number_format((float) $t['price'] * (int) $t['quantity'], 2);
        $payment = $b['invoice_id'] ? get_permalink((int) $b['invoice_id']) : '';
        $subject = 'BubbaHub booking #'.$booking_id.' — '.$venue_name;
        $body = "Booking #{$booking_id}\n\nVenue: {$venue_name}\nClass: {$b['class_label']}\nDate: {$b['booking_date']}\nTickets:\n- ".implode("\n- ", $lines)."\n\nTotal: £".number_format((float) $b['total'], 2)."\nType: ".($b['mode'] === 'pay_now' ? 'Pay Now' : 'Reserve Now')."\nStatus: {$b['status']}\n\n";
        if ($payment) $body .= "Payment link:\n{$payment}\n\n";
        $body .= 'Thank you for booking with BubbaHub.';
        if (is_email($b['customer_email'])) wp_mail($b['customer_email'], $subject, $body);
        $admin = self::settings()['admin_email'];
        if (is_email($admin)) wp_mail($admin, 'New '.$subject, $body);
        if ($venue && $venue->post_author) {
            $leader_email = get_the_author_meta('user_email', $venue->post_author);
            if (is_email($leader_email) && $leader_email !== $admin && $leader_email !== $b['customer_email']) wp_mail($leader_email, 'New '.$subject, $body);
        }
    }

    public static function ninja_after_submission($form_data) {
        $settings = self::settings();
        $form_id = absint($form_data['form_id'] ?? 0);
        if ($settings['ninja_form_id'] && $settings['ninja_form_id'] !== $form_id) return;
        $fields = [];
        foreach ((array) ($form_data['fields'] ?? []) as $field) $fields[$field['key'] ?? ''] = $field['value'] ?? '';
        if (empty($fields['bh_booking_payload'])) return;
        $payload = json_decode((string) $fields['bh_booking_payload'], true);
        if (!is_array($payload)) return;
        $customer = [
            'first_name' => self::field_guess($fields, ['first_name','firstname','fname']),
            'last_name' => self::field_guess($fields, ['last_name','lastname','lname']),
            'email' => self::field_guess($fields, ['email','email_address']),
            'phone' => self::field_guess($fields, ['phone','telephone','mobile']),
        ];
        if (!is_email($customer['email'])) return;
        $token = sanitize_text_field($fields['bh_booking_token'] ?? '');
        if ($token) {
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}".self::TABLE_SUFFIX." WHERE token=%s", $token));
            if ($existing) return;
        }
        $payload['token'] = $token;
        self::create_booking($payload, $customer, sanitize_key($payload['mode'] ?? 'reserve'), $form_id, 0);
    }

    public static function field_guess($fields, $keys) {
        foreach ($keys as $key) if (!empty($fields[$key])) return (string) $fields[$key];
        foreach ($fields as $key => $value) {
            $k = strtolower((string) $key);
            foreach ($keys as $wanted) if (strpos($k, $wanted) !== false && is_scalar($value)) return (string) $value;
        }
        return '';
    }

    public static function getpaid_payment_complete($invoice_id) {
        self::mark_invoice_booking($invoice_id, 'confirmed');
    }

    public static function getpaid_status_changed($invoice_id, $new_status, $old_status) {
        if ($new_status === 'publish' || $new_status === 'wpi-renewal') self::mark_invoice_booking($invoice_id, 'confirmed');
        elseif (in_array($new_status, ['wpi-failed','wpi-cancelled','wpi-refunded'], true)) self::mark_invoice_booking($invoice_id, 'cancelled');
    }

    public static function mark_invoice_booking($invoice_id, $status) {
        global $wpdb;
        if (!$invoice_id) return;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table} WHERE invoice_id=%d", $invoice_id));
        if (!$row) return;
        $wpdb->update($table, ['status' => $status, 'expires_at' => null], ['id' => (int) $row->id], ['%s','%s'], ['%d']);
        if ($status === 'confirmed') self::send_emails((int) $row->id);
    }

    public static function shortcode($atts = []) {
        $atts = shortcode_atts([
            'venue_field' => 'venue',
            'class_field' => 'class',
            'date_field' => 'date',
            'tickets_field' => 'tickets',
            'mode_field' => 'booking_mode',
            'total_field' => 'booking_total',
            'token_field' => 'bh_booking_token',
            'payload_field' => 'bh_booking_payload',
        ], $atts, 'bubbahub_booking');
        $venues = self::venues();
        $token = wp_generate_password(32, false, false);
        wp_enqueue_script('jquery');
        wp_register_script('bubbahub-booking-inline', '', [], defined('BUBBAHUB_VERSION') ? BUBBAHUB_VERSION : '1.0', true);
        wp_enqueue_script('bubbahub-booking-inline');
        wp_add_inline_script('bubbahub-booking-inline', 'window.BubbaHubBooking='.wp_json_encode(['rest' => esc_url_raw(rest_url(self::REST_NS.'/booking')), 'nonce' => wp_create_nonce('wp_rest'), 'fields' => $atts]).';');
        wp_add_inline_script('bubbahub-booking-inline', self::frontend_js());
        wp_register_style('bubbahub-booking-inline', false, [], defined('BUBBAHUB_VERSION') ? BUBBAHUB_VERSION : '1.0');
        wp_enqueue_style('bubbahub-booking-inline');
        wp_add_inline_style('bubbahub-booking-inline', self::frontend_css());
        ob_start();
        ?>
        <div class="bh-booking-widget" data-token="<?php echo esc_attr($token); ?>">
            <div class="bh-booking-step"><span>1</span><div><strong>Select venue</strong><select class="bh-booking-venue"><option value="">Choose a venue</option><?php foreach ($venues as $v): ?><option value="<?php echo esc_attr($v['id']); ?>"><?php echo esc_html($v['label']); ?></option><?php endforeach; ?></select></div></div>
            <div class="bh-booking-step"><span>2</span><div><strong>Select class</strong><select class="bh-booking-class" disabled><option value="">Choose a class</option></select></div></div>
            <div class="bh-booking-step"><span>3</span><div><strong>Select date</strong><div class="bh-booking-dates"><p class="bh-muted">Choose a venue first.</p></div></div></div>
            <div class="bh-booking-step"><span>4</span><div><strong>Tickets</strong><div class="bh-booking-tickets"><p class="bh-muted">Choose a venue first.</p></div></div></div>
            <div class="bh-booking-total"><span>Total</span><strong>£<span class="bh-total">0.00</span></strong></div>
            <div class="bh-booking-mode"><button type="button" class="bh-mode is-selected" data-mode="pay_now">Pay Now</button><button type="button" class="bh-mode" data-mode="reserve">Reserve Now</button></div>
            <input type="hidden" class="bh-booking-token" value="<?php echo esc_attr($token); ?>">
            <p class="bh-booking-error" role="alert" hidden></p>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function frontend_js() {
        return <<<'JS'
(function($){
function findField(form,key){if(!key)return $();var f=form.find('[data-key="'+key+'"],[data-field-key="'+key+'"]');if(f.length)return f.find('input,select,textarea').first().add(f.filter('input,select,textarea').first());return form.find('input[name="'+key+'"],select[name="'+key+'"],textarea[name="'+key+'"]');}
function setField(form,key,value){var el=findField(form,key);if(el.length)el.val(value).trigger('change');}
function updateHidden(w){var form=w.closest('form'),p=w.data('payload')||{},c=window.BubbaHubBooking.fields||{};setField(form,c.venue_field,p.venue_id||'');setField(form,c.class_field,p.class_key||'');setField(form,c.date_field,p.date||'');setField(form,c.tickets_field,JSON.stringify(p.tickets||[]));setField(form,c.mode_field,p.mode||'pay_now');setField(form,c.total_field,(p.total||0).toFixed(2));setField(form,c.token_field,w.find('.bh-booking-token').val());setField(form,c.payload_field,JSON.stringify(p));}
function esc(s){return $('<div>').text(s||'').html();}
function calc(w){var p=w.data('payload')||{};p.tickets=[];p.total=0;w.find('[data-ticket]').each(function(){var q=Math.max(0,parseInt($(this).val(),10)||0);if(q){p.tickets.push({key:$(this).data('ticket'),quantity:q});p.total+=q*Number($(this).data('price')||0);}});w.data('payload',p);w.find('.bh-total').text(p.total.toFixed(2));updateHidden(w);}
function loadVenue(w,id){var cls=w.find('.bh-booking-class'),dates=w.find('.bh-booking-dates'),tickets=w.find('.bh-booking-tickets');cls.prop('disabled',true).html('<option>Loading…</option>');dates.html('<p class="bh-muted">Loading dates…</p>');tickets.html('<p class="bh-muted">Loading tickets…</p>');$.ajax({url:window.BubbaHubBooking.rest+'/venue/'+id,headers:{'X-WP-Nonce':window.BubbaHubBooking.nonce}}).done(function(data){cls.empty().append('<option value="">Choose a class</option>');(data.classes||[]).forEach(function(c){cls.append($('<option/>',{value:c.key,text:c.label}));});cls.prop('disabled',false);dates.empty();(data.dates||[]).forEach(function(d){var a=d.availability||{},full=a.available!==null&&a.available<=0,label=d.label+(full?' — Full':(a.available!==null?' — '+a.available+' spaces':''));dates.append($('<button/>',{type:'button',class:'bh-date',disabled:full,'data-date':d.date,text:label}));});tickets.empty();(data.tickets||[]).forEach(function(t){tickets.append('<div class="bh-ticket"><div><strong>'+esc(t.label)+'</strong><small>£'+Number(t.price||0).toFixed(2)+'</small></div><input type="number" min="0" max="'+(t.max||99)+'" value="0" data-ticket="'+esc(t.key)+'" data-price="'+Number(t.price||0)+'"></div>');});w.data('payload',{venue_id:id,class_key:'',date:'',tickets:[],mode:'pay_now',total:0});updateHidden(w);calc(w);}).fail(function(){dates.html('<p class="bh-error">Unable to load this venue.</p>');});}
$(document).on('change','.bh-booking-venue',function(){var w=$(this).closest('.bh-booking-widget');loadVenue(w,$(this).val());});
$(document).on('change','.bh-booking-class',function(){var w=$(this).closest('.bh-booking-widget'),p=w.data('payload')||{};p.class_key=$(this).val();w.data('payload',p);updateHidden(w);});
$(document).on('click','.bh-date',function(){var w=$(this).closest('.bh-booking-widget'),p=w.data('payload')||{};w.find('.bh-date').removeClass('is-selected');$(this).addClass('is-selected');p.date=$(this).data('date');w.data('payload',p);updateHidden(w);});
$(document).on('input change','.bh-booking-tickets input',function(){calc($(this).closest('.bh-booking-widget'));});
$(document).on('click','.bh-mode',function(){var w=$(this).closest('.bh-booking-widget'),p=w.data('payload')||{};w.find('.bh-mode').removeClass('is-selected');$(this).addClass('is-selected');p.mode=$(this).data('mode');w.data('payload',p);updateHidden(w);});
$(document).on('click','.nf-form-cont input[type="submit"],.nf-form-cont button[type="submit"]',function(){var form=$(this).closest('form'),w=form.find('.bh-booking-widget');if(!w.length)return;var p=w.data('payload')||{};if(!p.venue_id||!p.class_key||!p.date||!p.tickets||!p.tickets.length){w.find('.bh-booking-error').text('Please complete venue, class, date and ticket selections.').prop('hidden',false);return false;}updateHidden(w);});
})(jQuery);
JS;
    }

    public static function frontend_css() {
        return '.bh-booking-widget{max-width:760px;margin:20px auto;padding:24px;border-radius:22px;background:#fff;box-shadow:0 10px 35px rgba(0,0,0,.08)}.bh-booking-step{display:grid;grid-template-columns:42px 1fr;gap:14px;padding:18px 0;border-bottom:1px solid #eee}.bh-booking-step>span{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f7d6df;color:#6f3348;font-weight:700}.bh-booking-step strong{display:block;margin:0 0 8px}.bh-booking-step select{width:100%;min-height:46px;padding:10px 12px;border:1px solid #ddd;border-radius:12px;background:#fff}.bh-booking-dates{display:flex;flex-wrap:wrap;gap:8px}.bh-date,.bh-mode{border:1px solid #ddd;background:#fff;border-radius:12px;padding:10px 14px;cursor:pointer}.bh-date.is-selected,.bh-mode.is-selected{border-color:#6f3348;background:#f7d6df}.bh-date:disabled{opacity:.45;cursor:not-allowed}.bh-ticket{display:flex;justify-content:space-between;align-items:center;padding:12px;border:1px solid #eee;border-radius:14px;margin:8px 0}.bh-ticket small{display:block;color:#777;margin-top:3px}.bh-ticket input{width:78px;padding:8px;border:1px solid #ddd;border-radius:10px}.bh-booking-total{display:flex;justify-content:space-between;font-size:18px;padding:20px 0}.bh-booking-mode{display:flex;gap:10px}.bh-booking-mode .bh-mode{flex:1}.bh-muted{color:#777}.bh-error{color:#b42318;margin-top:12px}.bh-booking-error{color:#b42318;background:#fff1f1;padding:10px;border-radius:10px}@media(max-width:600px){.bh-booking-widget{padding:16px}.bh-booking-mode{flex-direction:column}}';
    }
}
BubbaHubBookings::init();
