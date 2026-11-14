var COMYLI = COMYLI || {};
COMYLI.isReady = true;

(function (window, document, $) {
	'use strict';

	function escapeHtml(str) {
		if (str === null || str === undefined) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function base64url_encode(data) {
		return window.btoa(data)
			.replace(/\+/g, '-')
			.replace(/\//g, '_')
			.replace(/=+$/gm, '');
	}

	function base64url_decode(data) {
		var padLen = (4 - (data.length % 4)) % 4;
		return window.atob(
			data.replace(/_/g, '/')
				.replace(/-/g, '+')
				.padEnd(data.length + padLen, '=')
		);
	}

	function _get(reqKey) {
		var uri = window.location.href.split('?');
		if (uri.length <= 1) return undefined;
		var vars = uri[1].split('&');
		var query_string = {};
		for (var i = 0; i < vars.length; i++) {
			if (!vars[i]) continue;
			var pair = vars[i].split('=');
			var key = decodeURIComponent(pair[0] || '');
			var rawVal = pair.length > 1 ? pair.slice(1).join('=') : '';
			var value = decodeURIComponent(rawVal);
			if (typeof query_string[key] === 'undefined') {
				query_string[key] = value;
			} else if (typeof query_string[key] === 'string') {
				query_string[key] = [query_string[key], value];
			} else {
				query_string[key].push(value);
			}
		}
		return query_string[base64url_encode(reqKey)];
	}

	function getScriptAddr() {
		return '//' + window.location.hostname + window.location.pathname;
	}

	function getComyliEnv(el) {
		if (el && typeof el.closest === 'function') {
			var layout = el.closest('.comyli-layout');
			if (layout) {
				if (layout._comyliEnvParsed) {
					return layout._comyliEnvParsed;
				}
				if (layout.dataset && layout.dataset.comyliEnv) {
					try {
						layout._comyliEnvParsed = JSON.parse(layout.dataset.comyliEnv);
						return layout._comyliEnvParsed;
					} catch (e) {}
				}
			}
		}
		return typeof window.COMYLI_ENV !== 'undefined' ? window.COMYLI_ENV : null;
	}

	function findFormFieldByName(form, name) {
		if (!form || !form.elements) return null;
		for (var i = 0; i < form.elements.length; i++) {
			if (form.elements[i].name === name) {
				return form.elements[i];
			}
		}
		return null;
	}

	function submitFilterForm(form) {
		if (!form) return;
		if ($ && typeof $ === 'function') {
			$(form).trigger('submit');
		} else {
			form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
		}
	}

	function parseJsonTextResponse(text) {
		if (typeof text === 'string' && text.startsWith('"') && text.endsWith('"')) {
			try { return JSON.parse(text); } catch (e) {}
		}
		return text;
	}

	function toast(text, timeoutMs) {
		timeoutMs = timeoutMs || 3000;
		var container = document.getElementById('toast-container');
		if (!container) {
			container = document.createElement('div');
			container.id = 'toast-container';
			document.body.appendChild(container);
		}

		var t = document.createElement('div');
		t.className = 'toast';
		t.innerHTML = text;
		container.appendChild(t);

		var closeToast = function () {
			t.classList.add('closing');
			setTimeout(function () {
				if (t.parentElement) t.remove();
			}, 300);
		};

		var tTimeout = setTimeout(closeToast, timeoutMs);

		t.addEventListener('mouseenter', function () {
			clearTimeout(tTimeout);
		});
		t.addEventListener('mouseleave', function () {
			tTimeout = setTimeout(closeToast, timeoutMs);
		});

		return {
			close: closeToast,
			element: t
		};
	}

	function isValidForm(form) {
		if (!form || !form.elements) return true;
		for (var i = 0; i < form.elements.length; i++) {
			var e = form.elements[i];
			var isStrict = (e.dataset && e.dataset.strict === 'true') ||
				(e.list && e.list.dataset && e.list.dataset.strict === 'true');

			if (e.list != null && isStrict && e.list.id.substring(0, 8) !== 'optional') {
				var proof = false;
				for (var j = 0; j < e.list.options.length; j++) {
					if (e.list.options[j].value === e.value || e.value === '') {
						proof = true;
						break;
					}
				}
				if (!proof) {
					toast('"' + escapeHtml(e.value) + '" ist kein Wert aus der vorgegebenen Liste.');
					return false;
				}
			}

			if ((e.tagName === 'INPUT' || e.tagName === 'TEXTAREA') && e.required && e.value === '') {
				toast('Spalte "' + escapeHtml(e.name) + '" muss gef&uuml;llt sein.');
				return false;
			}
			if (e.tagName === 'SELECT' && e.required) {
				var selVal = (e.selectedIndex >= 0 && e.options[e.selectedIndex]) ? e.options[e.selectedIndex].value : '';
				if (selVal === '') {
					toast('Spalte "' + escapeHtml(e.name) + '" muss gef&uuml;llt sein.');
					return false;
				}
			}

			if (e.validity && !e.validity.valid) {
				toast('Spalte "' + escapeHtml(e.name) + '" entspricht nicht dem vorgegebenen Format: ' + escapeHtml(e.pattern || ''));
				return false;
			}
		}
		return true;
	}

	function ajaxLoad(form) {
		if (!form || !form.elements) return;
		for (var i = 0; i < form.elements.length; i++) {
			var e = form.elements[i];
			if (e.name && e.name.substr(0, 4) === 'prim') {
				var primNew = findFormFieldByName(form, e.name.substr(4));
				if (!primNew) continue;
				if (primNew.tagName === 'INPUT' || primNew.tagName === 'TEXTAREA') {
					e.value = primNew.value;
				} else if (primNew.tagName === 'SELECT' && primNew.selectedIndex >= 0 && primNew.options[primNew.selectedIndex]) {
					e.value = primNew.options[primNew.selectedIndex].value;
				}
			}
		}
	}

	function extractFileName(e) {
		var fullPath = e.value;
		if (!fullPath) return;
		var startIndex = (fullPath.indexOf('\\') >= 0 ? fullPath.lastIndexOf('\\') : fullPath.lastIndexOf('/'));
		var filename = fullPath.substring(startIndex);
		if (filename.indexOf('\\') === 0 || filename.indexOf('/') === 0) {
			filename = filename.substring(1);
		}
		var form = e.form || (typeof e.closest === 'function' ? e.closest('form') : null);
		var nameField = form ? findFormFieldByName(form, e.name + '_name') : null;
		if (nameField) nameField.value = filename;
	}

	function _doAjaxSend(form) {
		form.isSaving = true;
		form.needsSave = false;

		var xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function () {
			if (this.readyState === 4 && this.status === 200) {
				var response = String(parseJsonTextResponse(this.responseText) || '');
				var tsParts = response.split('|TS|');
				toast(escapeHtml(tsParts[0]));
				if (tsParts.length > 1 && tsParts[1]) {
					var inputs = form.querySelectorAll('input[name="lastChanged"], input[name="lstChanged"]');
					inputs.forEach(function (inp) {
						inp.value = tsParts[1].trim();
					});
				}
				ajaxLoad(form);
				form.isSaving = false;
				if (form.needsSave) {
					_doAjaxSend(form);
				}
			} else if (this.readyState === 4) {
				var errText = parseJsonTextResponse(this.responseText);
				toast('Fehler: ' + escapeHtml(errText));
				form.isSaving = false;
			}
		};

		var env = getComyliEnv(form);
		var tbl = _get('table');
		var tblParam = (tbl !== undefined && tbl !== 'undefined') ? '&' + base64url_encode('table') + '=' + tbl : '';
		var dbgParam = _get('debug');
		var uriStr = '' + getScriptAddr() + '?' + base64url_encode('noInterface') + '=1&' + base64url_encode('debug') + '=' + (dbgParam || '') + tblParam;

		if (isValidForm(form)) {
			var formData = new FormData(form);
			if (env && env.restUrl) {
				var urlObj = new URL(env.restUrl + 'save', window.location.origin);
				if (env.postId) urlObj.searchParams.set('comyli_id', env.postId);
				if (env.ctx) urlObj.searchParams.set('comyli_ctx', env.ctx);
				if (tbl !== undefined && tbl !== 'undefined') {
					urlObj.searchParams.set(base64url_encode('table'), tbl);
				}
				uriStr = urlObj.toString();
			}
			formData.append('TA_btnSubmit', '[S]');
			xhttp.open('POST', uriStr, true);
			if (env && env.nonce) xhttp.setRequestHeader('X-WP-Nonce', env.nonce);
			xhttp.send(formData);
		} else {
			form.isSaving = false;
		}
	}

	function ajaxSend(e) {
		if (e && e.isTrusted === false && (!e.detail || !e.detail.userAction)) {
			return;
		}
		var form = (e && e.target) ? (e.target.form || (typeof e.target.closest === 'function' ? e.target.closest('form') : null)) : null;
		if (!form && e && e.tagName === 'FORM') form = e;
		if (!form) return;
		if (form.isSaving) {
			form.needsSave = true;
			return;
		}
		_doAjaxSend(form);
	}

	function activateAjaxSend() {
		if (typeof COMYLI !== 'undefined' && typeof COMYLI.triggerActiveSync === 'function') {
			COMYLI.triggerActiveSync();
		}
	}

	function addAjaxTo(elements) {
		for (var i = 0; i < elements.length; i++) {
			var form = elements[i];
			for (var j = 0; j < form.elements.length; j++) {
				var input = form.elements[j];
				if (!input._hasComyliListener) {
					input._hasComyliListener = true;
					input.addEventListener('change', ajaxSend);
					input.addEventListener('input', activateAjaxSend, true);
				}
			}
		}
	}

	function updateBulkDelBtn(tableEl) {
		if (!tableEl) return;
		var rowChecks = tableEl.querySelectorAll('.comyli-bulk-check');
		var bulkDelBtn = tableEl.querySelector('.comyli-bulk-delete-btn');
		var anyChecked = Array.from(rowChecks).some(function (cb) { return cb.checked; });
		if (bulkDelBtn) bulkDelBtn.style.display = anyChecked ? 'inline-block' : 'none';
	}

	function colsInit(container) {
		var scope = container || document;
		var colnames = scope.getElementsByClassName('colnames');
		for (var i = 0; i < colnames.length; i++) {
			var parentWidth = colnames[i].parentElement ? colnames[i].parentElement.getBoundingClientRect().width : 0;
			if (parentWidth > 10) {
				colnames[i].style.minWidth = (parentWidth - 10) + 'px';
			}
		}
	}

	function scrollHandler() {
		var navbar = document.querySelector('.comyli-colnames-row');
		if (!navbar) return;
		var colnames = document.getElementsByClassName('colnames');
		var rect = navbar.parentElement ? navbar.parentElement.getBoundingClientRect() : null;
		var stickyTop = rect ? rect.top + window.pageYOffset : 0;

		if (window.pageYOffset >= stickyTop && rect && rect.bottom > 80) {
			navbar.classList.add('sticky');
			for (var i = 0; i < colnames.length; i++) {
				if (colnames[i].parentElement) {
					colnames[i].style.left = colnames[i].parentElement.getBoundingClientRect().left + 'px';
				}
			}
		} else {
			navbar.classList.remove('sticky');
			for (var j = 0; j < colnames.length; j++) {
				colnames[j].style.left = '0px';
			}
		}
	}

	function addResizableHandle(column) {
		if (!column || column.querySelector('.column-resize-handle')) return;
		var handle = document.createElement('div');
		handle.classList.add('column-resize-handle');
		column.appendChild(handle);

		var currentColumn = null;
		var startX = 0;
		var initialWidth = 0;

		var onDrag = function (e) {
			if (!currentColumn) return;
			var diffX = e.clientX - startX;
			var newWidth = initialWidth + diffX;
			if (newWidth > 50) {
				currentColumn.style.width = newWidth + 'px';
				currentColumn.style.maxWidth = newWidth + 'px';
			}
		};

		var stopResize = function () {
			document.removeEventListener('mousemove', onDrag);
			document.removeEventListener('mouseup', stopResize);
			document.body.style.userSelect = '';
			document.body.style.cursor = '';
			currentColumn = null;
		};

		handle.addEventListener('mousedown', function (e) {
			e.stopPropagation();
			currentColumn = column;
			startX = e.clientX;
			initialWidth = currentColumn.offsetWidth;
			document.body.style.userSelect = 'none';
			document.body.style.cursor = 'col-resize';
			document.addEventListener('mousemove', onDrag);
			document.addEventListener('mouseup', stopResize);
		});
	}

	COMYLI.filter = {
		cleanFilter: function (scopeEl) {
			var root = scopeEl || document;
			var elements = root.querySelectorAll('.filter:not([type="submit"])');
			for (var i = 0; i < elements.length; i++) {
				elements[i].value = '';
			}
			var searchInput = root.querySelector('.volltextsuche input');
			if (searchInput) {
				searchInput.value = '';
			}
		},

		switchBool: function (e) {
			if (!$) return;
			var a = $(e);
			var input = a.prev('input');
			var inputName = base64url_decode(input.attr('name'));

			if (a.text() === 'or') {
				a.text('and');
				inputName = inputName.substr(0, 5) + 'A' + inputName.substr(6);
			} else {
				a.text('or');
				inputName = inputName.substr(0, 5) + 'O' + inputName.substr(6);
			}
			input.attr('name', base64url_encode(inputName));
		}
	};

	COMYLI.initCodeEditors = function (root) {
		var scope = root || document;
		if (typeof CodeJar === 'undefined' || typeof Prism === 'undefined') return;
		scope.querySelectorAll('textarea.sql-editor').forEach(function (textarea) {
			if (textarea.dataset.codejarInit === '1') return;
			textarea.dataset.codejarInit = '1';
			textarea.style.display = 'none';

			var wrapper = document.createElement('div');
			wrapper.style.display = 'inline-block';
			wrapper.style.verticalAlign = 'top';
			wrapper.style.width = '100%';

			var editorDiv = document.createElement('div');
			editorDiv.className = 'codejar-editor language-sql';
			editorDiv.style.border = '1px solid #ccc';
			editorDiv.style.padding = '8px';
			editorDiv.style.minHeight = '60px';
			editorDiv.style.fontFamily = 'monospace';
			editorDiv.style.borderRadius = '4px';
			editorDiv.style.boxShadow = 'inset 0 1px 1px rgba(0,0,0,0.075)';
			editorDiv.style.width = '100%';
			editorDiv.style.background = 'inherit';
			editorDiv.style.color = 'inherit';

			wrapper.appendChild(editorDiv);
			textarea.parentNode.insertBefore(wrapper, textarea);

			var highlight = function (editor) {
				editor.textContent = editor.textContent;
				Prism.highlightElement(editor);
			};

			var jar = CodeJar(editorDiv, highlight);
			jar.updateCode(textarea.value);

			jar.onUpdate(function (code) {
				if (textarea.value !== code) {
					textarea.value = code;
					textarea.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});

			editorDiv.addEventListener('focus', function () {
				textarea.dispatchEvent(new Event('focus', { bubbles: true }));
			});
			editorDiv.addEventListener('input', function () {
				textarea.dispatchEvent(new Event('input', { bubbles: true }));
			});
		});
	};

	COMYLI.initSelect2 = function (root) {
		if (!$ || typeof $.fn === 'undefined' || typeof $.fn.select2 !== 'function') return;
		var scope = root || document;
		scope.querySelectorAll('.select2Input').forEach(function (select) {
			if (select.dataset.select2Init === '1') return;
			select.dataset.select2Init = '1';

			var listName = select.dataset.fkList || select.dataset.list || '';
			var datalistEl = listName ? document.getElementById(listName) : null;
			var items = [];
			if (datalistEl) {
				datalistEl.querySelectorAll('option').forEach(function (opt) {
					items.push({ id: opt.value, text: opt.value });
				});
			}

			$(select).select2({
				data: items,
				allowClear: true
			});

			var hiddenInput = select.previousElementSibling;
			var initialValues = [];
			if (hiddenInput) {
				try { initialValues = JSON.parse(hiddenInput.value || '[]'); } catch (e) {}
			}

			Array.from(select.options).forEach(function (option) {
				option.selected = Array.isArray(initialValues) && initialValues.indexOf(option.value) !== -1;
			});
			select.dispatchEvent(new Event('change'));

			$(select).on('change', function () {
				if (!hiddenInput) return;
				var selectedValues = Array.from(this.selectedOptions).map(function (option) {
					return option.value;
				});
				var newValue = JSON.stringify(selectedValues);
				if (hiddenInput.value !== newValue) {
					hiddenInput.value = newValue;
					hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
					hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
	};

	COMYLI.initResizeHandles = function (root) {
		var scope = root || document;
		scope.querySelectorAll('.table.select.comyli').forEach(function (tableContainer) {
			var columns = tableContainer.querySelectorAll('.comyli-colnames-row > .td');
			columns.forEach(function (column, index) {
				if (index < columns.length - 1) {
					addResizableHandle(column);
				}
			});
		});
	};

	COMYLI.bindDelegatedEvents = function () {
		if (COMYLI._delegatedBound) return;
		COMYLI._delegatedBound = true;

		// Stop sort trigger when clicking column info icon
		document.addEventListener('click', function (e) {
			if (e.target && e.target.closest('.comyli-col-info')) {
				e.stopPropagation();
				e.preventDefault();
			}
		}, true);

		// Delegated sortable header click
		document.addEventListener('click', function (e) {
			var sortEl = e.target && typeof e.target.closest === 'function' ? e.target.closest('.comyli-sortable') : null;
			if (!sortEl || e.target.closest('.column-resize-handle')) return;
			var tableEl = sortEl.closest('.table.select.comyli');
			if (!tableEl) return;

			var uid = tableEl.dataset.comyliUid;
			var form = uid ? document.getElementById('fsp_' + uid) : tableEl.querySelector('.comyli-fsp-form');
			if (!form) return;

			var orderKey = tableEl.dataset.orderKey || base64url_encode('order');
			var dirKey = tableEl.dataset.dirKey || base64url_encode('d');
			var sortCol = sortEl.dataset.sortCol || '';
			var sortDir = sortEl.dataset.sortDir || 'ASC';

			var setHidden = function (name, val) {
				var inp = findFormFieldByName(form, name);
				if (!inp) {
					inp = document.createElement('input');
					inp.type = 'hidden';
					inp.name = name;
					form.appendChild(inp);
				}
				inp.value = val;
			};

			setHidden(orderKey, sortCol);
			setHidden(dirKey, sortDir);
			submitFilterForm(form);
		});

		// Delegated cleanFilter link
		document.addEventListener('click', function (e) {
			var cleanBtn = e.target && typeof e.target.closest === 'function' ? e.target.closest('.cleanFilter') : null;
			if (!cleanBtn) return;
			e.preventDefault();
			var tableEl = cleanBtn.closest('.table.select.comyli');
			COMYLI.filter.cleanFilter(tableEl || document);
			var uid = tableEl ? tableEl.dataset.comyliUid : null;
			var form = (uid ? document.getElementById('fsp_' + uid) : null) || cleanBtn.closest('form') || (tableEl ? tableEl.querySelector('.comyli-fsp-form') : null);
			submitFilterForm(form);
		});

		// Delegated page/limit change + bulk selection checkboxes
		document.addEventListener('change', function (e) {
			var target = e.target;
			if (!target) return;

			if (target.classList.contains('page') || target.classList.contains('limit')) {
				submitFilterForm(target.form || target.closest('form'));
				return;
			}

			if (target.classList.contains('comyli-bulk-select-all')) {
				var tableEl = target.closest('.table.select.comyli');
				if (!tableEl) return;
				var rowChecks = tableEl.querySelectorAll('.comyli-bulk-check');
				rowChecks.forEach(function (cb) { cb.checked = target.checked; });
				updateBulkDelBtn(tableEl);
			} else if (target.classList.contains('comyli-bulk-check')) {
				var tableEl2 = target.closest('.table.select.comyli');
				if (!tableEl2) return;
				var selectAll = tableEl2.querySelector('.comyli-bulk-select-all');
				var rowChecks2 = tableEl2.querySelectorAll('.comyli-bulk-check');
				if (!target.checked && selectAll) selectAll.checked = false;
				var allChecked = rowChecks2.length > 0 && Array.from(rowChecks2).every(function (c) { return c.checked; });
				if (allChecked && selectAll) selectAll.checked = true;
				updateBulkDelBtn(tableEl2);
			}
		});

		// Delegated bulk delete button
		document.addEventListener('click', function (e) {
			var bulkBtn = e.target && typeof e.target.closest === 'function' ? e.target.closest('.comyli-bulk-delete-btn') : null;
			if (!bulkBtn) return;
			var tableEl = bulkBtn.closest('.table.select.comyli');
			if (!tableEl) return;

			if (!window.confirm('Wirklich alle ausgewählten Zeilen löschen?')) return;
			var rowChecks = tableEl.querySelectorAll('.comyli-bulk-check');
			var selectAll = tableEl.querySelector('.comyli-bulk-select-all');
			rowChecks.forEach(function (cb) {
				if (cb.checked) {
					var tr = cb.closest('.tr');
					if (tr) {
						var btnDel = tr.querySelector('.btnDel');
						if (btnDel) btnDel.click();
					}
				}
			});
			rowChecks.forEach(function (cb) { cb.checked = false; });
			if (selectAll) selectAll.checked = false;
			updateBulkDelBtn(tableEl);
		});

		// Delegated delete button with 10s Undo toast
		document.addEventListener('click', function (e) {
			var btn = e.target && typeof e.target.closest === 'function' ? e.target.closest('.btnDel') : null;
			if (!btn) return;
			e.preventDefault();
			if (btn.dataset.deleting) return;
			btn.dataset.deleting = '1';

			var form = btn.closest('.formEdit');
			if (!form) return;

			var firstInput = form.querySelector('input:not([type="hidden"]):not([type="submit"]), select, textarea');
			var rowName = firstInput ? firstInput.value : 'Eintrag';
			if (rowName.length > 30) rowName = rowName.substring(0, 30) + '...';
			if (!rowName) rowName = 'Eintrag';
			var safeRowName = escapeHtml(rowName);

			form.style.display = 'none';

			var timerId = Date.now() + '_' + Math.floor(Math.random() * 1000);
			var countdown = 10;
			var toastObj = toast('<b>' + safeRowName + '</b> wird in <span id="cd-' + timerId + '">' + countdown + '</span>s gel&ouml;scht... <a href="#" class="undo-btn" data-timer="' + timerId + '" style="color: #ffaa00; text-decoration: underline; font-weight: bold; margin-left: 10px; cursor: pointer;">R&uuml;ckg&auml;ngig</a>', 10000);

			var cdInterval = setInterval(function () {
				countdown--;
				var cdSpan = document.getElementById('cd-' + timerId);
				if (cdSpan) cdSpan.innerText = String(countdown);
				if (countdown <= 0) clearInterval(cdInterval);
			}, 1000);

			var executeDelete = function () {
				clearInterval(cdInterval);
				var xhttp = new XMLHttpRequest();
				xhttp.onreadystatechange = function () {
					if (this.readyState === 4 && this.status === 200) {
						form.remove();
					} else if (this.readyState === 4) {
						form.style.display = '';
						delete btn.dataset.deleting;
						toast('Fehler beim L&ouml;schen: ' + escapeHtml(parseJsonTextResponse(this.responseText)));
					}
				};
				var env = getComyliEnv(form);
				var tbl = _get('table');
				var tblParam = (tbl !== undefined && tbl !== 'undefined') ? '&' + base64url_encode('table') + '=' + tbl : '';
				var uri = '' + getScriptAddr() + '?' + base64url_encode('noInterface') + '=1' + tblParam;
				if (env && env.restUrl) {
					var urlObj = new URL(env.restUrl + 'save', window.location.origin);
					if (env.postId) urlObj.searchParams.set('comyli_id', env.postId);
					if (env.ctx) urlObj.searchParams.set('comyli_ctx', env.ctx);
					if (tbl !== undefined && tbl !== 'undefined') {
						urlObj.searchParams.set(base64url_encode('table'), tbl);
					}
					uri = urlObj.toString();
				}

				xhttp.open('POST', uri, true);
				if (env && env.nonce) xhttp.setRequestHeader('X-WP-Nonce', env.nonce);
				var formData = new FormData(form);
				formData.append('TA_btnSubmit', '[X]');
				xhttp.send(formData);
			};

			var deleteTimer = setTimeout(executeDelete, 10000);

			var undoBtn = document.querySelector('.undo-btn[data-timer="' + timerId + '"]');
			if (undoBtn) {
				undoBtn.addEventListener('click', function (e2) {
					e2.preventDefault();
					clearTimeout(deleteTimer);
					clearInterval(cdInterval);
					toastObj.close();
					form.style.display = '';
					delete btn.dataset.deleting;
				});
			}
		});

		// Delegated formEdit / formAdd validation & WP REST insert handling
		document.addEventListener('submit', function (e) {
			var form = e.target;
			if (!form || !form.classList) return;

			if (form.classList.contains('formEdit')) {
				if (!isValidForm(form)) {
					e.preventDefault();
					return;
				}
				var envEdit = getComyliEnv(form);
				if (envEdit && envEdit.restUrl) {
					e.preventDefault();
					_doAjaxSend(form);
				}
				return;
			}

			if (form.classList.contains('formAdd')) {
				if (!isValidForm(form)) {
					e.preventDefault();
					return;
				}
				var envAdd = getComyliEnv(form);
				if (envAdd && envAdd.restUrl) {
					e.preventDefault();
					var tableEl = form.closest('.table.select.comyli');
					var tbl = _get('table');
					var urlObj = new URL(envAdd.restUrl + 'save', window.location.origin);
					if (envAdd.postId) urlObj.searchParams.set('comyli_id', envAdd.postId);
					if (envAdd.ctx) urlObj.searchParams.set('comyli_ctx', envAdd.ctx);
					if (tbl !== undefined && tbl !== 'undefined') {
						urlObj.searchParams.set(base64url_encode('table'), tbl);
					}

					var formData = new FormData(form);
					formData.append('TA_btnSubmit', '[Hinzuf]');

					var xhttp = new XMLHttpRequest();
					xhttp.onreadystatechange = function () {
						if (this.readyState === 4 && this.status === 200) {
							var resp = String(parseJsonTextResponse(this.responseText) || '');
							toast(escapeHtml(resp.split('|TS|')[0]));
							var fspForm = tableEl ? tableEl.querySelector('.comyli-fsp-form') : null;
							if (fspForm) {
								submitFilterForm(fspForm);
							} else {
								window.location.reload();
							}
						} else if (this.readyState === 4) {
							toast('Fehler: ' + escapeHtml(parseJsonTextResponse(this.responseText)));
						}
					};
					xhttp.open('POST', urlObj.toString(), true);
					if (envAdd.nonce) xhttp.setRequestHeader('X-WP-Nonce', envAdd.nonce);
					xhttp.send(formData);
				}
			}
		});

		// Delegated AJAX Filter / Sort / Pagination form submission (multi-table safe)
		if ($ && typeof $ === 'function') {
			$(document).on('submit', '.comyli-fsp-form', function (e) {
				var submitter = e.originalEvent ? e.originalEvent.submitter : null;
				if (submitter && submitter.name === base64url_encode('csv')) {
					return; // Let the browser handle CSV download natively
				}

				e.preventDefault();
				var formEl = this;
				var tableEl = formEl.closest('.table.select.comyli');
				var uid = tableEl ? tableEl.dataset.comyliUid : null;
				var allTables = Array.from(document.querySelectorAll('.table.select.comyli'));
				var tableIndex = tableEl ? allTables.indexOf(tableEl) : 0;

				var url = '?' + $(formEl).serialize();
				var tableWrapper = tableEl ? $(tableEl).parent() : $('.table.select.comyli').parent();
				tableWrapper.css('opacity', '0.5');

				$.ajax({
					url: url,
					type: 'GET',
					success: function (response) {
						var $resp = $('<div>').append($.parseHTML(response));
						var $candidates = $resp.find('.table.select.comyli');
						var $newTable = $();
						if ($candidates.length === 1) {
							$newTable = $candidates.first();
						} else if ($candidates.length > 1) {
							$newTable = $candidates.eq(tableIndex >= 0 ? tableIndex : 0);
						}

						if ($newTable.length && tableEl) {
							var newTableEl = $newTable.get(0);
							$(tableEl).replaceWith(newTableEl);
							COMYLI.initTable(newTableEl);
							COMYLI.initSelect2(newTableEl);
							COMYLI.initResizeHandles(newTableEl);
							COMYLI.initCodeEditors(newTableEl);
							window.history.pushState({ path: url }, '', url);
						}
					},
					complete: function () {
						tableWrapper.css('opacity', '1');
					}
				});
			});

			$(window).on('popstate', function () {
				location.reload();
			});
		}

		if (!COMYLI._scrollBound) {
			window.addEventListener('scroll', scrollHandler);
			COMYLI._scrollBound = true;
		}
	};

	COMYLI.initTable = function (root) {
		COMYLI.bindDelegatedEvents();

		var scope = root || document;
		var tables = [];
		if (scope !== document && scope.classList && scope.classList.contains('table') && scope.classList.contains('comyli')) {
			tables = [scope];
		} else {
			tables = Array.from(scope.querySelectorAll('.table.select.comyli'));
		}

		if (!tables.length) return;

		tables.forEach(function (tbl) {
			if (tbl.dataset.comyliInit === '1') return;
			tbl.dataset.comyliInit = '1';

			colsInit(tbl);

			tbl.querySelectorAll('.mkText').forEach(function (el) {
				el.addEventListener('focusout', function () {
					this.value = this.value.trim();
				});
			});
			tbl.querySelectorAll('.mkBlob').forEach(function (el) {
				el.addEventListener('change', function () {
					extractFileName(this);
				});
			});
			tbl.querySelectorAll('.textarea').forEach(function (el) {
				var child = el.children[0];
				if (child) {
					child.addEventListener('focus', function () { this.classList.add('open'); });
					child.addEventListener('focusout', function () { this.classList.remove('open'); });
				}
			});
			tbl.querySelectorAll('.timestamp').forEach(function (el) {
				el.size = '19';
			});

			addAjaxTo(tbl.getElementsByClassName('formEdit'));
			COMYLI.initSelect2(tbl);
			COMYLI.initResizeHandles(tbl);
			COMYLI.initCodeEditors(tbl);
		});

		scrollHandler();
	};

	// Expose helpers globally where needed
	window.base64url_encode = base64url_encode;
	window.base64url_decode = base64url_decode;
	window._get = _get;
	window.toast = toast;
	window.ajaxLoad = ajaxLoad;
	window.isValidForm = isValidForm;

	// ---------------------------------------------------------
	// Live-Sync Collaboration Feature (3-State: Auto / An / Aus)
	// ---------------------------------------------------------
	COMYLI.syncMode = 'auto';
	COMYLI.liveSyncInterval = null;
	COMYLI.heartbeatInterval = null;
	COMYLI.lastKnownMaxTs = null;
	COMYLI.highPrecisionTimeout = null;

	COMYLI.stopTimers = function () {
		if (COMYLI.liveSyncInterval) clearInterval(COMYLI.liveSyncInterval);
		if (COMYLI.heartbeatInterval) clearInterval(COMYLI.heartbeatInterval);
		if (COMYLI.highPrecisionTimeout) clearTimeout(COMYLI.highPrecisionTimeout);
		COMYLI.liveSyncInterval = null;
		COMYLI.heartbeatInterval = null;
		COMYLI.highPrecisionTimeout = null;
	};

	COMYLI.startHeartbeat = function () {
		COMYLI.stopTimers();
		COMYLI.performHeartbeat(true);
		COMYLI.heartbeatInterval = setInterval(COMYLI.performHeartbeat, 15000);
	};

	function getPrimarySyncContainer() {
		var btn = document.querySelector('.comyli-live-sync-btn');
		return btn ? btn.closest('.table.select.comyli') : document.querySelector('.table.select.comyli');
	}

	COMYLI.performHeartbeat = function (isInit) {
		if (document.hidden) return;
		var container = getPrimarySyncContainer();
		var env = getComyliEnv(container);

		var urlObj;
		if (env && env.restUrl) {
			urlObj = new URL(env.restUrl + 'heartbeat', window.location.origin);
			if (env.postId) urlObj.searchParams.set('comyli_id', env.postId);
			if (env.ctx) urlObj.searchParams.set('comyli_ctx', env.ctx);
		} else {
			var uri = window.location.href;
			var hashIndex = uri.indexOf('#');
			if (hashIndex > -1) uri = uri.substring(0, hashIndex);
			urlObj = new URL(uri);
			urlObj.searchParams.set('action', 'heartbeat');
		}
		var tbl = _get('table');
		if (tbl) urlObj.searchParams.set(base64url_encode('table'), tbl);

		var xhr = new XMLHttpRequest();
		xhr.open('GET', urlObj.toString(), true);
		if (env && env.nonce) xhr.setRequestHeader('X-WP-Nonce', env.nonce);
		xhr.onreadystatechange = function () {
			if (this.readyState === 4 && this.status === 200) {
				var ts = String(parseJsonTextResponse(this.responseText) || '').trim();
				if (ts && !ts.startsWith('<')) {
					if (isInit === true) {
						COMYLI.lastKnownMaxTs = ts;
					} else if (COMYLI.lastKnownMaxTs && ts !== COMYLI.lastKnownMaxTs) {
						COMYLI.lastKnownMaxTs = ts;
						COMYLI.performLiveSync();
					}
				}
			}
		};
		xhr.send();
	};

	COMYLI.performLiveSync = function () {
		if (!COMYLI.isReady) return;
		var container = getPrimarySyncContainer();
		if (!container) return;

		var clientRows = {};
		container.querySelectorAll('.formEdit').forEach(function (row) {
			var pk = null;
			var lc = null;
			row.querySelectorAll('input').forEach(function (inp) {
				if (inp.name.startsWith('prim') && inp.name !== 'primlastChanged' && inp.name !== 'primlstChanged') {
					pk = inp.value;
				}
				if (inp.name === 'lastChanged' || inp.name === 'lstChanged') {
					lc = inp.value;
				}
			});
			if (pk && lc) {
				clientRows[pk] = lc;
			}
		});

		var env = getComyliEnv(container);
		var urlObj;
		if (env && env.restUrl) {
			urlObj = new URL(env.restUrl + 'syncState', window.location.origin);
			if (env.postId) urlObj.searchParams.set('comyli_id', env.postId);
			if (env.ctx) urlObj.searchParams.set('comyli_ctx', env.ctx);
		} else {
			var uri = window.location.href;
			var hashIndex = uri.indexOf('#');
			if (hashIndex > -1) uri = uri.substring(0, hashIndex);
			urlObj = new URL(uri);
			urlObj.searchParams.set('action', 'syncState');
		}
		var tbl = _get('table');
		if (tbl) urlObj.searchParams.set(base64url_encode('table'), tbl);

		var xhr = new XMLHttpRequest();
		xhr.open('POST', urlObj.toString(), true);
		if (env && env.nonce) xhr.setRequestHeader('X-WP-Nonce', env.nonce);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function () {
			if (this.readyState === 4) {
				if (this.status === 200) {
					try {
						var data = JSON.parse(this.responseText);
						COMYLI.applySyncState(data, container);
					} catch (e) {
						console.error('Live-Sync Parsing Error', e);
					}
				} else {
					var btn = container.querySelector('.comyli-live-sync-btn');
					if (btn) COMYLI.toggleLiveSync(btn);
				}
			}
		};
		xhr.send('clientRows=' + encodeURIComponent(JSON.stringify(clientRows)));
	};

	COMYLI.applySyncState = function (data, targetContainer) {
		if (!data || data.error) return;
		var container = targetContainer || getPrimarySyncContainer();
		if (!container) return;

		if (data.delete && data.delete.length > 0) {
			container.querySelectorAll('.formEdit').forEach(function (row) {
				var pk = null;
				row.querySelectorAll('input[type="hidden"]').forEach(function (inp) {
					if (inp.name.startsWith('prim') && inp.name !== 'primlastChanged' && inp.name !== 'primlstChanged') {
						pk = inp.value;
					}
				});
				if (pk && data.delete.includes(pk)) {
					row.remove();
				}
			});
		}

		if (data.upsert && data.upsert.length > 0) {
			var formEditRows = container.querySelectorAll('.formEdit');
			data.upsert.forEach(function (upsertRow) {
				var existingRow = null;
				formEditRows.forEach(function (row) {
					var pk = null;
					row.querySelectorAll('input[type="hidden"]').forEach(function (inp) {
						if (inp.name.startsWith('prim') && inp.name !== 'primlastChanged' && inp.name !== 'primlstChanged') {
							pk = inp.value;
						}
					});
					if (pk === upsertRow.id) {
						existingRow = row;
					}
				});

				if (existingRow) {
					if (existingRow.isSaving || existingRow.needsSave) return;

					var lc = null;
					existingRow.querySelectorAll('input[name="lastChanged"], input[name="lstChanged"]').forEach(function (inp) {
						lc = inp.value;
					});
					if (lc && upsertRow.ts && upsertRow.ts <= lc) return;

					if (existingRow.contains(document.activeElement)) {
						existingRow.style.boxShadow = 'inset 0 0 0 2px #ef4444';
						existingRow.title = 'Ein Kollege hat diesen Datensatz gerade geändert! Bitte neu laden.';
					} else {
						var temp = document.createElement('div');
						temp.innerHTML = upsertRow.html;
						var newRow = temp.firstElementChild;
						if (newRow) {
							existingRow.replaceWith(newRow);
							newRow.style.backgroundColor = '#dcfce7';
							setTimeout(function () { newRow.style.backgroundColor = ''; }, 2000);
						}
					}
				} else {
					var tempAdd = document.createElement('div');
					tempAdd.innerHTML = upsertRow.html;
					var newRowAdd = tempAdd.firstElementChild;
					if (newRowAdd) {
						var formAdd = container.querySelector('.formAdd');
						if (formAdd) {
							container.insertBefore(newRowAdd, formAdd);
						} else {
							container.appendChild(newRowAdd);
						}
						newRowAdd.style.backgroundColor = '#dcfce7';
						setTimeout(function () { newRowAdd.style.backgroundColor = ''; }, 2000);
					}
				}
			});

			COMYLI.initSelect2(container);
			addAjaxTo(container.getElementsByClassName('formEdit'));
		}
	};

	COMYLI.triggerActiveSync = function () {
		if (COMYLI.syncMode !== 'auto') return;

		COMYLI.stopTimers();
		COMYLI.liveSyncInterval = setInterval(COMYLI.performLiveSync, 10000);

		if (COMYLI.highPrecisionTimeout) clearTimeout(COMYLI.highPrecisionTimeout);
		COMYLI.highPrecisionTimeout = setTimeout(function () {
			if (COMYLI.syncMode === 'auto') {
				var btn = document.querySelector('.comyli-live-sync-btn');
				if (btn) {
					btn.innerHTML = '&#128259; Live-Sync (Auto)';
					btn.style.backgroundColor = '#3b82f6';
					btn.style.color = 'white';
				}
				COMYLI.startHeartbeat();
			}
		}, 60000);
	};

	COMYLI.toggleLiveSync = function (btn) {
		if (COMYLI.syncMode === 'auto') {
			COMYLI.syncMode = 'active';
			btn.innerHTML = '&#128259; Live-Sync (An)';
			btn.style.backgroundColor = '#22c55e';
			btn.style.color = 'white';
			COMYLI.stopTimers();
			COMYLI.liveSyncInterval = setInterval(COMYLI.performLiveSync, 10000);
			COMYLI.performLiveSync();
		} else if (COMYLI.syncMode === 'active') {
			COMYLI.syncMode = 'off';
			btn.innerHTML = '&#128259; Live-Sync (Aus)';
			btn.style.backgroundColor = '';
			btn.style.color = '';
			COMYLI.stopTimers();
		} else {
			COMYLI.syncMode = 'auto';
			btn.innerHTML = '&#128259; Live-Sync (Auto)';
			btn.style.backgroundColor = '#3b82f6';
			btn.style.color = 'white';
			COMYLI.stopTimers();
			COMYLI.startHeartbeat();
		}
	};

	document.addEventListener('visibilitychange', function () {
		if (COMYLI.syncMode === 'auto' && !document.hidden) {
			COMYLI.performHeartbeat();
		}
	});

	function bootstrapFrontend() {
		COMYLI.initTable(document);
		var btn = document.querySelector('.comyli-live-sync-btn');
		if (btn) {
			COMYLI.syncMode = 'auto';
			btn.innerHTML = '&#128259; Live-Sync (Auto)';
			btn.style.backgroundColor = '#3b82f6';
			btn.style.color = 'white';
			COMYLI.startHeartbeat();
		}
	}

	if (document.readyState === 'interactive' || document.readyState === 'complete') {
		bootstrapFrontend();
	} else {
		document.addEventListener('DOMContentLoaded', bootstrapFrontend);
	}
	window.addEventListener('load', function () {
		COMYLI.initTable(document);
	});

})(window, document, typeof jQuery !== 'undefined' ? jQuery : null);