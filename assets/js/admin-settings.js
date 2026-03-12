(function($) {
    'use strict';

    $(document).ready(function() {
        const providers = {
            'nextcloud': {
                'path': '/remote.php/dav/files/',
                'placeholder': 'https://your-nextcloud.com'
            },
            'owncloud': {
                'path': '/remote.php/dav/files/',
                'placeholder': 'https://your-owncloud.com'
            },
            'yandex': {
                'path': '/dav/',
                'placeholder': 'https://webdav.yandex.ru'
            },
            'custom': {
                'path': '',
                'placeholder': 'https://example.com'
            }
        };

        const $providerSelect = $('#wwml_provider');
        const $serverInput = $('input[name="wwml_server"]');
        const $pathInput = $('input[name="wwml_path"]');

        if ($providerSelect.length) {
            $providerSelect.on('change', function() {
                const val = $(this).val();
                if (providers[val]) {
                    if (providers[val].path !== '') {
                        $pathInput.val(providers[val].path);
                    }
                    if (providers[val].placeholder !== '') {
                        $serverInput.attr('placeholder', providers[val].placeholder);
                    }
                }
            });
        }

        // Кнопка проверки соединения
        $('#wwml-test-connection').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const data = {
                action: 'wwml_test_webdav_connection',
                nonce: wwml_admin.nonce,
                server: $('#wwml_server').val() || $('input[name="wwml_server"]').val(),
                login: $('#wwml_login').val() || $('input[name="wwml_login"]').val(),
                password: $('#wwml_password').val() || $('input[name="wwml_password"]').val(),
                path: $('#wwml_path').val() || $('input[name="wwml_path"]').val()
            };

            $btn.prop('disabled', true).text(wwml_admin.text_testing);

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    alert(wwml_admin.text_success);
                } else {
                    alert(wwml_admin.text_error + ': ' + response.data);
                }
                $btn.prop('disabled', false).text(wwml_admin.text_test_btn);
            });
        });
    });
})(jQuery);
