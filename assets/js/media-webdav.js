(function($, _) {
	if (typeof wp === 'undefined' || !wp.media) {
		return;
	}

	var l10n = window.wwml_media || {};

	// Controller State for the Media Modal
	wp.media.controller.WebDav = wp.media.controller.State.extend({
		defaults: {
			id: 'wwml-webdav',
			menu: 'default',
			router: 'browse',
			content: 'wwml-webdav-content',
			title: l10n.tab_title
		}
	});

	// Main Browser View (exported to window for reuse)
	window.WWML_Browser_View = wp.media.View.extend({
		className: 'wwml-browser-wrap',
		
		events: {
			'click .wwml-folder': 'openFolder',
			'click .wwml-file': 'importFile',
			'click .wwml-breadcrumb': 'navigateBreadcrumb'
		},

		initialize: function() {
			this.currentPath = '/';
			this.render();
			this.loadDirectory(this.currentPath);
		},

		render: function() {
			this.$el.html('<div class="wwml-loading">' + l10n.loading + '</div>');
			return this;
		},

		loadDirectory: function(path) {
			var self = this;
			this.$el.html('<div class="wwml-loading">' + l10n.loading + '</div>');

			wp.ajax.post('wwml_get_files', {
				nonce: l10n.nonce,
				path: path
			}).done(function(response) {
				self.currentPath = path;
				self.renderDirectory(response);
			}).fail(function(err) {
				var msg = (typeof err === 'object') ? (err.message || l10n.error) : (err || l10n.error);
				self.$el.html('<div class="error" style="padding:20px; color:#d63638;"><p><strong>Error:</strong> ' + msg + '</p></div>');
			});
		},

		renderDirectory: function(data) {
			var html = '';
			
			// Breadcrumbs
			html += '<div class="wwml-breadcrumbs" style="padding: 10px; background: #f6f7f7; border-bottom: 1px solid #dcdcde; margin-bottom: 15px;">';
			var parts = this.currentPath.split('/').filter(Boolean);
			html += '<a class="wwml-breadcrumb" data-path="/" style="text-decoration:none; font-weight:bold;">WebDAV Home</a>';
			var buildPath = '/';
			for(var i = 0; i < parts.length; i++) {
				buildPath += parts[i] + '/';
				html += ' <span style="color:#8c8f94;">&raquo;</span> <a class="wwml-breadcrumb" data-path="' + buildPath + '" style="text-decoration:none;">' + _.escape(parts[i]) + '</a>';
			}
			html += '</div>';

			html += '<div class="wwml-grid" style="padding: 0 15px 15px 15px;">';

			// Parent dir
			if (this.currentPath !== '/') {
				var parentPath = this.currentPath.replace(/([^\/]+)\/$/, '');
				if (!parentPath.startsWith('/')) parentPath = '/' + parentPath;

				html += '<div class="wwml-item wwml-folder" data-path="' + _.escape(parentPath) + '">';
				html += '<span class="dashicons dashicons-arrow-left-alt2 wwml-icon folder"></span>';
				html += '<span class="wwml-filename">..</span>';
				html += '</div>';
			}

			// Folders
			if (data.dirs && data.dirs.length) {
				_.each(data.dirs, function(dir) {
					var targetPath = this.currentPath + dir.name + '/';
					if (this.currentPath === '/') { targetPath = '/' + dir.name + '/'; }

					html += '<div class="wwml-item wwml-folder" data-path="' + _.escape(targetPath) + '">';
					html += '<span class="dashicons dashicons-category wwml-icon folder"></span>';
					html += '<span class="wwml-filename">' + _.escape(dir.name) + '</span>';
					html += '</div>';
				}, this);
			}

			// Files
			if (data.files && data.files.length) {
				_.each(data.files, function(file) {
					html += '<div class="wwml-item wwml-file" data-url="' + _.escape(file.url) + '">';
					if (file.mime_type.indexOf('image/') === 0) {
						html += '<img src="' + l10n.ajaxurl + '?action=wwml_preview&file=' + encodeURIComponent(file.url) + '" style="height:80px; width:auto; display:block; margin: 0 auto 10px;" />';
					} else if (file.mime_type.indexOf('pdf') !== -1) {
						html += '<span class="dashicons dashicons-pdf wwml-icon" style="color:#d63638;"></span>';
					} else {
						html += '<span class="dashicons dashicons-media-default wwml-icon"></span>';
					}
					html += '<span class="wwml-filename">' + _.escape(file.name) + '</span>';
					html += '</div>';
				}, this);
			}

			if ((!data.dirs || !data.dirs.length) && (!data.files || !data.files.length)) {
				html += '<div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #646970;">' + _.escape("This folder is empty") + '</div>';
			}

			html += '</div>';
			this.$el.html(html);
		},

		openFolder: function(e) {
			e.preventDefault();
			var path = $(e.currentTarget).data('path');
			this.loadDirectory(path);
		},

		navigateBreadcrumb: function(e) {
			e.preventDefault();
			var path = $(e.currentTarget).data('path');
			this.loadDirectory(path);
		},

		importFile: function(e) {
			e.preventDefault();
			var $item = $(e.currentTarget);
			var url = $item.data('url');
			var self = this;

			if ($item.hasClass('is-importing')) return;
			$item.addClass('is-importing').append('<span class="spinner is-active" style="position:absolute;top:5px;right:5px;visibility:visible;"></span>');

			wp.ajax.post('wwml_import_file', {
				nonce: l10n.nonce,
				file_url: url
			}).done(function(attachmentData) {
				// We received WP Attachment JSON.
				var attachment = wp.media.model.Attachment.create(attachmentData);
				
				// Add to library collection
				if (wp.media.query()) {
					wp.media.query().add(attachment);
				}

				// If we are in a frame (modal), handle selection and switching
				if (self.options && self.options.controller) {
					var frame = self.options.controller;
					frame.setState('library');
					
					setTimeout(function() {
						var selection = frame.state().get('selection');
						selection.reset([attachment]);
					}, 100);
				} else {
					// We are on the standalone Browser page
					$item.removeClass('is-importing').find('.spinner').remove();
					$item.css('background', '#f0fcf0').css('border-color', '#00a32a');
					alert("File imported successfully to Media Library!");
				}

			}).fail(function(err) {
				$item.removeClass('is-importing').find('.spinner').remove();
				var msg = (typeof err === 'object') ? (err.message || l10n.error) : (err || l10n.error);
				alert(msg);
			});
		}
	});

	// Integration with Media Modal
	var oldMediaFrame = wp.media.view.MediaFrame.Post;
	if (oldMediaFrame) {
		wp.media.view.MediaFrame.Post = oldMediaFrame.extend({
			browseRouter: function(routerView) {
				oldMediaFrame.prototype.browseRouter.apply(this, arguments);
				routerView.set({
					'wwml-webdav': {
						text: l10n.tab_title,
						priority: 50
					}
				});
			},

			bindHandlers: function() {
				oldMediaFrame.prototype.bindHandlers.apply(this, arguments);
				this.on('content:render:wwml-webdav-content', this.wwmlRenderContent, this);
			},

			wwmlRenderContent: function() {
				var view = new window.WWML_Browser_View({
					controller: this
				});
				this.content.set(view);
			}
		});
	}

	var oldSelectFrame = wp.media.view.MediaFrame.Select;
	if (oldSelectFrame) {
		wp.media.view.MediaFrame.Select = oldSelectFrame.extend({
			browseRouter: function(routerView) {
				oldSelectFrame.prototype.browseRouter.apply(this, arguments);
				routerView.set({
					'wwml-webdav': {
						text: l10n.tab_title,
						priority: 50
					}
				});
			},

			bindHandlers: function() {
				oldSelectFrame.prototype.bindHandlers.apply(this, arguments);
				this.on('content:render:wwml-webdav-content', this.wwmlRenderContent, this);
			},

			wwmlRenderContent: function() {
				var view = new window.WWML_Browser_View({
					controller: this
				});
				this.content.set(view);
			}
		});
	}

})(jQuery, _);
