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
                'path': '/',
                'placeholder': 'https://example.com'
            }
        };

        const $providerSelect = $('#wwml_provider');
        const $serverInput = $('#wwml_server');
        const $pathInput = $('#wwml_path');
        const $debugWrap = $('#wwml-debug-log-wrap');
        const $debugLog = $('#wwml-debug-log');

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
                provider: $providerSelect.val(),
                server: $serverInput.val(),
                login: $('#wwml_login').val(),
                password: $('#wwml_password').val(),
                path: $pathInput.val()
            };

            $btn.prop('disabled', true).text(wwml_admin.text_testing);
            $debugWrap.hide();
            $debugLog.text('');

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    alert(wwml_admin.text_success);
                } else {
                    const msg = (typeof response.data === 'object') ? response.data.message : response.data;
                    alert(wwml_admin.text_error + ': ' + msg);
                }
                
                if (response.data && response.data.debug) {
                    $debugLog.text(response.data.debug);
                    $debugWrap.show();
                } else if (typeof response === 'string' && response.includes('DEBUG LOG')) {
                    // Fallback for weird responses
                    $debugLog.text(response);
                    $debugWrap.show();
                }

                $btn.prop('disabled', false).text(wwml_admin.text_test_btn);
            }).fail(function(xhr) {
                alert(wwml_admin.text_error + ' (HTTP ' + xhr.status + ')');
                if (xhr.responseText) {
                    $debugLog.text(xhr.responseText);
                    $debugWrap.show();
                }
                $btn.prop('disabled', false).text(wwml_admin.text_test_btn);
            });
        });
    });
})(jQuery);
