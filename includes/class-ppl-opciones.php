<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PPL_Opciones
 * ------------
 * All configuration for the payment gateway lives here instead of
 * being hardcoded in the plugin file: which payment methods are
 * enabled, their account/instructions text, colors, and the PayPal
 * gateway ID to reuse. Nothing here is business-specific by default.
 */
class PPL_Opciones {

    private static $instancia = null;
    const OPTION_KEY = 'ppl_opciones';

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'registrar_pagina'));
        add_action('admin_init', array($this, 'registrar_ajustes'));
    }

    /**
     * Fixed catalog of supported manual method "slots". The admin can
     * enable/disable each one and edit its label/instructions/icon,
     * but the slots themselves (and their meaning to the checkout
     * logic) are fixed, since each has slightly different fields
     * (e.g. "efectivo" shows an office picker instead of a proof of
     * payment upload).
     */
    public static function metodos_disponibles() {
        return array(
            'paypal'           => __('PayPal', 'custom-payment-gateway'),
            'zelle'            => __('Zelle', 'custom-payment-gateway'),
            'binance_pay'      => __('Binance Pay', 'custom-payment-gateway'),
            'transferencia_bs' => __('Bank transfer', 'custom-payment-gateway'),
            'pago_movil'       => __('Mobile payment', 'custom-payment-gateway'),
            'bancolombia'      => __('Bancolombia', 'custom-payment-gateway'),
            'efectivo'         => __('Cash on site', 'custom-payment-gateway'),
        );
    }

    public static function defaults() {
        return array(
            // Which methods are active (all off by default except PayPal,
            // since PayPal is the only one that doesn't need the admin to
            // fill in any account details first).
            'metodos_activos' => array('paypal'),

            // PayPal gateway ID already configured in WooCommerce.
            'paypal_gateway_id' => 'ppcp-gateway',

            // Free-text account/instructions per manual method. Empty by
            // default — the admin fills these in with their own data.
            'zelle_titulo'            => 'Zelle (USD)',
            'zelle_datos'             => '',
            'binance_pay_titulo'      => 'Binance (USDT)',
            'binance_pay_datos'       => '',
            'transferencia_bs_titulo' => 'Bank transfer',
            'transferencia_bs_datos'  => '',
            'pago_movil_titulo'       => 'Mobile payment',
            'pago_movil_datos'        => '',
            'bancolombia_titulo'      => 'Bancolombia',
            'bancolombia_datos'       => '',

            // Cash offices, one per line as "key|Label - address".
            'oficinas_texto' => '',

            // Optional reference exchange rates (informational only,
            // charges always happen in the store's base currency).
            'tasa_referencia_1_nombre' => '',
            'tasa_referencia_1_valor'  => 0,
            'tasa_referencia_2_nombre' => '',
            'tasa_referencia_2_valor'  => 0,
            'tasa_bcv_funcion'         => '', // optional: name of a function that returns a live rate, e.g. from another plugin

            // Branding / style.
            'color_primario'      => '#0B2647',
            'color_primario_hover' => '#12325C',
            'color_secundario'    => '#067EB1',
            'color_acento'        => '#D4A72C',
            'color_fondo_resalte' => '#EAF4F9',
            'color_fondo_input'   => '#F8FAFC',
            'color_texto'         => '#0F172A',
            'color_peligro'       => '#DD1C0D',
            'radio_borde'         => 18,
            'familia_fuente'      => "'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",

            // Pages (populated automatically on activation; editable).
            'pagina_carrito_id'      => 0,
            'pagina_checkout_id'     => 0,
            'pagina_confirmacion_id' => 0,
        );
    }

    public function obtener_opciones() {
        $guardadas = get_option(self::OPTION_KEY, array());
        return wp_parse_args($guardadas, self::defaults());
    }

    public function metodo_activo($metodo) {
        $o = $this->obtener_opciones();
        return in_array($metodo, (array) $o['metodos_activos'], true);
    }

    /**
     * Parses the "oficinas_texto" free-text field into an associative
     * array (key => label), for use in the office <select>.
     */
    public function obtener_oficinas() {
        $o = $this->obtener_opciones();
        $lineas = array_filter(array_map('trim', explode("\n", (string) $o['oficinas_texto'])));
        $oficinas = array();

        foreach ($lineas as $i => $linea) {
            if (strpos($linea, '|') !== false) {
                list($key, $label) = array_map('trim', explode('|', $linea, 2));
            } else {
                $key   = 'oficina_' . ($i + 1);
                $label = $linea;
            }
            $oficinas[sanitize_key($key)] = $label;
        }

        return $oficinas;
    }

    // ---------------------------------------------------------
    // Settings page
    // ---------------------------------------------------------
    public function registrar_pagina() {
        add_menu_page(
            __('Payment Gateway', 'custom-payment-gateway'),
            __('Payment Gateway', 'custom-payment-gateway'),
            'manage_woocommerce',
            'ppl-ajustes',
            array($this, 'render_pagina'),
            'dashicons-cart'
        );
    }

    public function registrar_ajustes() {
        register_setting('ppl_grupo_opciones', self::OPTION_KEY, array(
            'sanitize_callback' => array($this, 'sanitizar_opciones'),
        ));
    }

    public function sanitizar_opciones($input) {
        $defaults = self::defaults();
        $limpio   = self::instancia()->obtener_opciones(); // start from current, so unrelated fields (like page IDs) aren't wiped

        // Payment methods (checkboxes — array or absent).
        $metodos_validos = array_keys(self::metodos_disponibles());
        $limpio['metodos_activos'] = isset($input['metodos_activos']) && is_array($input['metodos_activos'])
            ? array_values(array_intersect($metodos_validos, $input['metodos_activos']))
            : array();

        $campos_texto = array(
            'paypal_gateway_id',
            'zelle_titulo', 'zelle_datos',
            'binance_pay_titulo', 'binance_pay_datos',
            'transferencia_bs_titulo', 'transferencia_bs_datos',
            'pago_movil_titulo', 'pago_movil_datos',
            'bancolombia_titulo', 'bancolombia_datos',
            'tasa_referencia_1_nombre', 'tasa_referencia_2_nombre',
            'tasa_bcv_funcion', 'familia_fuente',
        );
        foreach ($campos_texto as $campo) {
            if (isset($input[$campo])) {
                $limpio[$campo] = sanitize_text_field($input[$campo]);
            }
        }

        // Multi-line free text fields (account instructions, offices)
        // need sanitize_textarea_field to preserve line breaks.
        $campos_textarea = array('zelle_datos', 'binance_pay_datos', 'transferencia_bs_datos', 'pago_movil_datos', 'bancolombia_datos', 'oficinas_texto');
        foreach ($campos_textarea as $campo) {
            if (isset($input[$campo])) {
                $limpio[$campo] = sanitize_textarea_field($input[$campo]);
            }
        }

        $campos_numero = array('tasa_referencia_1_valor', 'tasa_referencia_2_valor', 'radio_borde');
        foreach ($campos_numero as $campo) {
            if (isset($input[$campo])) {
                $limpio[$campo] = is_numeric($input[$campo]) ? floatval($input[$campo]) : $defaults[$campo];
            }
        }

        $campos_color = array('color_primario', 'color_primario_hover', 'color_secundario', 'color_acento', 'color_fondo_resalte', 'color_fondo_input', 'color_texto', 'color_peligro');
        foreach ($campos_color as $campo) {
            if (isset($input[$campo])) {
                $limpio[$campo] = sanitize_hex_color($input[$campo]) ? $input[$campo] : $defaults[$campo];
            }
        }

        return $limpio;
    }

    public function render_pagina() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $o       = $this->obtener_opciones();
        $metodos = self::metodos_disponibles();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Custom Payment Gateway — Settings', 'custom-payment-gateway'); ?></h1>
            <p>
                <?php esc_html_e('Use the shortcodes [pasarela_carrito], [pasarela_pago] and [pasarela_confirmacion], or let this plugin manage the pages automatically (see the Pages section at the bottom).', 'custom-payment-gateway'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('ppl_grupo_opciones'); ?>

                <h2 class="title"><?php esc_html_e('Payment methods', 'custom-payment-gateway'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enabled methods', 'custom-payment-gateway'); ?></th>
                        <td>
                            <?php foreach ($metodos as $key => $label) : ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr($this->nombre_campo('metodos_activos')); ?>[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, (array) $o['metodos_activos'], true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('PayPal reuses the gateway you already have configured in WooCommerce → Settings → Payments — this plugin never handles card data directly.', 'custom-payment-gateway'); ?></p>
                        </td>
                    </tr>
                    <?php $this->fila_texto('paypal_gateway_id', __('PayPal gateway ID', 'custom-payment-gateway'), $o, __('e.g. ppcp-gateway (WooCommerce PayPal Payments) or paypal (legacy PayPal Standard).', 'custom-payment-gateway')); ?>
                </table>

                <h2 class="title"><?php esc_html_e('Manual method details', 'custom-payment-gateway'); ?></h2>
                <p class="description"><?php esc_html_e('Fill in only the methods you enabled above. This text is shown to the customer exactly as written.', 'custom-payment-gateway'); ?></p>
                <table class="form-table" role="presentation">
                    <?php $this->fila_texto('zelle_titulo', __('Zelle — title', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_textarea('zelle_datos', __('Zelle — instructions', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_texto('binance_pay_titulo', __('Binance Pay — title', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_textarea('binance_pay_datos', __('Binance Pay — instructions', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_texto('transferencia_bs_titulo', __('Bank transfer — title', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_textarea('transferencia_bs_datos', __('Bank transfer — instructions', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_texto('pago_movil_titulo', __('Mobile payment — title', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_textarea('pago_movil_datos', __('Mobile payment — instructions', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_texto('bancolombia_titulo', __('Bancolombia — title', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_textarea('bancolombia_datos', __('Bancolombia — instructions', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_textarea('oficinas_texto', __('Cash offices (one per line: key|Label - address)', 'custom-payment-gateway'), $o, __('Example: office1|Main Office - 123 Main St', 'custom-payment-gateway')); ?>
                </table>

                <h2 class="title"><?php esc_html_e('Reference exchange rates (optional, informational only)', 'custom-payment-gateway'); ?></h2>
                <p class="description"><?php esc_html_e('Charges always happen in your store\'s base currency. These are only shown to the customer as an approximate reference next to bank transfer / mobile payment methods.', 'custom-payment-gateway'); ?></p>
                <table class="form-table" role="presentation">
                    <?php $this->fila_texto('tasa_referencia_1_nombre', __('Reference #1 — currency name', 'custom-payment-gateway'), $o, __('e.g. Bs', 'custom-payment-gateway')); ?>
                    <?php $this->fila_numero('tasa_referencia_1_valor', __('Reference #1 — rate (1 store currency =)', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_texto('tasa_referencia_2_nombre', __('Reference #2 — currency name', 'custom-payment-gateway'), $o, __('e.g. COP', 'custom-payment-gateway')); ?>
                    <?php $this->fila_numero('tasa_referencia_2_valor', __('Reference #2 — rate (1 store currency =)', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_texto('tasa_bcv_funcion', __('Live rate function name (optional)', 'custom-payment-gateway'), $o, __('If another plugin on your site defines a PHP function that returns a live exchange rate, enter its name here to use it instead of reference #1 (falls back to reference #1 automatically if the function is missing).', 'custom-payment-gateway')); ?>
                </table>

                <h2 class="title"><?php esc_html_e('Style & branding', 'custom-payment-gateway'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php $this->fila_color('color_primario', __('Primary color (buttons, headings)', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_color('color_primario_hover', __('Primary color (hover)', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_color('color_secundario', __('Secondary color (steps, focus)', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_color('color_acento', __('Accent color (button text/icons)', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_color('color_fondo_resalte', __('Highlight background', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_color('color_fondo_input', __('Input background', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_color('color_texto', __('Text color', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_color('color_peligro', __('Danger color (errors, remove item)', 'custom-payment-gateway'), $o); ?>
                    <?php $this->fila_numero('radio_borde', __('Border radius (px)', 'custom-payment-gateway'), $o, 0, 40); ?>
                    <?php $this->fila_texto('familia_fuente', __('Font family (CSS font-family)', 'custom-payment-gateway'), $o); ?>
                </table>

                <?php submit_button(__('Save changes', 'custom-payment-gateway')); ?>
            </form>

            <hr>
            <h2 class="title"><?php esc_html_e('Pages', 'custom-payment-gateway'); ?></h2>
            <?php PPL_Paginas::instancia()->render_seccion_paginas(); ?>
        </div>
        <?php
    }

    private function nombre_campo($clave) {
        return self::OPTION_KEY . '[' . $clave . ']';
    }

    private function fila_color($clave, $label, $o) {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($clave); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input type="color" id="<?php echo esc_attr($clave); ?>" name="<?php echo esc_attr($this->nombre_campo($clave)); ?>" value="<?php echo esc_attr($o[$clave]); ?>"></td>
        </tr>
        <?php
    }

    private function fila_numero($clave, $label, $o, $min = 0, $max = 999999) {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($clave); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input type="number" step="any" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" id="<?php echo esc_attr($clave); ?>" name="<?php echo esc_attr($this->nombre_campo($clave)); ?>" value="<?php echo esc_attr($o[$clave]); ?>" class="small-text"></td>
        </tr>
        <?php
    }

    private function fila_texto($clave, $label, $o, $desc = '') {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($clave); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input type="text" id="<?php echo esc_attr($clave); ?>" name="<?php echo esc_attr($this->nombre_campo($clave)); ?>" value="<?php echo esc_attr($o[$clave]); ?>" class="regular-text">
                <?php if ($desc) : ?><p class="description"><?php echo esc_html($desc); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function fila_textarea($clave, $label, $o, $desc = '') {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($clave); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <textarea id="<?php echo esc_attr($clave); ?>" name="<?php echo esc_attr($this->nombre_campo($clave)); ?>" rows="3" class="large-text"><?php echo esc_textarea($o[$clave]); ?></textarea>
                <?php if ($desc) : ?><p class="description"><?php echo esc_html($desc); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }
}
