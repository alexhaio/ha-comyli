var COMYLI = COMYLI || {};
COMYLI.isReady = true;

COMYLI.filter = {
	cleanFilter: function(){
		var elements = document.querySelectorAll('.filter:not([type="submit"])');
		for(var i=0; i < elements.length; i++){
			elements[i].value = '';
		}
		
		var searchInput = document.querySelector('.volltextsuche input');
		if(searchInput){ searchInput.value = ''; }
	},
	
	init: function(){
		var elements = document.getElementsByClassName('page');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("change", function(){this.form.submit();});
		}
		
		var elements = document.getElementsByClassName('cleanFilter');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("click", function(e){e.preventDefault(); COMYLI.filter.cleanFilter(); this.closest('form').submit();});
		}
	},
	switchBool: function(e){
		var a = $(e);
		var input = a.prev("input");
		var inputName = base64url_decode(input.attr("name"));
			
		if(a.text() == "or"){
			a.text("and");
			inputName = inputName.substr(0,5) + "A" + inputName.substr(6);
		}
		else{
			a.text("or");
			inputName = inputName.substr(0,5) + "O" + inputName.substr(6);
		}
		input.attr("name",base64url_encode(inputName));
	},
	
	removeFilterField: function(){
		
	},
	
	showOperatorsMask: function(){
		
	}
}




jQuery( document ).ready(function( $ ) {
	if (window.comyliJsInitialized) return;
	window.comyliJsInitialized = true;
	
	// Initialize CodeJar for MySQL syntax highlighting
	if (typeof CodeJar !== 'undefined' && typeof Prism !== 'undefined') {
		document.querySelectorAll('textarea.sql-editor').forEach(textarea => {
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
			
			// Inherit colors from parent for dark mode
			editorDiv.style.background = 'inherit';
			editorDiv.style.color = 'inherit';
			
			wrapper.appendChild(editorDiv);
			textarea.parentNode.insertBefore(wrapper, textarea);
			
			var highlight = function(editor) {
				editor.textContent = editor.textContent;
				Prism.highlightElement(editor);
			};
			
			var jar = CodeJar(editorDiv, highlight);
			jar.updateCode(textarea.value);
			
			jar.onUpdate(function(code) {
				if (textarea.value !== code) {
					textarea.value = code;
					// Dispatch change event so ajaxSend triggers
					textarea.dispatchEvent(new Event('change', {bubbles: true}));
				}
			});
			
			// To trigger activateAjaxSend on focus/input
			editorDiv.addEventListener('focus', function() {
				textarea.dispatchEvent(new Event('focus', {bubbles: true}));
			});
			editorDiv.addEventListener('input', function() {
				textarea.dispatchEvent(new Event('input', {bubbles: true}));
			});
		});
	}
	

});

Element.prototype.getElementsByName = function (arg) {
	var returnList = [];
	(function BuildReturn(startPoint) {
		for (var child in startPoint) {
			if (startPoint[child].nodeType != 1) continue; //not an element
			if (startPoint[child].getAttribute("name") == arg) returnList.push(startPoint[child]);
			if (startPoint[child].childNodes.length > 0) {
				BuildReturn(startPoint[child].childNodes);
			}
		}
	})(this.childNodes);
	return returnList;
};

//Sticky-Bar
var navbar;
var sticky;

//Spaltenueberschriften
var colnames;

COMYLI.initTable = function(){
	if (COMYLI._tableInitialized) return;
	COMYLI._tableInitialized = true;

	//Datentypen fuer sticky-Bar initialisieren
	navbar = document.getElementById("colNames");
	if(navbar){
		sticky = navbar.offsetTop;
//Alle Spaltenueberschriften erhalten Mindestbreite und scrollen mit
		//macht die "fixed"-Spaltenueberschriften h-scrollbar
		colnames = document.getElementsByClassName('colnames');
		colsInit()
		
		window.addEventListener('scroll', scrollHandler);
		scrollHandler();
		

		//Datentypen
		var elements = document.getElementsByClassName('mkText');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("focusout", function(){this.value.trim();});
		}
		var elements = document.getElementsByClassName('mkBlob');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("change", function(){extractFileName(this);});
		}
		var elements = document.getElementsByClassName('textarea');
		for(var i=0; i < elements.length; i++){
			e = elements[i].children[0];
			e.addEventListener("focus", function(){this.classList.add('open');});
			e.addEventListener("focusout", function(){this.classList.remove('open');});
		}
		//Alle Zeitstempel erhalten passende Breite und Pattern anstatt jeder einzeln via HTML
		var elements = document.getElementsByClassName('timestamp');
		for(var i=0; i < elements.length; i++){
			elements[i].size = '15';
			elements[i].pattern = '(0[0-9]|1[0-9]|2[0-9]|3[01]).(0[0-9]|1[012]).[0-9]{4} [0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}';
		}

		//Formular-Funktionen
		COMYLI.filter.init();
		
		var elements = document.getElementsByClassName('limit');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("change", function(){this.form.submit();});
		}
		var elements = document.getElementsByClassName('formEdit');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("submit", function(){return isValidForm(this);});
		}
		var elements = document.getElementsByClassName('formAdd');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("submit", function(){return isValidForm(this);});
		}
		var elements = document.getElementsByClassName('btnDel');
		for(var i=0; i < elements.length; i++){
			elements[i].addEventListener("click", function(e){
				e.preventDefault();
				var btn = e.currentTarget;
				if (btn.dataset.deleting) return;
				btn.dataset.deleting = '1';
				
				var form = btn.closest('.formEdit');
				if (!form) return;
				
				// Identify row
				var firstInput = form.querySelector('input:not([type="hidden"]):not([type="submit"]), select, textarea');
				var rowName = firstInput ? firstInput.value : 'Eintrag';
				if (rowName.length > 30) rowName = rowName.substring(0, 30) + '...';
				if (!rowName) rowName = 'Eintrag';
				
				// Hide the row immediately
				form.style.display = 'none';
				
				var timerId = Date.now();
				var countdown = 10;
				var toastObj = toast('<b>' + rowName + '</b> wird in <span id="cd-' + timerId + '">' + countdown + '</span>s gel&ouml;scht... <a href="#" class="undo-btn" data-timer="' + timerId + '" style="color: #ffaa00; text-decoration: underline; font-weight: bold; margin-left: 10px; cursor: pointer;">R&uuml;ckg&auml;ngig</a>', 10000);
				
				var cdInterval = setInterval(function() {
					countdown--;
					var cdSpan = document.getElementById('cd-' + timerId);
					if (cdSpan) cdSpan.innerText = countdown;
					if (countdown <= 0) clearInterval(cdInterval);
				}, 1000);
				
				var executeDelete = function() {
					clearInterval(cdInterval);
					var xhttp = new XMLHttpRequest();
					xhttp.onreadystatechange = function() {
						if (this.readyState == 4 && this.status == 200) {
							form.remove(); // permanently remove from DOM
						} else if (this.readyState == 4) {
							form.style.display = ''; // restore on error
							toast("Fehler beim L&ouml;schen: " + this.responseText);
						}
					};
					var tbl = _get('table');
					var tblParam = tbl !== undefined && tbl !== 'undefined' ? "&" + base64url_encode('table') + "=" + tbl : "";
					var uri = "" + getScriptAddr() + "?" + base64url_encode('noInterface') + "=1" + tblParam;
					
					xhttp.open("POST", uri, true);
					if (typeof COMYLI_ENV !== 'undefined' && COMYLI_ENV.nonce) xhttp.setRequestHeader('X-WP-Nonce', COMYLI_ENV.nonce);
					var formData = new FormData(form);
					formData.append('TA_btnSubmit', '[X]');
					xhttp.send(formData);
				};
				
				var deleteTimer = setTimeout(executeDelete, 10000);
				
				// Re-fetch undoBtn since it was just added to DOM by toast()
				var undoBtn = document.querySelector('.undo-btn[data-timer="' + timerId + '"]');
				if(undoBtn) {
					undoBtn.addEventListener('click', function(e2) {
						e2.preventDefault();
						clearTimeout(deleteTimer);
						clearInterval(cdInterval);
						toastObj.close();
						form.style.display = ''; // restore
						delete btn.dataset.deleting;
					});
				}
			});
		}
		
		//SQL-ACE-Editor
		

		//Alle Zellen erhalten onChange = 'activateAjaxSend(this)'
		//Alle Zellen erhalten onFocus  = 'ajaxSend(this)'
		var elements;
		elements = document.getElementsByClassName('formEdit');
		addAjaxTo(elements);
	}
};

// Initialize table immediately when DOM is ready (do not wait for full window.load!)
if (document.readyState === 'interactive' || document.readyState === 'complete') {
	COMYLI.initTable();
} else {
	document.addEventListener('DOMContentLoaded', COMYLI.initTable);
}
window.addEventListener('load', COMYLI.initTable);


function colsInit(){
	for(var i=0; i < colnames.length; i++){
		colnames[i].style.minWidth = colnames[i].parentElement.getBoundingClientRect().width - 10;
	}
}

function scrollHandler(){
	if (window.pageYOffset >= sticky) {
		navbar.classList.add("sticky");
		for(var i=0; i < colnames.length; i++){
			colnames[i].style.left = colnames[i].parentElement.getBoundingClientRect().left + "px";
		}
	}
	else {
		navbar.classList.remove("sticky");
		for(var i=0; i < colnames.length; i++){
			colnames[i].style.left = "0px";
		}
	}
}

function addAjaxTo(elements){
	for(var i=0; i < elements.length; i++){
		var form = elements[i];
		for(var j=0; j < form.elements.length; j++){
			var input = form.elements[j];
			if (!input._hasComyliListener) {
				input._hasComyliListener = true;
				input.addEventListener("change", ajaxSend);
				input.addEventListener("input", activateAjaxSend, true);
			}
		}
	}	
}
function extractFileName(e){
	var fullPath = e.value;
	if (fullPath) {
		var startIndex = (fullPath.indexOf('\\') >= 0 ? fullPath.lastIndexOf('\\') : fullPath.lastIndexOf('/'));
		var filename = fullPath.substring(startIndex);
		if (filename.indexOf('\\') === 0 || filename.indexOf('/') === 0) {
			filename = filename.substring(1);
		}
		var nameField = e.parentElement.parentElement.getElementsByName(e.name + '_name')[0];
		if(nameField !== undefined) nameField.value = filename;
	}
}

var changedForm = null;
function ajaxSend(e){ //onFocus
    if (e) {
        // Ignore synthetic programmatic events (e.g. from select2 init) that were not initiated by a user
        if (e.isTrusted === false && (!e.detail || !e.detail.userAction)) {
            return;
        }
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

function _doAjaxSend(form) {
    var lcs = form.querySelectorAll('input[name="lastChanged"], input[name="lstChanged"]');
    var debugVals = [];
    lcs.forEach(function(inp) { debugVals.push(inp.name + '=' + inp.value); });
    console.log('COMYLI PRE-SEND: Timestamps im Formular:', debugVals);
    form.isSaving = true;
    form.needsSave = false;

    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var response = this.responseText;
            if (typeof response === 'string' && response.startsWith('"') && response.endsWith('"')) {
                try { response = JSON.parse(response); } catch(e) {}
            }
            var tsParts = response.split('|TS|');
            toast(tsParts[0]);
            if (tsParts.length > 1 && tsParts[1]) {
                                var inputs = form.querySelectorAll('input[name="lastChanged"], input[name="lstChanged"]');
                inputs.forEach(function(inp) {
                    inp.value = tsParts[1].trim();
                });
            }
            ajaxLoad(form);
            form.isSaving = false;
            if (form.needsSave) {
                _doAjaxSend(form);
            }
        } else if(this.readyState == 4) {
            var errText = this.responseText;
            if (typeof errText === 'string' && errText.startsWith('"') && errText.endsWith('"')) {
                try { errText = JSON.parse(errText); } catch(e) {}
            }
            toast("Fehler: " + errText);
            console.log(this.responseText);
            form.isSaving = false;
        }
    };
    var tbl = _get('table');
    var tblParam = tbl !== undefined && tbl !== 'undefined' ? "&" + base64url_encode('table') + "=" + tbl : "";
    var uriStr = "" + getScriptAddr() + "?" + base64url_encode('noInterface') + "=1&" + base64url_encode('debug') + "=" + _get('debug') + tblParam;

    if(isValidForm(form)){
        var formData = new FormData(form);
        if (typeof window.COMYLI_ENV !== 'undefined' && window.COMYLI_ENV.restUrl) {
            var urlObj = new URL(window.COMYLI_ENV.restUrl + 'save', window.location.origin);
            urlObj.searchParams.set('comyli_id', window.COMYLI_ENV.postId);
            if (window.COMYLI_ENV.atts) {
                urlObj.searchParams.set('comyli_atts', JSON.stringify(window.COMYLI_ENV.atts));
            }
            if (tbl !== undefined && tbl !== 'undefined') {
                urlObj.searchParams.set(base64url_encode('table'), tbl);
            }
            uriStr = urlObj.toString();
        }
        formData.append('TA_btnSubmit', '[S]');
        xhttp.open("POST", uriStr, true);
        if (typeof COMYLI_ENV !== 'undefined' && COMYLI_ENV.nonce) xhttp.setRequestHeader('X-WP-Nonce', COMYLI_ENV.nonce);
        xhttp.send(formData);
    } else {
        form.isSaving = false;
    }
}
function activateAjaxSend(){ //onInput
	changedForm = (this && typeof this.closest === 'function') ? this.closest('.formEdit') : this.parentElement.parentElement;
	if (typeof COMYLI !== 'undefined' && typeof COMYLI.triggerActiveSync === 'function') COMYLI.triggerActiveSync();
}

function _get(reqKey) {
	var uri = window.location.href.split("?");
	if(uri.length > 1){
		var vars = uri[1].split("&");
		var query_string = {};
		for (var i = 0; i < vars.length; i++) {
			var pair = vars[i].split("=");
			var key = decodeURIComponent(pair[0]);
			var value = decodeURIComponent(pair[1]);
			// If first entry with this name
			if (typeof query_string[key] === "undefined") {
				query_string[key] = decodeURIComponent(value);
				// If second entry with this name
			} else if (typeof query_string[key] === "string") {
				var arr = [query_string[key], decodeURIComponent(value)];
				query_string[key] = arr;
				// If third or later entry with this name
			} else {
				query_string[key].push(decodeURIComponent(value));
			}
		}
		return query_string[base64url_encode(reqKey)];
	}
}

function getScriptAddr(){
	return '//' + window.location.hostname + window.location.pathname;
}

function base64url_encode(data) { 
	return window.btoa(data)
		.replace('+','-')
		.replace('/','_')
		.replace(/=+$/gm,'');
}

function base64url_decode(data) {
	return window.atob(
		data.padEnd(data.length + data.length % 2, '=')
		.replace('_','/')
		.replace('-','+')
	);

}
function display(e,s){
	e.style.display = s;
}

function toast(text, timeoutMs){
    timeoutMs = timeoutMs || 3000;
    var container = document.getElementById("toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        document.body.appendChild(container);
    }
    
    var t = document.createElement("div");
    t.className = "toast";
    t.innerHTML = text;
    container.appendChild(t);
    
    var tTimeout;
    var closeToast = function() {
        t.classList.add("closing");
        setTimeout(function() {
            if (t.parentElement) t.remove();
        }, 300);
    };
    
    tTimeout = setTimeout(closeToast, timeoutMs);
    
    // Pause on hover
    t.addEventListener('mouseenter', function() {
        clearTimeout(tTimeout);
    });
    
    // Resume on leave
    t.addEventListener('mouseleave', function() {
        tTimeout = setTimeout(closeToast, timeoutMs);
    });
    
    // Allow closing manually by passing a special ID or just clicking?
    // Not explicitly requested, but good to have.
    return {
        close: closeToast,
        element: t
    };
}

function ajaxLoad(form){ //einfach alle primX mit dem wert von X ueberschreiben
	//TODO: echtes AJAX-load via SQL: SELECT * FROM [table] WHERE [alle X von primX]
	var e;
	var primOld;
	var primNew;
	for (var i = 0; i < form.elements.length; i++) {
		e = form.elements[i];
		if(e.name.substr(0,4)=='prim'){
			primOld = e;
			primNew = form.getElementsByName(e.name.substr(4))[0];
			if( primNew.tagName == 'INPUT' || primNew.tagName == 'TEXTAREA' ){
				primOld.value = primNew.value;
			}
			if(primNew.tagName == 'SELECT'){
				primOld.value = primNew.options[primNew.selectedIndex].value;
			}
		}
	}
}


function isValidForm(form) {
	var e;
	for (var i = 0; i < form.elements.length; i++) {
		e = form.elements[i];
		//Alle Element durchlaufen und pruefen, ob Input mit Liste
		if(e.list != null){
			if(e.list.id.substring(0,8) != 'optional' && e.list.dataset.strict == "true"){
				var proof = 0;
				for (var j = 0; j < e.list.options.length; j++) { //Pruefe ob Wert Teil der zugehoerigen Liste, Wenn JA:
					if(e.list.options[j].value == e.value || e.value == ''){
						proof = 1;
						break;
					}
				}
				if(proof == 0){
					toast('"' + e.value + '" ist kein Wert aus der vorgegebnenen Liste.');
					return false;
				}
			}
		}
		
		//Alle required-Elemente befuellt?
		if( (e.tagName == 'INPUT' || e.tagName == 'TEXTAREA') && e.required){
			if(e.value == ''){
				toast('Spalte "' + e.name + '" muss gefuellt sein.');
				return false;
			}
		}
		if(e.tagName == 'SELECT' && e.required){
			if(e.options[e.selectedIndex].value == ''){
				toast('Spalte "' + e.name + '" muss gefuellt sein.');
				return false;
			}
		}
		
		//Alle Elemente gueltig? (pattern)
		if(!e.validity.valid){
			toast('Spalte "' + e.name + '" entspricht nicht dem vorgegebenen Format: ' + e.pattern);
			return false;
		}
	}
	
	return true;
}

function padWithZeroes(n, width) {
	while(n.length < width){
		n = '0' + n;
	}
	return n;
}

function setList(obj,list){
	obj.setAttribute("list", list);
}



document.addEventListener('DOMContentLoaded', () => {
    const tableContainer = document.querySelector('.table');
    if (!tableContainer) return;

    // Funktion zum HinzufÃƒÆ’Ã‚Â¼gen des Handles zu einer Spalte
    function addResizableHandle(column) {
        // Handle als unsichtbares DOM-Element erstellen
        const handle = document.createElement('div');
        handle.classList.add('column-resize-handle');
        column.appendChild(handle);

        let currentColumn, nextColumn, startX, initialWidth, initialNextWidth;

        // Startet den Ziehvorgang
        const startResize = (e) => {
            currentColumn = column;
            // Finde die nÃƒÆ’Ã‚Â¤chste Spalte, die auch eine 'resizable-column' ist
            nextColumn = currentColumn.nextElementSibling && currentColumn.nextElementSibling.classList.contains('resizable-column') 
                           ? currentColumn.nextElementSibling 
                           : null;

            startX = e.clientX;
            initialWidth = currentColumn.offsetWidth;
            initialNextWidth = nextColumn ? nextColumn.offsetWidth : 0;
            
            // Verhindert Textauswahl wÃƒÆ’Ã‚Â¤hrend des Ziehens
            document.body.style.userSelect = 'none'; 
            document.body.style.cursor = 'col-resize';

            document.addEventListener('mousemove', onDrag);
            document.addEventListener('mouseup', stopResize);
        };

        // FÃƒÆ’Ã‚Â¼hrt die GrÃƒÆ’Ã‚Â¶ÃƒÆ’Ã…Â¸enÃƒÆ’Ã‚Â¤nderung wÃƒÆ’Ã‚Â¤hrend des Ziehens aus
        const onDrag = (e) => {
            if (!currentColumn) return;

            const diffX = e.clientX - startX;
            const newWidth = initialWidth + diffX;
            const newNextWidth = initialNextWidth - diffX;
            
            // Nur fortfahren, wenn die Spalte nicht zu schmal wird (z.B. min 50px)
            if (newWidth > 50) {
                currentColumn.style.width = newWidth + 'px';
                currentColumn.style.maxWidth = newWidth + 'px';
                
                // Passt auch die Breite der nÃƒÆ’Ã‚Â¤chsten Spalte an, falls vorhanden,
                // um die Gesamtbreite der Tabelle konstant zu halten.
                //if (nextColumn && newNextWidth > 50) {
                //    nextColumn.style.width = newNextWidth + 'px';
                //}
            }
        };

        // Beendet den Ziehvorgang
        const stopResize = () => {
            document.removeEventListener('mousemove', onDrag);
            document.removeEventListener('mouseup', stopResize);
            document.body.style.userSelect = '';
            document.body.style.cursor = '';
            currentColumn = null;
            nextColumn = null;
        };

        handle.addEventListener('mousedown', startResize);
    }

    // FÃƒÆ’Ã‚Â¼ge Handles zu allen Spalten im Container hinzu
    const columns = tableContainer.querySelectorAll('.td');
    columns.forEach((column, index) => {
        // FÃƒÆ’Ã‚Â¼ge das Handle nur zu den Spalten hinzu, die NICHT die letzte sind
        if (index < columns.length - 1) {
             addResizableHandle(column);
        }
    });

    // Wichtig: FÃƒÆ’Ã‚Â¼ge das Handle auch zu den Spalten in den folgenden "Zeilen" hinzu,
    // um die gesamte "Tabelle" funktional zu machen. 
    // Idealerweise mÃƒÆ’Ã‚Â¼ssten die BreitenÃƒÆ’Ã‚Â¤nderungen alle Elemente der Spalte betreffen. 
    // FÃƒÆ’Ã‚Â¼r eine komplette LÃƒÆ’Ã‚Â¶sung mÃƒÆ’Ã‚Â¼sste man die BreitenÃƒÆ’Ã‚Â¤nderung auf alle Zellen 
    // in der gleichen Spaltenposition (Index) anwenden.
    // Beim display: table Layout genÃƒÆ’Ã‚Â¼gt es oft, nur die Breiten der Header zu ÃƒÆ’Ã‚Â¤ndern,
            // wenn table-layout: fixed gesetzt ist.
});

COMYLI.performLiveSync = function() {
    if (!COMYLI.isReady) return;
    
    var clientRows = {};
    var formEditRows = document.querySelectorAll('.table.select.comyli .formEdit');
    formEditRows.forEach(function(row) {
                var pk = null;
        var lc = null;
        var inputs = row.querySelectorAll('input');
        inputs.forEach(function(inp) {
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

        var uri = window.location.href;
    var hashIndex = uri.indexOf('#');
    if (hashIndex > -1) {
        uri = uri.substring(0, hashIndex);
    }
    var urlObj = new URL(uri);
    urlObj.searchParams.set('action', 'syncState');
    
    if (typeof window.COMYLI_ENV !== 'undefined' && window.COMYLI_ENV.restUrl) {
        urlObj = new URL(window.COMYLI_ENV.restUrl + 'syncState', window.location.origin);
        urlObj.searchParams.set('comyli_id', window.COMYLI_ENV.postId);
        if (window.COMYLI_ENV.atts) {
            urlObj.searchParams.set('comyli_atts', JSON.stringify(window.COMYLI_ENV.atts));
        }
    }
    var tbl = _get('table');
    if (tbl) urlObj.searchParams.set(base64url_encode('table'), tbl);
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", urlObj.toString(), true);
    if (typeof COMYLI_ENV !== 'undefined' && COMYLI_ENV.nonce) xhr.setRequestHeader('X-WP-Nonce', COMYLI_ENV.nonce);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (this.readyState === 4) {
            if (this.status === 200) {
                try {
                    var data = JSON.parse(this.responseText);
                    COMYLI.applySyncState(data);
                } catch(e) {
                    console.error("Live-Sync Parsing Error", e);
                }
            } else {
                console.warn("Live-Sync fehlerhaft, deaktiviere...");
                var btn = document.getElementById('btnLiveSync');
                if (btn) COMYLI.toggleLiveSync(btn);
            }
        }
    };
    xhr.send('clientRows=' + encodeURIComponent(JSON.stringify(clientRows)));
};

COMYLI.applySyncState = function(data) {
    if (!data || data.error) return;
    console.log('COMYLI LIVESYNC: Server sendet', data.upsert ? data.upsert.length : 0, 'Zeilen zum Update.');

    var container = document.querySelector('.table.select.comyli');
    if (!container) return;

    // Remove deleted rows
    if (data.delete && data.delete.length > 0) {
        var formEditRows = document.querySelectorAll('.table.select.comyli .formEdit');
        formEditRows.forEach(function(row) {
            var inputs = row.querySelectorAll('input[type="hidden"]');
            var pk = null;
            inputs.forEach(function(inp) {
                if (inp.name.startsWith('prim') && inp.name !== 'primlastChanged' && inp.name !== 'primlstChanged') {
                    pk = inp.value;
                }
            });
            if (pk && data.delete.includes(pk)) {
                row.remove();
            }
        });
    }

    // Upsert updated/new rows
    if (data.upsert && data.upsert.length > 0) {
        var formEditRows = document.querySelectorAll('.table.select.comyli .formEdit');
        
        data.upsert.forEach(function(upsertRow) {
            var existingRow = null;
            formEditRows.forEach(function(row) {
                var inputs = row.querySelectorAll('input[type="hidden"]');
                var pk = null;
                inputs.forEach(function(inp) {
                    if (inp.name.startsWith('prim') && inp.name !== 'primlastChanged' && inp.name !== 'primlstChanged') {
                        pk = inp.value;
                    }
                });
                if (pk === upsertRow.id) {
                    existingRow = row;
                }
            });

                        if (existingRow) {
                if (existingRow.isSaving || existingRow.needsSave) {
                    return; // Skip replacing this row
                }
                
                var lc = null;
                                var inputs = existingRow.querySelectorAll('input[name="lastChanged"], input[name="lstChanged"]');
                inputs.forEach(function(inp) {
                    lc = inp.value;
                });
                console.log('COMYLI LIVESYNC: Zeile ' + upsertRow.id + ' | Client-HTML-TS: ' + lc + ' | Server-DB-TS: ' + upsertRow.ts);
                if (lc && upsertRow.ts && upsertRow.ts <= lc) {
                    console.log('COMYLI LIVESYNC: Server-TS ist aelter/gleich, ueberspringe Update.');
                    console.log('COMYLI LIVESYNC: Debug Row from Server:', upsertRow.debug_row);
                    return; // Server is sending older or equal data than what we just successfully saved!
                }
                
                // Check if user is typing in this row
                if (existingRow.contains(document.activeElement)) {
                    // Collision!
                    existingRow.style.boxShadow = "inset 0 0 0 2px #ef4444";
                    existingRow.title = "Ein Kollege hat diesen Datensatz gerade geaendert! Bitte neu laden.";
                } else {
                    // Safe to replace
                    var temp = document.createElement('div');
                    temp.innerHTML = upsertRow.html;
                    var newRow = temp.firstElementChild;
                    console.log('COMYLI LIVESYNC: Ersetze HTML der Zeile. HTML:', upsertRow.html);
                    console.log('COMYLI LIVESYNC: Debug Row from Server:', upsertRow.debug_row);
                    existingRow.replaceWith(newRow);
                    newRow.style.backgroundColor = "#dcfce7"; // flash green
                    setTimeout(function() { newRow.style.backgroundColor = ""; }, 2000);
                }
            } else {
                // New row (Append to bottom, but before the insert row if it exists)
                var temp = document.createElement('div');
                temp.innerHTML = upsertRow.html;
                var newRow = temp.firstElementChild;
                var formAdd = container.querySelector('.formAdd');
                if (formAdd) {
                    container.insertBefore(newRow, formAdd);
                } else {
                    container.appendChild(newRow);
                }
                newRow.style.backgroundColor = "#dcfce7";
                setTimeout(function() { newRow.style.backgroundColor = ""; }, 2000);
            }
        });
        
        // Re-bind Select2 if available
        if (typeof initSelect2 === 'function') initSelect2();
        var elements = document.getElementsByClassName('formEdit');
        if (typeof addAjaxTo === 'function') addAjaxTo(elements);
    }
};

// ---------------------------------------------------------
// Live-Sync Collaboration Feature (3-State: Auto / An / Aus)
// ---------------------------------------------------------
COMYLI.syncMode = 'auto'; // 'auto' | 'active' | 'off'
COMYLI.liveSyncInterval = null;
COMYLI.heartbeatInterval = null;
COMYLI.lastKnownMaxTs = null;
COMYLI.highPrecisionTimeout = null;

COMYLI.stopTimers = function() {
    if (COMYLI.liveSyncInterval) clearInterval(COMYLI.liveSyncInterval);
    if (COMYLI.heartbeatInterval) clearInterval(COMYLI.heartbeatInterval);
    if (COMYLI.highPrecisionTimeout) clearTimeout(COMYLI.highPrecisionTimeout);
    COMYLI.liveSyncInterval = null;
    COMYLI.heartbeatInterval = null;
    COMYLI.highPrecisionTimeout = null;
};

COMYLI.startHeartbeat = function() {
    COMYLI.stopTimers();
    COMYLI.performHeartbeat(true); // init
    COMYLI.heartbeatInterval = setInterval(COMYLI.performHeartbeat, 15000);
};

COMYLI.performHeartbeat = function(isInit) {
    if (document.hidden) return; // Pause heartbeat if tab is hidden
    
    var urlObj;
    if (typeof window.COMYLI_ENV !== 'undefined' && window.COMYLI_ENV.restUrl) {
        urlObj = new URL(window.COMYLI_ENV.restUrl + 'heartbeat', window.location.origin);
        urlObj.searchParams.set('comyli_id', window.COMYLI_ENV.postId);
        if (window.COMYLI_ENV.atts) {
            urlObj.searchParams.set('comyli_atts', JSON.stringify(window.COMYLI_ENV.atts));
        }
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
    if (typeof COMYLI_ENV !== 'undefined' && COMYLI_ENV.nonce) xhr.setRequestHeader('X-WP-Nonce', COMYLI_ENV.nonce);
    xhr.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            var ts = this.responseText.trim();
            if (typeof ts === 'string' && ts.startsWith('"') && ts.endsWith('"')) {
                try { ts = JSON.parse(ts); } catch(e) {}
            }
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

COMYLI.triggerActiveSync = function() {
    if (COMYLI.syncMode !== 'auto') return;
    
    COMYLI.stopTimers();
    COMYLI.liveSyncInterval = setInterval(COMYLI.performLiveSync, 10000);
    
    if (COMYLI.highPrecisionTimeout) clearTimeout(COMYLI.highPrecisionTimeout);
    COMYLI.highPrecisionTimeout = setTimeout(function() {
        if (COMYLI.syncMode === 'auto') {
            var btn = document.getElementById('btnLiveSync');
            if (btn) {
                btn.innerHTML = '&#128259; Live-Sync (Auto)';
                btn.style.backgroundColor = '#3b82f6';
                btn.style.color = 'white';
            }
            COMYLI.startHeartbeat();
        }
    }, 60000);
};

COMYLI.toggleLiveSync = function(btn) {
    if (COMYLI.syncMode === 'auto') {
        COMYLI.syncMode = 'active';
        btn.innerHTML = '&#128259; Live-Sync (An)';
        btn.style.backgroundColor = '#22c55e'; // Green
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
        btn.style.backgroundColor = '#3b82f6'; // Blue
        btn.style.color = 'white';
        COMYLI.stopTimers();
        COMYLI.startHeartbeat();
    }
};

document.addEventListener('visibilitychange', function() {
    if (COMYLI.syncMode === 'auto' && !document.hidden) {
        COMYLI.performHeartbeat();
    }
});

// Auto-start Live-Sync in Auto mode on page load if the table supports it
function initLiveSyncOnLoad() {
    var btn = document.getElementById('btnLiveSync');
    if (btn) {
        COMYLI.syncMode = 'auto';
        btn.innerHTML = '&#128259; Live-Sync (Auto)';
        btn.style.backgroundColor = '#3b82f6';
        btn.style.color = 'white';
        COMYLI.startHeartbeat();
    }
}

if (document.readyState === 'interactive' || document.readyState === 'complete') {
    initLiveSyncOnLoad();
} else {
    document.addEventListener('DOMContentLoaded', initLiveSyncOnLoad);
}