<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PPL_Core
 * --------
 * Renders the three shortcodes and the dynamic CSS. All business
 * data (bank accounts, offices, colors) comes from PPL_Opciones —
 * nothing is hardcoded here.
 */
class PPL_Core {

    private static $instancia = null;

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action('init', array($this, 'registrar_estado_pedido'));
        add_filter('wc_order_statuses', array($this, 'agregar_estado_pedido'));

        add_shortcode('pasarela_carrito', array($this, 'shortcode_carrito'));
        add_shortcode('pasarela_pago', array($this, 'shortcode_pago'));
        add_shortcode('pasarela_confirmacion', array($this, 'shortcode_confirmacion'));

        add_action('wp_enqueue_scripts', array($this, 'cargar_assets'));
        add_action('wp_head', array($this, 'imprimir_css_dinamico'));

        add_filter('woocommerce_payment_gateways', array($this, 'registrar_gateway_manual'));
    }

    // ---------------------------------------------------------
    // Custom order status: "Pending verification"
    // ---------------------------------------------------------
    public function registrar_estado_pedido() {
        register_post_status('wc-pend-verif', array(
            'label'                     => __('Pending verification', 'custom-payment-gateway'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Pending verification <span class="count">(%s)</span>',
                'Pending verification <span class="count">(%s)</span>',
                'custom-payment-gateway'
            ),
        ));
    }

    public function agregar_estado_pedido($order_statuses) {
        $new = array();
        foreach ($order_statuses as $key => $label) {
            $new[$key] = $label;
            if ('wc-on-hold' === $key) {
                $new['wc-pend-verif'] = __('Pending verification', 'custom-payment-gateway');
            }
        }
        return $new;
    }

    // ---------------------------------------------------------
    // Assets
    // ---------------------------------------------------------
    public function cargar_assets() {
        if (!$this->debe_cargar_assets()) {
            return;
        }
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ppl-checkout',
            PPL_PLUGIN_URL . 'assets/checkout.js',
            array('jquery'),
            PPL_VERSION,
            true
        );

        $o = PPL_Opciones::instancia()->obtener_opciones();
        wp_localize_script('ppl-checkout', 'pplData', array(
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'tasaFallback1' => (float) $o['tasa_referencia_1_valor'],
            'tasaFallback2' => (float) $o['tasa_referencia_2_valor'],
            'tasaNombre1'   => $o['tasa_referencia_1_nombre'],
            'tasaNombre2'   => $o['tasa_referencia_2_nombre'],
        ));
    }

    private function debe_cargar_assets() {
        if (!function_exists('is_cart')) {
            return false;
        }
        global $post;
        if (is_cart() || is_checkout()) {
            return true;
        }
        if ($post && has_shortcode($post->post_content, 'pasarela_carrito')) {
            return true;
        }
        if ($post && has_shortcode($post->post_content, 'pasarela_pago')) {
            return true;
        }
        if ($post && has_shortcode($post->post_content, 'pasarela_confirmacion')) {
            return true;
        }
        return false;
    }

    // ---------------------------------------------------------
    // Dynamic CSS from saved options
    // ---------------------------------------------------------
    public function imprimir_css_dinamico() {
        if (!$this->debe_cargar_assets()) {
            return;
        }
        $o = PPL_Opciones::instancia()->obtener_opciones();
        ?>
        <style id="ppl-estilos-dinamicos">
        .ppl-wrap{
            --ppl-dark: <?php echo esc_html($o['color_texto']); ?>;
            --ppl-primary: <?php echo esc_html($o['color_primario']); ?>;
            --ppl-primary-soft: <?php echo esc_html($o['color_primario_hover']); ?>;
            --ppl-secondary: <?php echo esc_html($o['color_secundario']); ?>;
            --ppl-accent: <?php echo esc_html($o['color_acento']); ?>;
            --ppl-highlight: <?php echo esc_html($o['color_fondo_resalte']); ?>;
            --ppl-bg: <?php echo esc_html($o['color_fondo_input']); ?>;
            --ppl-danger: <?php echo esc_html($o['color_peligro']); ?>;
            --ppl-radius: <?php echo esc_html($o['radio_borde']); ?>px;
            max-width:1040px;margin:0 auto;color:var(--ppl-dark);
            font-family:<?php echo esc_html($o['familia_fuente']); ?>;
            opacity:0;
            transition: opacity 0.3s ease-in-out;
        }
        .ppl-wrap.ppl-loaded{opacity:1}
        .ppl-steps{display:flex;justify-content:space-between;align-items:center;margin:0 0 22px;font-size:11px;color:#8a95a1;text-transform:uppercase;letter-spacing:.05em}
        .ppl-steps span{position:relative;flex:1;text-align:center}
        .ppl-steps span::before{content:"";display:block;width:8px;height:8px;border-radius:50%;background:#d7dde3;margin:0 auto 6px}
        .ppl-steps span.active{color:var(--ppl-secondary);font-weight:700}
        .ppl-steps span.active::before{background:var(--ppl-secondary)}
        .ppl-grid{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
        @media(max-width:860px){.ppl-grid{grid-template-columns:1fr}}
        .ppl-col-summary{position:sticky;top:20px}
        @media(max-width:860px){.ppl-col-summary{position:static}}
        .ppl-card{background:#fff;border:1px solid #e4e7eb;border-radius:var(--ppl-radius);padding:20px;margin-bottom:16px;box-shadow:0 4px 18px rgba(15,42,71,.06)}
        .ppl-card h3{margin:0 0 14px;font-size:15px;font-weight:700;color:var(--ppl-dark)}
        .ppl-card .ppl-field:last-child{margin-bottom:0}
        .ppl-total-bar{display:flex;justify-content:space-between;align-items:center;background:var(--ppl-highlight);border-radius:calc(var(--ppl-radius) - 4px);padding:14px 18px;margin-bottom:16px}
        .ppl-total-bar .lbl{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--ppl-secondary);font-weight:700}
        .ppl-total-bar .amt{font-size:22px;font-weight:800;color:var(--ppl-dark)}
        .ppl-fx{font-size:11px;color:#7a8794;margin-top:6px}
        .ppl-field{margin-bottom:14px}
        .ppl-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
        .ppl-field-full{grid-column:1 / -1}
        @media(max-width:520px){.ppl-field-grid{grid-template-columns:1fr}}
        .ppl-field label{display:block;font-size:12px;font-weight:600;color:#4a5560;margin-bottom:5px}
        .ppl-field input[type=text],.ppl-field input[type=email],.ppl-field input[type=tel],.ppl-field input[type=date],.ppl-field select,.ppl-field textarea{
            width:100%;padding:11px 13px;border:1px solid #dde1e6;border-radius:calc(var(--ppl-radius) - 8px);font-size:14px;background:var(--ppl-bg);box-sizing:border-box;
            font-family:inherit;color:var(--ppl-dark)}
        .ppl-field input:focus,.ppl-field select:focus,.ppl-field textarea:focus{outline:none;border-color:var(--ppl-secondary)}
        .ppl-field textarea{min-height:70px;resize:vertical}
        .ppl-field-notes{margin-top:20px}
        .ppl-radio-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .ppl-radio-grid label{display:flex;align-items:center;gap:8px;border:1px solid #dde1e6;border-radius:calc(var(--ppl-radius) - 8px);padding:10px 12px;font-size:13px;cursor:pointer;background:var(--ppl-bg);color:var(--ppl-dark)}
        .ppl-radio-grid input{accent-color:var(--ppl-secondary)}
        .ppl-radio-grid label.checked{border-color:var(--ppl-secondary);background:var(--ppl-highlight);font-weight:600}
        .ppl-icon{width:22px;height:22px;display:inline-block;vertical-align:middle;flex:none;object-fit:contain}
        .ppl-icon-placeholder{background:var(--ppl-primary);color:var(--ppl-accent);border-radius:50%;text-align:center;line-height:22px;font-size:11px;font-weight:700}
        .ppl-badge{display:inline-block;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;background:var(--ppl-primary);color:var(--ppl-accent);margin-left:auto}
        .ppl-method-panel{background:var(--ppl-highlight);border-radius:calc(var(--ppl-radius) - 6px);padding:14px;margin-top:10px;margin-bottom:20px;font-size:13px;line-height:1.6;white-space:pre-line;color:#3a4450;display:none}
        .ppl-method-panel.show{display:block}
        .ppl-file{border:1.5px dashed #c3cad1;border-radius:calc(var(--ppl-radius) - 8px);padding:16px;text-align:center;font-size:13px;color:#4a5560;background:var(--ppl-bg);cursor:pointer}
        .ppl-btn{display:block;width:100%;background:var(--ppl-primary);color:var(--ppl-accent);border:none;border-radius:calc(var(--ppl-radius) - 6px);padding:15px;font-size:15px;font-weight:700;letter-spacing:.03em;cursor:pointer;text-transform:uppercase;font-family:inherit;transition:background .15s}
        .ppl-btn:hover{background:var(--ppl-primary-soft)}
        .ppl-btn:disabled{opacity:.6;cursor:not-allowed}
        .ppl-msg{font-size:13px;border-radius:calc(var(--ppl-radius) - 8px);padding:10px 14px;margin-bottom:14px}
        .ppl-msg.error{background:#fbe6e4;color:var(--ppl-danger)}
        .ppl-msg.success{background:var(--ppl-highlight);color:var(--ppl-primary)}
        .ppl-cart-item{display:flex;align-items:center;gap:10px;font-size:13px;padding:8px 0;border-bottom:1px solid #eef0f2;color:var(--ppl-dark)}
        .ppl-cart-item:last-child{border-bottom:none}
        .ppl-cart-item .name{flex:1}
        .ppl-cart-item .price{font-weight:700}
        .ppl-cart-item .ppl-remove-item{flex:none;width:22px;height:22px;border:none;background:transparent;color:var(--ppl-danger);font-size:16px;line-height:1;cursor:pointer;padding:0;border-radius:50%;font-family:inherit}
        .ppl-cart-item .ppl-remove-item:hover{background:#fbe6e4}
        .ppl-cart-item .ppl-remove-item:disabled{opacity:.4;cursor:default}
        .ppl-resumen-desglose{margin-top:10px;padding-top:10px;border-top:1px dashed #dde1e6}
        .ppl-resumen-row{display:flex;justify-content:space-between;font-size:13px;color:#4a5560;padding:3px 0}
        .ppl-empty{text-align:center;padding:60px 20px;max-width:420px;margin:0 auto}
        .ppl-empty h2{margin:0 0 10px;font-size:22px;font-weight:700;color:var(--ppl-dark)}
        .ppl-empty p{margin:0 0 24px;font-size:14px;color:#4a5560}
        .ppl-detail-list{display:flex;flex-direction:column}
        .ppl-detail-row{display:flex;justify-content:space-between;gap:16px;padding:9px 0;border-bottom:1px solid #eef0f2;font-size:13px}
        .ppl-detail-row:last-child{border-bottom:none}
        .ppl-detail-row .label{color:#4a5560;font-weight:600;flex:none;width:45%}
        .ppl-detail-row .value{color:var(--ppl-dark);text-align:right;flex:1;word-break:break-word}
        .ppl-btn-inline{display:inline-block;width:auto;padding:13px 28px;text-decoration:none}
        .ppl-honeypot{position:absolute !important;left:-9999px !important;top:-9999px !important;height:0;width:0;overflow:hidden}
        @media(max-width:480px){.ppl-radio-grid{grid-template-columns:1fr}}
        </style>
        <script>
        window.addEventListener('load', function () {
            document.querySelectorAll('.ppl-wrap').forEach(function (el) { el.classList.add('ppl-loaded'); });
        });
        </script>
        <?php
    }

    // ---------------------------------------------------------
    // Live/reference exchange rate resolution
    // ---------------------------------------------------------
    public function obtener_tasa_referencia_1() {
        $o       = PPL_Opciones::instancia()->obtener_opciones();
        $funcion = trim((string) $o['tasa_bcv_funcion']);

        if ($funcion && function_exists($funcion)) {
            $valor = call_user_func($funcion);
            // Accept both "90.50" and "90,50" (thousand-separator) formats.
            $valor = str_replace('.', '', (string) $valor);
            $valor = str_replace(',', '.', $valor);
            if (is_numeric($valor) && (float) $valor > 0) {
                return (float) $valor;
            }
        }

        return (float) $o['tasa_referencia_1_valor'];
    }

    // ---------------------------------------------------------
    // Empty-cart HTML
    // ---------------------------------------------------------
    public function carrito_vacio_html() {
        ob_start();
        ?>
        <div class="ppl-wrap ppl-loaded">
            <div class="ppl-empty">
                <h2><?php esc_html_e('Your cart is empty', 'custom-payment-gateway'); ?></h2>
                <p><?php esc_html_e('Please add a product to continue.', 'custom-payment-gateway'); ?></p>
                <a class="ppl-btn ppl-btn-inline" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to home', 'custom-payment-gateway'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------
    // Order summary block (shared between shortcodes and AJAX response)
    // ---------------------------------------------------------
    public function render_resumen($return_data = false) {
        ob_start();
        ?>
        <div class="ppl-card">
            <h3><?php esc_html_e('Order summary', 'custom-payment-gateway'); ?></h3>
            <?php foreach (WC()->cart->get_cart() as $key => $item) :
                $product = $item['data'];
                ?>
                <div class="ppl-cart-item" data-key="<?php echo esc_attr($key); ?>">
                    <span class="name"><?php echo esc_html($product->get_name()); ?> x<?php echo (int) $item['quantity']; ?></span>
                    <span class="price"><?php echo wp_kses_post(wc_price($item['line_total'] + $item['line_tax'])); ?></span>
                    <button type="button" class="ppl-remove-item" data-key="<?php echo esc_attr($key); ?>" title="<?php esc_attr_e('Remove item', 'custom-payment-gateway'); ?>">&times;</button>
                </div>
            <?php endforeach; ?>

            <?php $totals = WC()->cart->get_totals(); ?>
            <div class="ppl-resumen-desglose">
                <div class="ppl-resumen-row">
                    <span><?php esc_html_e('Subtotal', 'custom-payment-gateway'); ?></span>
                    <span><?php echo wp_kses_post(wc_price($totals['subtotal'])); ?></span>
                </div>
                <?php if ($totals['total_tax'] > 0) : ?>
                    <div class="ppl-resumen-row">
                        <span><?php esc_html_e('Taxes', 'custom-payment-gateway'); ?></span>
                        <span><?php echo wp_kses_post(wc_price($totals['total_tax'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="ppl-total-bar">
            <span class="lbl"><?php esc_html_e('Total due', 'custom-payment-gateway'); ?></span>
            <span class="amt"><?php echo wp_kses_post(wc_price($totals['total'])); ?></span>
        </div>
        <?php
        $html = ob_get_clean();

        if ($return_data) {
            return array('html' => $html, 'total' => $totals['total']);
        }
        echo $html; // phpcs:ignore -- built entirely from escaped pieces above
    }

    // ---------------------------------------------------------
    // Icon HTML for a payment method
    // ---------------------------------------------------------
    public function icono_html($metodo) {
        $etiquetas = PPL_Opciones::metodos_disponibles();
        $inicial   = strtoupper(mb_substr($etiquetas[$metodo] ?? $metodo, 0, 1));

        /**
         * Filter to let a theme or another plugin supply a real icon
         * (e.g. an <img> tag) for a given payment method, instead of
         * the default placeholder circle.
         */
        $icono = apply_filters('ppl_icono_metodo_html', '', $metodo);
        if ($icono) {
            return $icono;
        }

        return '<span class="ppl-icon ppl-icon-placeholder">' . esc_html($inicial) . '</span>';
    }

    // ---------------------------------------------------------
    // Shortcode: [pasarela_carrito]
    // ---------------------------------------------------------
    public function shortcode_carrito($atts) {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        if (WC()->cart->is_empty()) {
            return $this->carrito_vacio_html();
        }

        $o            = PPL_Opciones::instancia()->obtener_opciones();
        $checkout_url = $o['pagina_checkout_id'] ? get_permalink($o['pagina_checkout_id']) : wc_get_checkout_url();

        ob_start();
        ?>
        <div class="ppl-wrap">
            <?php wp_nonce_field('ppl_checkout', 'ppl_nonce'); ?>
            <div class="ppl-steps">
                <span class="active"><?php esc_html_e('Cart', 'custom-payment-gateway'); ?></span>
                <span><?php esc_html_e('Payment', 'custom-payment-gateway'); ?></span>
                <span><?php esc_html_e('Confirmation', 'custom-payment-gateway'); ?></span>
            </div>
            <div id="ppl-resumen-wrap">
                <?php $this->render_resumen(); ?>
            </div>
            <a class="ppl-btn" style="text-decoration:none;text-align:center;display:block" href="<?php echo esc_url($checkout_url); ?>"><?php esc_html_e('Go to payment', 'custom-payment-gateway'); ?></a>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------
    // Shortcode: [pasarela_pago]
    // ---------------------------------------------------------
    public function shortcode_pago($atts) {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        if (WC()->cart->is_empty()) {
            return $this->carrito_vacio_html();
        }

        $o        = PPL_Opciones::instancia()->obtener_opciones();
        $metodos  = PPL_Opciones::metodos_disponibles();
        $activos  = (array) $o['metodos_activos'];
        $oficinas = PPL_Opciones::instancia()->obtener_oficinas();
        $total    = WC()->cart->get_total('edit');

        if (empty($activos)) {
            return '<div class="ppl-wrap"><div class="ppl-msg error">' . esc_html__('No payment methods are enabled yet. Please contact the site administrator.', 'custom-payment-gateway') . '</div></div>';
        }

        ob_start();
        ?>
        <div class="ppl-wrap" data-total="<?php echo esc_attr($total); ?>" data-tasa1="<?php echo esc_attr($this->obtener_tasa_referencia_1()); ?>" data-tasa2="<?php echo esc_attr($o['tasa_referencia_2_valor']); ?>">
            <div class="ppl-steps">
                <span><?php esc_html_e('Cart', 'custom-payment-gateway'); ?></span>
                <span class="active"><?php esc_html_e('Payment', 'custom-payment-gateway'); ?></span>
                <span><?php esc_html_e('Confirmation', 'custom-payment-gateway'); ?></span>
            </div>

            <form class="ppl-form" id="ppl-checkout-form" enctype="multipart/form-data">
            <?php wp_nonce_field('ppl_checkout', 'ppl_nonce'); ?>
            <!-- Honeypot: legitimate users never fill this hidden field; bots often do. -->
            <div class="ppl-honeypot" aria-hidden="true">
                <label>Leave this field empty<input type="text" name="ppl_hp_check" value="" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="ppl-grid">

                <div class="ppl-col-form">
                    <div class="ppl-card">
                        <h3><?php esc_html_e('Buyer type', 'custom-payment-gateway'); ?></h3>
                        <div class="ppl-field">
                            <div class="ppl-radio-grid">
                                <label><input type="radio" name="ppl_tipo_comprador" value="natural" checked> <?php esc_html_e('Individual', 'custom-payment-gateway'); ?></label>
                                <label><input type="radio" name="ppl_tipo_comprador" value="empresa"> <?php esc_html_e('Company / Business', 'custom-payment-gateway'); ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="ppl-card ppl-natural-fields">
                        <h3><?php esc_html_e('Contact details', 'custom-payment-gateway'); ?></h3>
                        <div class="ppl-field-grid">
                            <div class="ppl-field ppl-field-full">
                                <label><?php esc_html_e('Full name', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_nombre">
                            </div>
                            <div class="ppl-field">
                                <label><?php esc_html_e('Phone', 'custom-payment-gateway'); ?></label>
                                <input type="tel" name="ppl_telefono">
                            </div>
                            <div class="ppl-field">
                                <label><?php esc_html_e('ID number', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_cedula">
                            </div>
                            <div class="ppl-field ppl-field-full">
                                <label><?php esc_html_e('Email', 'custom-payment-gateway'); ?></label>
                                <input type="email" name="ppl_email" value="<?php echo esc_attr(is_user_logged_in() ? wp_get_current_user()->user_email : ''); ?>">
                            </div>
                            <div class="ppl-field ppl-field-full">
                                <label><?php esc_html_e('Address', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_direccion">
                            </div>
                        </div>
                    </div>

                    <div class="ppl-card ppl-empresa-fields" style="display:none">
                        <h3><?php esc_html_e('Company details', 'custom-payment-gateway'); ?></h3>
                        <div class="ppl-field-grid">
                            <div class="ppl-field ppl-field-full">
                                <label><?php esc_html_e('Company name', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_razon_social">
                            </div>
                            <div class="ppl-field">
                                <label><?php esc_html_e('Tax ID', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_rif">
                            </div>
                            <div class="ppl-field">
                                <label><?php esc_html_e('Admin email', 'custom-payment-gateway'); ?></label>
                                <input type="email" name="ppl_correo_admin">
                            </div>
                            <div class="ppl-field ppl-field-full">
                                <label><?php esc_html_e('Billing address', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_direccion_fiscal">
                            </div>
                            <div class="ppl-field ppl-field-full">
                                <label><?php esc_html_e('Notes (optional)', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_empresa_personas">
                            </div>
                        </div>
                    </div>

                    <div class="ppl-card">
                        <h3><?php esc_html_e('Payment method', 'custom-payment-gateway'); ?></h3>
                        <div class="ppl-field">
                            <div class="ppl-radio-grid">
                                <?php $primero = true; ?>
                                <?php foreach ($activos as $metodo) : ?>
                                    <label>
                                        <input type="radio" name="ppl_metodo" value="<?php echo esc_attr($metodo); ?>" <?php checked($primero); ?>>
                                        <?php echo $this->icono_html($metodo); ?>
                                        <?php echo esc_html($o[$metodo . '_titulo'] ?? $metodos[$metodo]); ?>
                                        <?php if ('paypal' === $metodo) : ?>
                                            <span class="ppl-badge"><?php esc_html_e('Automatic', 'custom-payment-gateway'); ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <?php $primero = false; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="ppl-fx"></div>
                        </div>

                        <?php foreach ($activos as $metodo) :
                            if ('paypal' === $metodo || 'efectivo' === $metodo) {
                                continue;
                            }
                            ?>
                            <div class="ppl-method-panel" data-method="<?php echo esc_attr($metodo); ?>">
                                <strong><?php echo esc_html($o[$metodo . '_titulo'] ?? ''); ?></strong>
                                <?php echo esc_html($o[$metodo . '_datos'] ?? ''); ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (in_array('paypal', $activos, true)) : ?>
                            <div class="ppl-method-panel" data-method="paypal"><?php esc_html_e('You will be redirected to the secure PayPal payment page after confirming your order.', 'custom-payment-gateway'); ?></div>
                        <?php endif; ?>

                        <?php if (in_array('efectivo', $activos, true)) : ?>
                            <div class="ppl-method-panel" data-method="efectivo"><?php esc_html_e('You can pay in cash at one of our locations. Your order will be reserved.', 'custom-payment-gateway'); ?></div>
                        <?php endif; ?>

                        <?php if (in_array('efectivo', $activos, true) && !empty($oficinas)) : ?>
                            <div class="ppl-oficina-field ppl-field" style="display:none">
                                <label><?php esc_html_e('Select location', 'custom-payment-gateway'); ?></label>
                                <select name="ppl_oficina">
                                    <?php foreach ($oficinas as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="ppl-manual-fields">
                            <div class="ppl-field">
                                <label><?php esc_html_e('Name on the payment', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_titular">
                            </div>
                            <div class="ppl-field">
                                <label><?php esc_html_e('Phone or account used', 'custom-payment-gateway'); ?></label>
                                <input type="text" name="ppl_cuenta_origen">
                            </div>
                            <div class="ppl-field">
                                <label><?php esc_html_e('Payment date', 'custom-payment-gateway'); ?></label>
                                <input type="date" name="ppl_fecha_pago">
                            </div>
                            <div class="ppl-field">
                                <label><?php esc_html_e('Proof of payment (image or PDF)', 'custom-payment-gateway'); ?></label>
                                <div class="ppl-file" onclick="this.querySelector('input').click()">
                                    📎 <?php esc_html_e('Tap to attach your proof of payment', 'custom-payment-gateway'); ?>
                                    <input type="file" name="ppl_comprobante" accept=".jpg,.jpeg,.png,.pdf" style="display:none" onchange="this.parentNode.firstChild.textContent='✅ '+this.files[0].name">
                                </div>
                            </div>
                        </div>

                        <div class="ppl-field ppl-field-notes">
                            <label><?php esc_html_e('Additional notes (optional)', 'custom-payment-gateway'); ?></label>
                            <textarea name="ppl_notas"></textarea>
                        </div>
                    </div>
                </div>

                <div class="ppl-col-summary">
                    <div id="ppl-resumen-wrap">
                        <?php $this->render_resumen(); ?>
                    </div>
                    <div id="ppl-form-msg"></div>
                    <button type="submit" form="ppl-checkout-form" class="ppl-btn"><?php esc_html_e('Confirm order', 'custom-payment-gateway'); ?></button>
                </div>

            </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------
    // Shortcode: [pasarela_confirmacion]
    // ---------------------------------------------------------
    public function shortcode_confirmacion($atts) {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $order_id = isset($_GET['pedido']) ? absint($_GET['pedido']) : 0;
        $key      = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $order    = $order_id ? wc_get_order($order_id) : false;

        // Timing-safe comparison, and never trusts the order ID alone —
        // the key must match, same protection WooCommerce's own
        // "order received" page uses.
        if (!$order || !hash_equals((string) $order->get_order_key(), $key)) {
            ob_start();
            ?>
            <div class="ppl-wrap ppl-loaded">
                <div class="ppl-empty">
                    <h2><?php esc_html_e('We could not find that order', 'custom-payment-gateway'); ?></h2>
                    <p><?php esc_html_e('Check the link or contact us if you think this is an error.', 'custom-payment-gateway'); ?></p>
                    <a class="ppl-btn ppl-btn-inline" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to home', 'custom-payment-gateway'); ?></a>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        ob_start();
        ?>
        <div class="ppl-wrap">
            <div class="ppl-steps">
                <span><?php esc_html_e('Cart', 'custom-payment-gateway'); ?></span>
                <span><?php esc_html_e('Payment', 'custom-payment-gateway'); ?></span>
                <span class="active"><?php esc_html_e('Confirmation', 'custom-payment-gateway'); ?></span>
            </div>

            <div class="ppl-msg success">
                <?php
                printf(
                    /* translators: %d: order number */
                    esc_html__('Order #%d registered! We will verify your payment and activate your access shortly. We will email you.', 'custom-payment-gateway'),
                    (int) $order->get_id()
                );
                ?>
            </div>

            <div class="ppl-card">
                <h3><?php esc_html_e('Products', 'custom-payment-gateway'); ?></h3>
                <?php foreach ($order->get_items() as $item) : ?>
                    <div class="ppl-cart-item">
                        <span class="name"><?php echo esc_html($item->get_name()); ?> x<?php echo (int) $item->get_quantity(); ?></span>
                        <span class="price"><?php echo wp_kses_post(wc_price($item->get_total() + $item->get_total_tax())); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="ppl-resumen-desglose">
                    <div class="ppl-resumen-row">
                        <span><?php esc_html_e('Subtotal', 'custom-payment-gateway'); ?></span>
                        <span><?php echo wp_kses_post(wc_price($order->get_subtotal())); ?></span>
                    </div>
                    <?php if ($order->get_total_tax() > 0) : ?>
                        <div class="ppl-resumen-row">
                            <span><?php esc_html_e('Taxes', 'custom-payment-gateway'); ?></span>
                            <span><?php echo wp_kses_post(wc_price($order->get_total_tax())); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ppl-total-bar">
                <span class="lbl"><?php esc_html_e('Total due', 'custom-payment-gateway'); ?></span>
                <span class="amt"><?php echo wp_kses_post(wc_price($order->get_total())); ?></span>
            </div>

            <div class="ppl-card">
                <h3><?php esc_html_e('Order details', 'custom-payment-gateway'); ?></h3>
                <div class="ppl-detail-list">
                    <?php foreach ($this->datos_pedido_rows($order) as $fila) :
                        if ('' === trim((string) $fila[1])) {
                            continue;
                        }
                        ?>
                        <div class="ppl-detail-row">
                            <span class="label"><?php echo esc_html($fila[0]); ?></span>
                            <span class="value"><?php echo esc_html($fila[1]); ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php $comp_id = $order->get_meta('_ppl_comprobante_id'); ?>
                    <?php if ($comp_id) : ?>
                        <div class="ppl-detail-row">
                            <span class="label"><?php esc_html_e('Proof of payment', 'custom-payment-gateway'); ?></span>
                            <span class="value"><a href="<?php echo esc_url(wp_get_attachment_url($comp_id)); ?>" target="_blank" rel="noopener"><?php esc_html_e('View proof', 'custom-payment-gateway'); ?></a></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <a class="ppl-btn ppl-btn-inline" style="text-decoration:none;text-align:center;display:block" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to home', 'custom-payment-gateway'); ?></a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Human-readable rows for the confirmation page and admin box.
     */
    public function datos_pedido_rows($order) {
        $rows = array();
        $tipo = $order->get_meta('_ppl_tipo_comprador');

        if ('empresa' === $tipo) {
            $rows[] = array(__('Buyer type', 'custom-payment-gateway'), __('Company / Business', 'custom-payment-gateway'));
            $rows[] = array(__('Company name', 'custom-payment-gateway'), $order->get_meta('_ppl_razon_social'));
            $rows[] = array(__('Tax ID', 'custom-payment-gateway'), $order->get_meta('_ppl_rif'));
            $rows[] = array(__('Billing address', 'custom-payment-gateway'), $order->get_meta('_ppl_direccion_fiscal'));
            $rows[] = array(__('Admin email', 'custom-payment-gateway'), $order->get_meta('_ppl_correo_admin'));
            $rows[] = array(__('Notes', 'custom-payment-gateway'), $order->get_meta('_ppl_empresa_personas'));
        } else {
            $rows[] = array(__('Buyer type', 'custom-payment-gateway'), __('Individual', 'custom-payment-gateway'));
            $rows[] = array(__('Full name', 'custom-payment-gateway'), trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
            $rows[] = array(__('ID number', 'custom-payment-gateway'), $order->get_meta('_ppl_cedula'));
            $rows[] = array(__('Email', 'custom-payment-gateway'), $order->get_billing_email());
            $rows[] = array(__('Phone', 'custom-payment-gateway'), $order->get_billing_phone());
            $rows[] = array(__('Address', 'custom-payment-gateway'), $order->get_meta('_ppl_direccion'));
        }

        $metodo = $order->get_meta('_ppl_metodo_pago');
        $rows[] = array(__('Payment method', 'custom-payment-gateway'), $this->metodo_label($metodo));

        if ('efectivo' === $metodo) {
            $oficina_key = $order->get_meta('_ppl_oficina');
            $oficinas    = PPL_Opciones::instancia()->obtener_oficinas();
            $rows[]      = array(__('Location', 'custom-payment-gateway'), $oficinas[$oficina_key] ?? $oficina_key);
        } elseif ($metodo) {
            $rows[] = array(__('Payer name', 'custom-payment-gateway'), $order->get_meta('_ppl_titular'));
            $rows[] = array(__('Source account/phone', 'custom-payment-gateway'), $order->get_meta('_ppl_cuenta_origen'));
            $rows[] = array(__('Payment date', 'custom-payment-gateway'), $order->get_meta('_ppl_fecha_pago'));
        }

        if ($order->get_meta('_ppl_notas')) {
            $rows[] = array(__('Notes', 'custom-payment-gateway'), $order->get_meta('_ppl_notas'));
        }

        return $rows;
    }

    public function metodo_label($key) {
        $o = PPL_Opciones::instancia()->obtener_opciones();
        if (isset($o[$key . '_titulo']) && $o[$key . '_titulo'] !== '') {
            return $o[$key . '_titulo'];
        }
        $metodos = PPL_Opciones::metodos_disponibles();
        return $metodos[$key] ?? $key;
    }

    public function url_confirmacion($order) {
        $o    = PPL_Opciones::instancia()->obtener_opciones();
        $base = $o['pagina_confirmacion_id'] ? get_permalink($o['pagina_confirmacion_id']) : home_url('/confirmacion/');

        return add_query_arg(
            array(
                'pedido' => $order->get_id(),
                'key'    => $order->get_order_key(),
            ),
            $base
        );
    }

    // ---------------------------------------------------------
    // Manual "gateway" registration (hidden from native checkout,
    // used only so WooCommerce accepts 'ppl_manual' as a valid
    // payment method on orders created by this plugin). The actual
    // class is defined in class-ppl-gateway-manual.php, loaded only
    // once WC_Payment_Gateway exists.
    // ---------------------------------------------------------
    public function registrar_gateway_manual($gateways) {
        if (class_exists('WC_Payment_Gateway')) {
            require_once PPL_PLUGIN_DIR . 'includes/class-ppl-gateway-manual.php';
            $gateways[] = 'PPL_Gateway_Manual';
        }
        return $gateways;
    }
}
