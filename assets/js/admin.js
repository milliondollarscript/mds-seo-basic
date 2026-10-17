(function ($) {
    'use strict';

    function setStatus(message, type) {
        var $status = $('#mds-seo-sitemap-result');
        $status.removeClass('is-success is-error is-pending').addClass(type ? 'is-' + type : '').text(message || '');
    }

    $(function () {
        $('[data-mds-seo-count]').each(function () {
            var $field = $(this);
            var key = $field.data('mds-seo-count');
            var $counter = $('[data-mds-seo-counter="' + key + '"]');
            $field.on('input', function () {
                $counter.text(String($field.val()).length);
            });
        });

        $('#mds-seo-generate-sitemap').on('click', function () {
            var $button = $(this);
            $button.prop('disabled', true);
            setStatus(mdsSeoBasic.strings.checking, 'pending');

            $.post(mdsSeoBasic.ajaxUrl, {
                action: 'mds_seo_generate_sitemap',
                nonce: mdsSeoBasic.nonce
            }).done(function (response) {
                if (response && response.success) {
                    setStatus(response.data.message || '', 'success');
                    return;
                }
                setStatus((response && response.data && response.data.message) || mdsSeoBasic.strings.failed, 'error');
            }).fail(function () {
                setStatus(mdsSeoBasic.strings.failed, 'error');
            }).always(function () {
                $button.prop('disabled', false);
            });
        });

        var mediaFrame;
        $('[data-mds-seo-choose-image]').on('click', function () {
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: mdsSeoBasic.strings.chooseImage,
                button: { text: mdsSeoBasic.strings.useImage },
                library: { type: 'image' },
                multiple: false
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $('#mds_seo_og_image').val(attachment.url).trigger('change');
                $('[data-mds-seo-image-preview]').attr('src', attachment.url).prop('hidden', false);
                $('[data-mds-seo-remove-image]').prop('hidden', false);
            });
            mediaFrame.open();
        });

        $('[data-mds-seo-remove-image]').on('click', function () {
            $('#mds_seo_og_image').val('');
            $('[data-mds-seo-image-preview]').attr('src', '').prop('hidden', true);
            $(this).prop('hidden', true);
        });
    });
})(jQuery);
