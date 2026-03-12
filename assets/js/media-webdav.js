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

	// Main Browser View
	window.WWML_Browser_View = wp.media.View.extend({
		className: 'wwml-browser-container',
		
		events: {
			'click .wwml-folder': 'openFolder',
			'click .wwml-file': 'selectFile',
			'click .wwml-breadcrumb': 'navigateBreadcrumb',
			'click #wwml-import-btn': 'importSelectedFile'
		},

		initialize: function(options) {
			this.options = options || {};
			this.currentPath = '/';
			this.selectedFile = null;
			this.log("View initialized. Path: " + this.currentPath);
			this.render();
			this.loadDirectory(this.currentPath);
		},

		log: function(msg) {
			if (this.options.debug_log) {
				this.options.debug_log(msg);
			}
			console.log("[WWML] " + msg);
		},

		render: function() {
			this.$el.html(
				'<div class="wwml-browser-main" style="display:flex; height:100%;">' +
					'<div class="wwml-browser-files" style="flex:1; overflow-y:auto; border-right:1px solid #dcdcde;">' +
						'<div class="wwml-loading">' + l10n.loading + '</div>' +
					'</div>' +
					'<div class="wwml-browser-sidebar" style="width:300px; padding:20px; background:#f6f7f7; overflow-y:auto;">' +
						'<div class="wwml-sidebar-empty">' + _.escape("Select a file to see details") + '</div>' +
					'</div>' +
				'</div>'
			);
			return this;
		},

		loadDirectory: function(path) {
			var self = this;
			this.log("Loading directory: " + path);
			this.selectedFile = null;
			this.updateSidebar();
			this.$('.wwml-browser-files').html('<div class="wwml-loading">' + l10n.loading + '</div>');

			wp.ajax.post('wwml_get_files', {
				nonce: l10n.nonce,
				path: path
			}).done(function(response) {
				if (response.debug) self.log(response.debug);
				self.currentPath = path;
				self.renderDirectory(response);
			}).fail(function(err) {
				var msg = (typeof err === 'object') ? (err.message || l10n.error) : (err || l10n.error);
				self.log("AJAX FAILED: " + msg);
				self.$('.wwml-browser-files').html('<div class="error" style="padding:20px; color:#d63638;"><p><strong>Error:</strong> ' + msg + '</p></div>');
			});
		},

		renderDirectory: function(data) {
			var html = '';
			
			// Breadcrumbs
			html += '<div class="wwml-breadcrumbs" style="padding: 10px; background: #f6f7f7; border-bottom: 1px solid #dcdcde; margin-bottom: 15px;">';
			var parts = this.currentPath.split('/').filter(Boolean);
			html += '<a class="wwml-breadcrumb" data-path="/" style="text-decoration:none; font-weight:bold; cursor:pointer;">WebDAV Home</a>';
			var buildPath = '/';
			for(var i = 0; i < parts.length; i++) {
				buildPath += parts[i] + '/';
				html += ' <span style="color:#8c8f94;">&raquo;</span> <a class="wwml-breadcrumb" data-path="' + buildPath + '" style="text-decoration:none; cursor:pointer;">' + _.escape(parts[i]) + '</a>';
			}
			html += '</div>';

			html += '<div class="wwml-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap:15px; padding: 0 15px 15px 15px;">';

			// Parent dir
			if (this.currentPath !== '/') {
				var parentPath = this.currentPath.replace(/([^\/]+)\/$/, '');
				if (!parentPath.startsWith('/')) parentPath = '/' + parentPath;

				html += '<div class="wwml-item wwml-folder" data-path="' + _.escape(parentPath) + '" style="border:1px solid #ddd; padding:10px; text-align:center; cursor:pointer;">';
				html += '<span class="dashicons dashicons-arrow-left-alt2" style="font-size:40px; height:50px; width:100%; color:#007cba;"></span>';
				html += '<span class="wwml-filename" style="font-size:12px; display:block;">..</span>';
				html += '</div>';
			}

			// Folders
			if (data.dirs && data.dirs.length) {
				_.each(data.dirs, function(dir) {
					var targetPath = this.currentPath + dir.name + '/';
					if (this.currentPath === '/') { targetPath = '/' + dir.name + '/'; }

					html += '<div class="wwml-item wwml-folder" data-path="' + _.escape(targetPath) + '" style="border:1px solid #ddd; padding:10px; text-align:center; cursor:pointer;">';
					html += '<span class="dashicons dashicons-category" style="font-size:40px; height:50px; width:100%; color:#007cba;"></span>';
					html += '<span class="wwml-filename" style="font-size:12px; display:block;">' + _.escape(dir.name) + '</span>';
					html += '</div>';
				}, this);
			}

			// Files
			if (data.files && data.files.length) {
				_.each(data.files, function(file) {
					var mime = (typeof file.mime_type === 'string') ? file.mime_type : '';
					html += '<div class="wwml-item wwml-file" data-file-json=\'' + _.escape(JSON.stringify(file)) + '\' style="border:1px solid #ddd; padding:10px; text-align:center; cursor:pointer; position:relative;">';
					if (mime.indexOf('image/') === 0) {
						html += '<img src="' + l10n.ajaxurl + '?action=wwml_preview&file=' + encodeURIComponent(file.url) + '" style="height:80px; width:auto; display:block; margin: 0 auto 10px;" />';
					} else if (mime.indexOf('pdf') !== -1) {
						html += '<span class="dashicons dashicons-pdf" style="font-size:40px; height:50px; width:100%; color:#d63638;"></span>';
					} else {
						html += '<span class="dashicons dashicons-media-default" style="font-size:40px; height:50px; width:100%; color:#8c8f94;"></span>';
					}
					html += '<span class="wwml-filename" style="font-size:12px; display:block; word-break:break-all;">' + _.escape(file.name) + '</span>';
					html += '</div>';
				}, this);
			}

			if ((!data.dirs || !data.dirs.length) && (!data.files || !data.files.length)) {
				html += '<div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #646970;">' + _.escape("This folder is empty") + '</div>';
			}

			html += '</div>';
			this.$('.wwml-browser-files').html(html);
		},

		openFolder: function(e) {
			var path = $(e.currentTarget).data('path');
			this.loadDirectory(path);
		},

		navigateBreadcrumb: function(e) {
			var path = $(e.currentTarget).data('path');
			this.loadDirectory(path);
		},

		selectFile: function(e) {
			this.$('.wwml-item').css('background', '').css('border-color', '#ddd');
			$(e.currentTarget).css('background', '#e7f7ff').css('border-color', '#007cba');
			
			this.selectedFile = $(e.currentTarget).data('file-json');
			this.updateSidebar();

			// Add debug info for preview
			if (this.selectedFile && this.selectedFile.mime_type.indexOf('image/') === 0) {
				var self = this;
				this.log("Debugging preview for: " + this.selectedFile.name);
				wp.ajax.post('wwml_preview_debug', {
					nonce: l10n.nonce,
					file_url: this.selectedFile.url
				}).done(function(res) {
					if (res.debug) self.log(res.debug);
				}).fail(function(res) {
					if (res.debug) self.log(res.debug);
				});
			}
		},

		updateSidebar: function() {
			var $sidebar = this.$('.wwml-browser-sidebar');
			if (!this.selectedFile) {
				$sidebar.html('<div class="wwml-sidebar-empty" style="text-align:center; padding-top:100px; color:#646970;">' + _.escape("Select a file to see details") + '</div>');
				return;
			}

			var file = this.selectedFile;
			var mime = file.mime_type || '';
			var html = '<h3>' + _.escape("File Details") + '</h3>';
			
			if (mime.indexOf('image/') === 0) {
				html += '<img src="' + l10n.ajaxurl + '?action=wwml_preview&file=' + encodeURIComponent(file.url) + '" style="width:100%; height:auto; margin-bottom:15px; border:1px solid #ddd;" />';
			} else {
				html += '<div style="text-align:center; padding:20px; background:#fff; margin-bottom:15px; border:1px solid #ddd;"><span class="dashicons ' + (mime.indexOf('pdf') !== -1 ? 'dashicons-pdf' : 'dashicons-media-default') + '" style="font-size:60px; height:60px; width:60px;"></span></div>';
			}

			html += '<p><strong>' + _.escape("Name:") + '</strong><br>' + _.escape(file.name) + '</p>';
			html += '<p><strong>' + _.escape("Type:") + '</strong><br>' + _.escape(mime) + '</p>';
			html += '<p><strong>' + _.escape("Size:") + '</strong><br>' + this.formatBytes(file.size) + '</p>';
			html += '<p><strong>' + _.escape("Modified:") + '</strong><br>' + _.escape(file.modified) + '</p>';
			html += '<hr>';
			html += '<button id="wwml-import-btn" class="button button-primary button-large" style="width:100%;">' + _.escape("Import to Media Library") + '</button>';
			
			$sidebar.html(html);
		},

		formatBytes: function(bytes, decimals = 2) {
			if (bytes === 0) return '0 Bytes';
			const k = 1024;
			const dm = decimals < 0 ? 0 : decimals;
			const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
		},

		importSelectedFile: function(e) {
			var self = this;
			var $btn = $(e.currentTarget);
			var file = this.selectedFile;

			if (!file || $btn.prop('disabled')) return;

			this.log("Starting import for file: " + file.url);
			$btn.prop('disabled', true).text(l10n.importing);

			wp.ajax.post('wwml_import_file', {
				nonce: l10n.nonce,
				file_url: file.url
			}).done(function(attachmentData) {
				self.log("Import success. Attachment ID: " + attachmentData.id);
				
				if (wp.media.query()) {
					wp.media.query().add(wp.media.model.Attachment.create(attachmentData));
				}

				if (self.options && self.options.controller) {
					var frame = self.options.controller;
					frame.setState('library');
					setTimeout(function() {
						var selection = frame.state().get('selection');
						selection.reset([wp.media.model.Attachment.create(attachmentData)]);
					}, 100);
				} else {
					$btn.text(_.escape("Imported!")).removeClass('button-primary').addClass('button-disabled');
					alert("File imported successfully!");
				}
			}).fail(function(err) {
				$btn.prop('disabled', false).text(_.escape("Import to Media Library"));
				var msg = (typeof err === 'object') ? (err.message || l10n.error) : (err || l10n.error);
				alert(msg);
			});
		}
	});

	// Modal Integration remains same but uses new View
	var patchFrame = function(FrameClass) {
		if (!FrameClass) return;
		var oldBrowseRouter = FrameClass.prototype.browseRouter;
		FrameClass.prototype.browseRouter = function(routerView) {
			oldBrowseRouter.apply(this, arguments);
			routerView.set({ 'wwml-webdav': { text: l10n.tab_title, priority: 50 } });
		};

		var oldBindHandlers = FrameClass.prototype.bindHandlers;
		FrameClass.prototype.bindHandlers = function() {
			oldBindHandlers.apply(this, arguments);
			this.on('content:render:wwml-webdav-content', function() {
				this.content.set(new window.WWML_Browser_View({ controller: this }));
			}, this);
		};
	};

	patchFrame(wp.media.view.MediaFrame.Post);
	patchFrame(wp.media.view.MediaFrame.Select);

})(jQuery, _);
