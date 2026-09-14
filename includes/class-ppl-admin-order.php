<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PPL_Admin_Order
 * ----------------
 * Shows the custom gateway's payment data (and proof of payment) in
 * the WooCommerce order edit screen, so the admin can verify manual
 * payments without leaving the dashboard.
 */
class PPL_Admin_Order {

    private static $instancia = null;

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'render_admin_box'));
    }

    public function render_admin_box($order) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $metodo = $order->get_meta('_ppl_metodo_pago');
        if (!$metodo) {
            return;
        }

        $core     = PPL_Core::instancia();
        $oficinas = PPL_Opciones::instancia()->obtener_oficinas();
        ?>
        <style>
            .ppl-admin-box{margin-top:14px;max-width:640px;background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;clear:both}
            .ppl-admin-box .ppl-admin-head{background:#0B2647;color:#D4A72C;padding:10px 14px;font-weight:700;font-size:13px;display:flex;justify-content:space-between;align-items:center;gap:10px}
            .ppl-admin-box .ppl-admin-body{padding:14px}
            .ppl-admin-row{display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid #f0f0f1;font-size:13px;line-height:1.4}
            .ppl-admin-row:last-child{border-bottom:none}
            .ppl-admin-row .l{color:#646970;font-weight:600;flex:none;width:45%}
            .ppl-admin-row .v{color:#1d2327;text-align:right;flex:1;word-break:break-word}
            .ppl-admin-comprobante{margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f1;font-size:13px}
            .ppl-admin-comprobante img{max-width:100%;max-height:420px;width:auto;border-radius:6px;border:1px solid #dcdcde;display:block;margin:8px auto 0;object-fit:contain}
            .ppl-admin-comprobante iframe{width:100%;height:420px;border:1px solid #dcdcde;border-radius:6px;margin-top:8px}
            .ppl-admin-comprobante p{margin:8px 0 0}
        </style>
        <div class="ppl-admin-box">
            <div class="ppl-admin-head">
                <span>💳 <?php esc_html_e('Custom gateway payment data', 'custom-payment-gateway'); ?></span>
                <span><?php echo esc_html($core->metodo_label($metodo)); ?></span>
            </div>
            <div class="ppl-admin-body">
                <?php
                $filas = array();

                if ('empresa' === $order->get_meta('_ppl_tipo_comprador')) {
                    $filas[] = array(__('Company name', 'custom-payment-gateway'), $order->get_meta('_ppl_razon_social'));
                    $filas[] = array(__('Tax ID', 'custom-payment-gateway'), $order->get_meta('_ppl_rif'));
                    $filas[] = array(__('Billing address', 'custom-payment-gateway'), $order->get_meta('_ppl_direccion_fiscal'));
                    $filas[] = array(__('Admin email', 'custom-payment-gateway'), $order->get_meta('_ppl_correo_admin'));
                    $filas[] = array(__('Notes', 'custom-payment-gateway'), $order->get_meta('_ppl_empresa_personas'));
                } else {
                    $filas[] = array(__('ID number', 'custom-payment-gateway'), $order->get_meta('_ppl_cedula'));
                    $filas[] = array(__('Address', 'custom-payment-gateway'), $order->get_meta('_ppl_direccion'));
                }

                if ($order->get_meta('_ppl_oficina')) {
                    $oficina_key = $order->get_meta('_ppl_oficina');
                    $filas[]     = array(__('Location', 'custom-payment-gateway'), $oficinas[$oficina_key] ?? $oficina_key);
                } else {
                    $filas[] = array(__('Payer name', 'custom-payment-gateway'), $order->get_meta('_ppl_titular'));
                    $filas[] = array(__('Source account/phone', 'custom-payment-gateway'), $order->get_meta('_ppl_cuenta_origen'));
                    $filas[] = array(__('Payment date', 'custom-payment-gateway'), $order->get_meta('_ppl_fecha_pago'));
                }

                if ($order->get_meta('_ppl_notas')) {
                    $filas[] = array(__('Notes', 'custom-payment-gateway'), $order->get_meta('_ppl_notas'));
                }

                foreach ($filas as $fila) :
                    if ('' === trim((string) $fila[1])) {
                        continue;
                    }
                    ?>
                    <div class="ppl-admin-row">
                        <span class="l"><?php echo esc_html($fila[0]); ?></span>
                        <span class="v"><?php echo esc_html($fila[1]); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php
                $comp_id = $order->get_meta('_ppl_comprobante_id');
                if ($comp_id) :
                    $url  = wp_get_attachment_url($comp_id);
                    $mime = get_post_mime_type($comp_id);
                    ?>
                    <div class="ppl-admin-comprobante">
                        <strong><?php esc_html_e('Proof of payment', 'custom-payment-gateway'); ?></strong>
                        <?php if (0 === strpos((string) $mime, 'image/')) : ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e('View full size', 'custom-payment-gateway'); ?>">
                                <img src="<?php echo esc_url($url); ?>" alt="<?php esc_attr_e('Proof of payment', 'custom-payment-gateway'); ?>">
                            </a>
                        <?php elseif ('application/pdf' === $mime) : ?>
                            <iframe src="<?php echo esc_url($url); ?>" title="<?php esc_attr_e('Proof of payment (PDF)', 'custom-payment-gateway'); ?>"></iframe>
                            <p><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open PDF in a new tab ↗', 'custom-payment-gateway'); ?></a></p>
                        <?php else : ?>
                            <p><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View proof ↗', 'custom-payment-gateway'); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
