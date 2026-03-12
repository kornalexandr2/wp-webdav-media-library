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

        const $providerSelect = $('#eml_webdav_provider');
        const $serverInput = $('input[name="eml_webdav_server"]');
        const $pathInput = $('input[name="eml_webdav_path"]');

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

        // Кнопка проверки соединения (будет добавлена позже)
        $('#wwml-test-connection').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const data = {
                action: 'wwml_test_webdav_connection',
                nonce: wwml_admin.nonce,
                server: $serverInput.val(),
                login: $('input[name="eml_webdav_login"]').val(),
                password: $('input[name="eml_webdav_password"]').val(),
                path: $pathInput.val()
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
