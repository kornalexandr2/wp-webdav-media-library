=== WP WebDav Media Library ===
Contributors: kornalexandr2, KiSa
Tags: media, library, webdav, cloud, external, yandex, nextcloud
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Независимый форк для использования WebDav в медиабиблиотеке WordPress.

== Description ==

Этот плагин является форком "External files from WebDav in Media Library". Он позволяет подключить вашу медиабиблиотеку WordPress к любому серверу WebDAV (Nextcloud, Яндекс.Диск и т. д.), чтобы использовать внешние файлы без их хранения на вашем сервере.

Основные возможности:
* Простой выбор предустановок для популярных облачных сервисов.
* Проверка соединения в реальном времени.
* Локальное кэширование миниатюр для быстрой работы.
* Поддержка пользовательских настроек WebDAV в профиле каждого пользователя.

== Installation ==

1. Загрузите файлы плагина в папку `/wp-content/plugins/wp-webdav-media-library`.
2. Активируйте плагин через экран 'Плагины' в WordPress.
3. Настройте параметры WebDAV в разделе 'Настройки' -> 'WebDAV'.

== Screenshots ==

1. Страница настроек с выбором провайдера.

== Changelog ==

= 1.0.0 =
* Первый релиз форка.
* Ребрендинг в WP WebDav Media Library.
* Изменен префикс функций на wwml_.
* Добавлена поддержка пресетов провайдеров (Nextcloud, Яндекс.Диск).
* Добавлена кнопка проверки соединения.
* Реализовано кэширование превью изображений.
