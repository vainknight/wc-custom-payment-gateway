<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PPL_Ajax
 * --------
 * Order creation / cart item removal. Security fixes applied vs. the
 * original script:
 *
 *  1. Real file-type validation. The original script trusted the
 *     uploaded file's *name* (pathinfo extension) to decide if it was
 *     a JPG/PNG/PDF. A file named "malware.php.jpg" or a renamed
 *     executable would pass that check. We now validate with
 *     wp_check_filetype_and_ext(), which inspects the actual file
 *     content/magic bytes (not just the name) before accepting it.
 *  2. Basic anti-spam: a honeypot field (real users never fill it,
 *     bots often do) plus a simple per-IP rate limit via transients,
 *     since the original had no protection against a script hammering
 *     wp_ajax_nopriv_ppl_crear_pedido with fake orders.
 *  3. All business data (methods, accounts) is read from PPL_Opciones
 *     instead of hardcoded arrays.
 */
class PPL_Ajax {

    private static $instancia = null;
    const RATE_LIMIT_MAX     = 8;   // max requests...
    const RATE_LIMIT_WINDOW  = 300; // ...per 5 minutes, per IP.

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action('wp_ajax_ppl_eliminar_item', array($this, 'eliminar_item'));
        add_action('wp_ajax_nopriv_ppl_eliminar_item', array($this, 'eliminar_item'));

        add_action('wp_ajax_ppl_crear_pedido', array($this, 'crear_pedido'));
        add_action('wp_ajax_nopriv_ppl_crear_pedido', array($this, 'crear_pedido'));
    }

    private function verificar_nonce() {
        if (!isset($_POST['ppl_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ppl_nonce'])), 'ppl_checkout')) {
            wp_send_json_error(array('message' => __('Your session expired, please reload the page and try again.', 'custom-payment-gateway')));
        }
    }

    private function obtener_ip_cliente() {
        foreach (array('HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])))[0];
                return trim($ip);
            }
        }
        return 'unknown';
    }

    /**
     * Basic per-IP throttle for order creation. Not a substitute for a
     * proper WAF, but it stops trivial scripted abuse of the public
     * (nopriv) endpoint without requiring extra plugins.
     */
    private function limite_de_tasa_excedido() {
        $ip  = $this->obtener_ip_cliente();
        $key = 'ppl_rate_' . md5($ip);

        $intentos = (int) get_transient($key);
        if ($intentos >= self::RATE_LIMIT_MAX) {
            return true;
        }

        set_transient($key, $intentos + 1, self::RATE_LIMIT_WINDOW);
        return false;
    }

    // ---------------------------------------------------------
    // Remove cart item
    // ---------------------------------------------------------
    public function eliminar_item() {
        $this->verificar_nonce();

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field(wp_unslash($_POST['cart_item_key'])) : '';

        if (!$cart_item_key || !WC()->cart->get_cart_item($cart_item_key)) {
            wp_send_json_error(array('message' => __('That item could not be found in your cart.', 'custom-payment-gateway')));
        }

        WC()->cart->remove_cart_item($cart_item_key);
        WC()->cart->calculate_totals();

        if (WC()->cart->is_empty()) {
            wp_send_json_success(array(
                'empty'    => true,
                'redirect' => home_url('/'),
            ));
        }

        $resumen = PPL_Core::instancia()->render_resumen(true);

        wp_send_json_success(array(
            'empty' => false,
            'html'  => $resumen['html'],
            'total' => $resumen['total'],
        ));
    }

    // ---------------------------------------------------------
    // Create order
    // ---------------------------------------------------------
    public function crear_pedido() {
        $this->verificar_nonce();

        // Honeypot: a hidden field no real user fills in. If it has a
        // value, silently pretend success without creating anything —
        // this avoids tipping off the bot that it was detected.
        if (!empty($_POST['ppl_hp_check'])) {
            wp_send_json_error(array('message' => __('Something went wrong. Please try again.', 'custom-payment-gateway')));
        }

        if ($this->limite_de_tasa_excedido()) {
            wp_send_json_error(array('message' => __('Too many attempts. Please wait a few minutes and try again.', 'custom-payment-gateway')));
        }

        if (WC()->cart->is_empty()) {
            wp_send_json_error(array('message' => __('Your cart is empty.', 'custom-payment-gateway')));
        }

        $o       = PPL_Opciones::instancia()->obtener_opciones();
        $activos = (array) $o['metodos_activos'];

        $metodo = isset($_POST['ppl_metodo']) ? sanitize_text_field(wp_unslash($_POST['ppl_metodo'])) : '';
        if (!in_array($metodo, $activos, true)) {
            wp_send_json_error(array('message' => __('Please select a valid payment method.', 'custom-payment-gateway')));
        }

        $tipo_comprador = isset($_POST['ppl_tipo_comprador']) && 'empresa' === $_POST['ppl_tipo_comprador'] ? 'empresa' : 'natural';

        $cedula = $direccion = $rif = $direccion_fiscal = '';

        if ('empresa' === $tipo_comprador) {
            $nombre           = sanitize_text_field(wp_unslash($_POST['ppl_razon_social'] ?? ''));
            $email            = sanitize_email(wp_unslash($_POST['ppl_correo_admin'] ?? ''));
            $rif              = sanitize_text_field(wp_unslash($_POST['ppl_rif'] ?? ''));
            $direccion_fiscal = sanitize_text_field(wp_unslash($_POST['ppl_direccion_fiscal'] ?? ''));
            $telefono         = '';

            if (empty($nombre) || !is_email($email) || empty($rif) || empty($direccion_fiscal)) {
                wp_send_json_error(array('message' => __('Please complete company name, tax ID, admin email and billing address.', 'custom-payment-gateway')));
            }
        } else {
            $nombre    = sanitize_text_field(wp_unslash($_POST['ppl_nombre'] ?? ''));
            $email     = sanitize_email(wp_unslash($_POST['ppl_email'] ?? ''));
            $telefono  = sanitize_text_field(wp_unslash($_POST['ppl_telefono'] ?? ''));
            $cedula    = sanitize_text_field(wp_unslash($_POST['ppl_cedula'] ?? ''));
            $direccion = sanitize_text_field(wp_unslash($_POST['ppl_direccion'] ?? ''));

            if (empty($nombre) || !is_email($email) || empty($telefono) || empty($cedula) || empty($direccion)) {
                wp_send_json_error(array('message' => __('Please complete name, phone, ID number, email and address.', 'custom-payment-gateway')));
            }
        }

        $partes = explode(' ', $nombre, 2);
        $first  = $partes[0];
        $last   = $partes[1] ?? '';

        $comprobante_id = 0;

        if ('paypal' !== $metodo) {
            $titular    = sanitize_text_field(wp_unslash($_POST['ppl_titular'] ?? ''));
            $origen     = sanitize_text_field(wp_unslash($_POST['ppl_cuenta_origen'] ?? ''));
            $fecha_pago = sanitize_text_field(wp_unslash($_POST['ppl_fecha_pago'] ?? ''));

            if ('efectivo' !== $metodo && (empty($titular) || empty($origen) || empty($fecha_pago))) {
                wp_send_json_error(array('message' => __('Please fill in the payer name, source account and payment date.', 'custom-payment-gateway')));
            }

            if ('efectivo' !== $metodo) {
                if (empty($_FILES['ppl_comprobante']['name'])) {
                    wp_send_json_error(array('message' => __('Please attach your proof of payment.', 'custom-payment-gateway')));
                }

                if ($_FILES['ppl_comprobante']['size'] > 5 * 1024 * 1024) {
                    wp_send_json_error(array('message' => __('The proof of payment must not exceed 5MB.', 'custom-payment-gateway')));
                }

                // Real content-based validation, not just the file name.
                // wp_check_filetype_and_ext() inspects the file's actual
                // bytes/MIME signature, closing the "malware.php.jpg"
                // style bypass that a name-only extension check allows.
                $archivo_tmp = $_FILES['ppl_comprobante']['tmp_name'];
                $nombre_arch = sanitize_file_name($_FILES['ppl_comprobante']['name']);
                $chequeo     = wp_check_filetype_and_ext($archivo_tmp, $nombre_arch);

                $tipos_permitidos = array('jpg', 'jpeg', 'png', 'pdf');
                $ext_valida       = $chequeo['ext'] && in_array(strtolower($chequeo['ext']), $tipos_permitidos, true);
                $mime_valido      = $chequeo['type'] && in_array($chequeo['type'], array('image/jpeg', 'image/png', 'application/pdf'), true);

                if (!$ext_valida || !$mime_valido) {
                    wp_send_json_error(array('message' => __('The proof of payment must be a JPG, PNG or PDF file.', 'custom-payment-gateway')));
                }

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $comprobante_id = media_handle_upload('ppl_comprobante', 0);
                if (is_wp_error($comprobante_id)) {
                    wp_send_json_error(array('message' => __('We could not upload the proof of payment. Please try again.', 'custom-payment-gateway')));
                }
            }
        }

        // Create the order from the current cart.
        $order = wc_create_order(array('customer_id' => get_current_user_id()));

        foreach (WC()->cart->get_cart() as $item) {
            $order->add_product($item['data'], $item['quantity']);
        }

        $order->set_address(array(
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $email,
            'phone'      => $telefono,
            'address_1'  => 'empresa' === $tipo_comprador ? $direccion_fiscal : $direccion,
        ), 'billing');

        $order->calculate_totals();

        $order->update_meta_data('_ppl_tipo_comprador', $tipo_comprador);
        if ('empresa' === $tipo_comprador) {
            $order->update_meta_data('_ppl_razon_social', $nombre);
            $order->update_meta_data('_ppl_rif', $rif);
            $order->update_meta_data('_ppl_direccion_fiscal', $direccion_fiscal);
            $order->update_meta_data('_ppl_correo_admin', $email);
            $order->update_meta_data('_ppl_empresa_personas', sanitize_text_field(wp_unslash($_POST['ppl_empresa_personas'] ?? '')));
        } else {
            $order->update_meta_data('_ppl_cedula', $cedula);
            $order->update_meta_data('_ppl_direccion', $direccion);
        }
        $order->update_meta_data('_ppl_notas', sanitize_textarea_field(wp_unslash($_POST['ppl_notas'] ?? '')));

        $core = PPL_Core::instancia();

        if ('paypal' === $metodo) {
            $order->set_payment_method($o['paypal_gateway_id']);
            $order->set_status('pending');
            $order->save();

            WC()->cart->empty_cart();

            wp_send_json_success(array(
                'redirect' => $order->get_checkout_payment_url(true),
            ));
        } else {
            $order->update_meta_data('_ppl_metodo_pago', $metodo);
            $order->update_meta_data('_ppl_titular', sanitize_text_field(wp_unslash($_POST['ppl_titular'] ?? '')));
            $order->update_meta_data('_ppl_cuenta_origen', sanitize_text_field(wp_unslash($_POST['ppl_cuenta_origen'] ?? '')));
            $order->update_meta_data('_ppl_fecha_pago', sanitize_text_field(wp_unslash($_POST['ppl_fecha_pago'] ?? '')));

            if ('efectivo' === $metodo) {
                $order->update_meta_data('_ppl_oficina', sanitize_text_field(wp_unslash($_POST['ppl_oficina'] ?? '')));
            }
            if ($comprobante_id) {
                $order->update_meta_data('_ppl_comprobante_id', $comprobante_id);
                wp_update_post(array('ID' => $comprobante_id, 'post_parent' => $order->get_id()));
            }

            $order->set_payment_method('ppl_manual');
            $order->set_payment_method_title($core->metodo_label($metodo));
            $order->set_status('efectivo' === $metodo ? 'on-hold' : 'pend-verif');
            $order->add_order_note(sprintf(
                /* translators: %s: payment method label */
                __('Order registered by the customer via the custom payment gateway. Method: %s. Pending manual verification.', 'custom-payment-gateway'),
                $core->metodo_label($metodo)
            ));
            $order->save();

            WC()->cart->empty_cart();

            $admin_email = get_option('admin_email');
            $asunto      = sprintf(
                /* translators: %d: order ID */
                __('New order pending verification #%d', 'custom-payment-gateway'),
                $order->get_id()
            );
            $cuerpo = sprintf(
                "%s\n\n%s: #%d\n%s: %s (%s)\n%s: %s\n\n%s",
                __('A new manual payment was registered.', 'custom-payment-gateway'),
                __('Order', 'custom-payment-gateway'),
                $order->get_id(),
                __('Customer', 'custom-payment-gateway'),
                $nombre,
                $email,
                __('Method', 'custom-payment-gateway'),
                $core->metodo_label($metodo),
                __('Review and verify the proof of payment from the WooCommerce dashboard.', 'custom-payment-gateway')
            );
            wp_mail($admin_email, $asunto, $cuerpo);

            wp_send_json_success(array(
                'redirect' => $core->url_confirmacion($order),
            ));
        }
    }
}
