(function ($) {
    'use strict';

    function togglePanels(scope) {
        scope.find('.ppl-method-panel').removeClass('show');
        var val = scope.find('input[name=ppl_metodo]:checked').val();
        scope.find('.ppl-radio-grid label').removeClass('checked');
        scope.find('input[name=ppl_metodo]:checked').closest('label').addClass('checked');

        if (val) {
            scope.find('.ppl-method-panel[data-method="' + val + '"]').addClass('show');
            var manual = (val !== 'paypal');
            scope.find('.ppl-manual-fields').toggle(manual && val !== 'efectivo');
            scope.find('.ppl-oficina-field').toggle(val === 'efectivo');

            var tasa1 = parseFloat(scope.data('tasa1')) || (window.pplData && pplData.tasaFallback1) || 0;
            var tasa2 = parseFloat(scope.data('tasa2')) || (window.pplData && pplData.tasaFallback2) || 0;
            var total = parseFloat(scope.data('total')) || 0;
            var nombre1 = (window.pplData && pplData.tasaNombre1) || '';
            var nombre2 = (window.pplData && pplData.tasaNombre2) || '';

            var texto = '';
            if ((val === 'transferencia_bs' || val === 'pago_movil') && tasa1 > 0 && nombre1) {
                texto = 'Ref. approx. ' + nombre1 + ' ' + (total * tasa1).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else if (val === 'bancolombia' && tasa2 > 0 && nombre2) {
                texto = 'Ref. approx. ' + nombre2 + ' ' + (total * tasa2).toLocaleString();
            }
            scope.find('.ppl-fx').text(texto);
        }
    }

    $(document).on('change', 'input[name=ppl_metodo]', function () {
        togglePanels($(this).closest('.ppl-wrap'));
    });

    $(document).on('change', 'input[name=ppl_tipo_comprador]', function () {
        var scope = $(this).closest('.ppl-wrap');
        var esEmpresa = $(this).val() === 'empresa';
        scope.find('.ppl-empresa-fields').toggle(esEmpresa);
        scope.find('.ppl-natural-fields').toggle(!esEmpresa);
    });

    $(document).on('click', '.ppl-remove-item', function (e) {
        e.preventDefault();
        var btn = $(this);
        var scope = btn.closest('.ppl-wrap');
        var nonce = scope.find('input[name=ppl_nonce]').val();
        btn.prop('disabled', true);

        $.post(pplData.ajaxUrl, {
            action: 'ppl_eliminar_item',
            cart_item_key: btn.data('key'),
            ppl_nonce: nonce
        }, function (res) {
            if (!res.success) {
                alert(res.data.message);
                btn.prop('disabled', false);
                return;
            }
            if (res.data.empty) {
                window.location.href = res.data.redirect;
                return;
            }
            $('#ppl-resumen-wrap').html(res.data.html);
            scope.attr('data-total', res.data.total).data('total', res.data.total);
            togglePanels(scope);
        }).fail(function () {
            alert('Could not update the cart. Please try again.');
            btn.prop('disabled', false);
        });
    });

    $(document).on('submit', '.ppl-form', function (e) {
        e.preventDefault();
        var form = $(this);
        var scope = form.closest('.ppl-wrap');
        var btn = form.find('.ppl-btn');
        var msgBox = scope.find('#ppl-form-msg');
        msgBox.empty();
        btn.prop('disabled', true).text('Processing...');

        var fd = new FormData(this);
        fd.append('action', 'ppl_crear_pedido');

        $.ajax({
            url: pplData.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    if (res.data.redirect) {
                        window.location.href = res.data.redirect;
                    } else {
                        msgBox.html('<div class="ppl-msg success">' + res.data.message + '</div>');
                        form[0].reset();
                        btn.prop('disabled', false).text('Confirm order');
                        msgBox[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    msgBox.html('<div class="ppl-msg error">' + res.data.message + '</div>');
                    btn.prop('disabled', false).text('Confirm order');
                    msgBox[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            },
            error: function () {
                msgBox.html('<div class="ppl-msg error">A connection error occurred. Please try again.</div>');
                btn.prop('disabled', false).text('Confirm order');
                msgBox[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });

    $(function () {
        $('.ppl-wrap').each(function () {
            togglePanels($(this));
        });
        $('.ppl-wrap').addClass('ppl-loaded');
    });
})(jQuery);
