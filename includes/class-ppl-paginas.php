<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PPL_Paginas
 * -----------
 * Creates the three pages the gateway needs (Cart, Checkout,
 * Confirmation) on plugin activation if they don't exist yet, and
 * lets the admin re-sync their content (overwrite with the plugin's
 * shortcode) at any time from the settings page.
 */
class PPL_Paginas {

    private static $instancia = null;

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action('admin_post_ppl_sincronizar_paginas', array($this, 'handle_sincronizar_paginas'));
    }

    /**
     * Definition of the three required pages: option key that stores
     * the page ID, default title/slug, and the shortcode it must
     * contain.
     */
    public static function definicion_paginas() {
        return array(
            'pagina_carrito_id' => array(
                'titulo'    => __('Cart', 'custom-payment-gateway'),
                'slug'      => 'carrito',
                'shortcode' => '[pasarela_carrito]',
            ),
            'pagina_checkout_id' => array(
                'titulo'    => __('Checkout', 'custom-payment-gateway'),
                'slug'      => 'finalizar-compra',
                'shortcode' => '[pasarela_pago]',
            ),
            'pagina_confirmacion_id' => array(
                'titulo'    => __('Order Confirmation', 'custom-payment-gateway'),
                'slug'      => 'confirmacion',
                'shortcode' => '[pasarela_confirmacion]',
            ),
        );
    }

    /**
     * Runs on plugin activation. Creates each page only if the stored
     * ID is missing/invalid AND no page with the same slug already
     * exists (in which case it adopts that existing page instead of
     * creating a duplicate).
     */
    public static function crear_paginas_en_activacion() {
        $opciones_instancia = PPL_Opciones::instancia();
        $o = $opciones_instancia->obtener_opciones();

        foreach (self::definicion_paginas() as $option_key => $def) {
            $id_actual = isset($o[$option_key]) ? (int) $o[$option_key] : 0;

            if ($id_actual && get_post($id_actual)) {
                continue; // Already set and the page still exists.
            }

            $existente = get_page_by_path($def['slug']);
            if ($existente) {
                $o[$option_key] = $existente->ID;
                continue;
            }

            $nuevo_id = wp_insert_post(array(
                'post_title'   => $def['titulo'],
                'post_name'    => $def['slug'],
                'post_content' => $def['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ));

            if (!is_wp_error($nuevo_id) && $nuevo_id) {
                $o[$option_key] = $nuevo_id;
            }
        }

        update_option(PPL_Opciones::OPTION_KEY, $o);
    }

    /**
     * Overwrites the content of the three pages with the current
     * shortcodes — used when the admin explicitly asks to "re-sync"
     * pages that already existed with different content.
     */
    public function sincronizar_paginas($forzar_sobrescribir = false) {
        $o = PPL_Opciones::instancia()->obtener_opciones();
        $resultado = array();

        foreach (self::definicion_paginas() as $option_key => $def) {
            $id = isset($o[$option_key]) ? (int) $o[$option_key] : 0;
            $post = $id ? get_post($id) : null;

            if ($post) {
                if ($forzar_sobrescribir) {
                    wp_update_post(array(
                        'ID'           => $id,
                        'post_content' => $def['shortcode'],
                        'post_status'  => 'publish',
                    ));
                }
                $resultado[$option_key] = $id;
                continue;
            }

            $existente = get_page_by_path($def['slug']);
            if ($existente) {
                if ($forzar_sobrescribir) {
                    wp_update_post(array(
                        'ID'           => $existente->ID,
                        'post_content' => $def['shortcode'],
                        'post_status'  => 'publish',
                    ));
                }
                $resultado[$option_key] = $existente->ID;
                continue;
            }

            $nuevo_id = wp_insert_post(array(
                'post_title'   => $def['titulo'],
                'post_name'    => $def['slug'],
                'post_content' => $def['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ));

            $resultado[$option_key] = is_wp_error($nuevo_id) ? 0 : $nuevo_id;
        }

        $o = wp_parse_args($resultado, $o);
        update_option(PPL_Opciones::OPTION_KEY, $o);

        return $resultado;
    }

    public function handle_sincronizar_paginas() {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('ppl_sincronizar_paginas')) {
            wp_die(esc_html__('You are not allowed to do this.', 'custom-payment-gateway'));
        }

        $forzar = !empty($_POST['ppl_sobrescribir']);
        $this->sincronizar_paginas($forzar);

        wp_safe_redirect(add_query_arg('ppl_sync', '1', wp_get_referer() ?: admin_url('admin.php?page=ppl-ajustes')));
        exit;
    }

    public function render_seccion_paginas() {
        $o = PPL_Opciones::instancia()->obtener_opciones();

        if (isset($_GET['ppl_sync'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Pages synced.', 'custom-payment-gateway') . '</p></div>';
        }
        ?>
        <table class="widefat" style="max-width:700px;margin-bottom:16px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Page', 'custom-payment-gateway'); ?></th>
                    <th><?php esc_html_e('Status', 'custom-payment-gateway'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (self::definicion_paginas() as $option_key => $def) :
                    $id   = isset($o[$option_key]) ? (int) $o[$option_key] : 0;
                    $post = $id ? get_post($id) : null;
                    ?>
                    <tr>
                        <td><?php echo esc_html($def['titulo']); ?> (<code><?php echo esc_html($def['shortcode']); ?></code>)</td>
                        <td>
                            <?php if ($post) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($id)); ?>"><?php echo esc_html(get_the_title($id)); ?></a>
                                &mdash;
                                <a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank"><?php esc_html_e('View', 'custom-payment-gateway'); ?></a>
                            <?php else : ?>
                                <span style="color:#a00;"><?php esc_html_e('Not created yet', 'custom-payment-gateway'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ppl_sincronizar_paginas'); ?>
            <input type="hidden" name="action" value="ppl_sincronizar_paginas">
            <p>
                <label>
                    <input type="checkbox" name="ppl_sobrescribir" value="1">
                    <?php esc_html_e('Overwrite the content of existing pages with the plugin shortcodes (use this if you edited them and want to reset to the plugin design).', 'custom-payment-gateway'); ?>
                </label>
            </p>
            <?php submit_button(__('Create missing pages / Sync', 'custom-payment-gateway'), 'secondary', 'submit', false); ?>
        </form>
        <?php
    }
}
