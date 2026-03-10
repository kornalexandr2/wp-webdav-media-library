(function($, _) {
	if (typeof wp === 'undefined' || !wp.media) {
		return;
	}

	var l10n = window.wwml_media || {};

	// Controller State
	wp.media.controller.WebDav = wp.media.controller.State.extend({
		defaults: {
			id: 'wwml-webdav',
			menu: 'default',
			router: 'browse',
			content: 'wwml-webdav-content',
			title: l10n.tab_title
		}
	});

	// View
	var WebDavView = wp.media.View.extend({
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
				self.$el.html('<div class="error"><p>' + (err || l10n.error) + '</p></div>');
			});
		},

		renderDirectory: function(data) {
			var html = '';
			
			// Breadcrumbs
			html += '<div class="wwml-breadcrumbs">';
			var parts = this.currentPath.split('/').filter(Boolean);
			html += '<a class="wwml-breadcrumb" data-path="/">WebDAV Home</a>';
			var buildPath = '/';
			for(var i = 0; i < parts.length; i++) {
				buildPath += parts[i] + '/';
				html += ' &raquo; <a class="wwml-breadcrumb" data-path="' + buildPath + '">' + _.escape(parts[i]) + '</a>';
			}
			html += '</div>';

			html += '<div class="wwml-grid">';

			// Parent dir
			if (this.currentPath !== '/') {
				var parentPath = this.currentPath.replace(/([^\/]+)\/$/, '');
				html += '<div class="wwml-item wwml-folder" data-path="' + _.escape(parentPath) + '">';
				html += '<span class="dashicons dashicons-arrow-left-alt2 wwml-icon folder"></span>';
				html += '<span class="wwml-filename">..</span>';
				html += '</div>';
			}

			// Folders
			_.each(data.dirs, function(dir) {
				var targetPath = this.currentPath + dir.name + '/';
				if (this.currentPath === '/') { targetPath = '/' + dir.name + '/'; }

				html += '<div class="wwml-item wwml-folder" data-path="' + _.escape(targetPath) + '">';
				html += '<span class="dashicons dashicons-category wwml-icon folder"></span>';
				html += '<span class="wwml-filename">' + _.escape(dir.name) + '</span>';
				html += '</div>';
			}, this);

			// Files
			_.each(data.files, function(file) {
				html += '<div class="wwml-item wwml-file" data-url="' + _.escape(file.url) + '">';
				if (file.mime_type.indexOf('image/') === 0) {
					html += '<img src="' + l10n.ajaxurl + '?action=wwml_preview&file=' + encodeURIComponent(file.url) + '" />';
				} else {
					html += '<span class="dashicons dashicons-media-default wwml-icon"></span>';
				}
				html += '<span class="wwml-filename">' + _.escape(file.name) + '</span>';
				html += '</div>';
			}, this);

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
			$item.addClass('is-importing').append('<span class="spinner is-active" style="position:absolute;top:5px;right:5px;"></span>');

			wp.ajax.post('wwml_import_file', {
				nonce: l10n.nonce,
				file_url: url
			}).done(function(attachmentData) {
				// We received WP Attachment JSON.
				var attachment = wp.media.model.Attachment.create(attachmentData);
				
				// Force addition to the global library collection
				if (wp.media.query()) {
					wp.media.query().add(attachment);
				}

				// Switch to Library tab and select the new item
				var frame = self.controller;
				frame.setState('library');
				
				// Small delay to let the grid render
				setTimeout(function() {
					var selection = frame.state().get('selection');
					selection.reset([attachment]);
				}, 100);

			}).fail(function(err) {
				$item.removeClass('is-importing').find('.spinner').remove();
				alert(err || l10n.error);
			});
		}
	});

	// Hook into the Media Frame
	var oldMediaFrame = wp.media.view.MediaFrame.Post;
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

			this.on('router:render:browse', this.wwmlRenderRouter, this);
			
			// Add state
			this.states.add([
				new wp.media.controller.WebDav()
			]);

			this.on('content:render:wwml-webdav-content', this.wwmlRenderContent, this);
		},

		wwmlRenderRouter: function(routerView) {
			// Ensures the router button works
		},

		wwmlRenderContent: function() {
			var view = new WebDavView({
				controller: this,
				model: this.state()
			});
			this.content.set(view);
		}
	});

	// Also patch Select frame for generic media pickers (like featured image)
	var oldSelectFrame = wp.media.view.MediaFrame.Select;
	if (oldSelectFrame) {
		wp.media.view.MediaFrame.Select = oldSelectFrame.extend({
			bindHandlers: function() {
				oldSelectFrame.prototype.bindHandlers.apply(this, arguments);
				
				this.states.add([
					new wp.media.controller.WebDav()
				]);
				this.on('content:render:wwml-webdav-content', this.wwmlRenderContent, this);
			},
			browseRouter: function(routerView) {
				oldSelectFrame.prototype.browseRouter.apply(this, arguments);
				routerView.set({
					'wwml-webdav': {
						text: l10n.tab_title,
						priority: 50
					}
				});
			},
			wwmlRenderContent: function() {
				var view = new WebDavView({
					controller: this,
					model: this.state()
				});
				this.content.set(view);
			}
		});
	}

})(jQuery, _);
