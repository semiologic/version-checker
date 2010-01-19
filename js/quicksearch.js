/**
 * Semiologic Quicksearch components
 * 
 * @author Tom Klingenberg
 */

/**
 * highlighting
 * 
 * based on highlight v3 / MIT license / by Johann Burkard 	<http://johannburkard.de>
 * rewritten, bugfixed and extended by me
 *
 * @author Tom Klingenberg
 */
function sem_highlightLoad() {
	if ( jQuery.fn.sem_highlight ) 
		return; // plugin already loaded
	
	jQuery.fn.sem_highlight = function(action, paramA, paramB, paramC) {
		var defaults = {
			element: 'span',
			cssclass: 'highlight',
			extra: 0,
			extracss: 'term-'
		};								
		switch(action) {
			case 'mark':
				var options = jQuery.extend(defaults, paramC);
				mark(this, paramA, paramB, options);
				break;
			case 'markall':
				var options = jQuery.extend(defaults, paramB);				
				for (key in paramA)
					mark(this, paramA[key], key, options);
				break;
			case 'clear':
				var options = jQuery.extend(defaults, paramA);
				clear(this, options);
				break;
		};
		function mark(callee, pat, number, options) {
			if ( 0 == pat.length) // skip empty pattern 
				return 0;			
			return callee.each(function() {
				var value_cssclass = options.cssclass;
				var value_htmltag  = options.element;
				if ( options.extra ) 
					value_cssclass = value_cssclass + " " + options.extracss + "" + number;				
				innerHighlight(this, pat.toUpperCase(), value_cssclass, value_htmltag);
			});
		};
		function clear(callee, options) {
			return callee.find('span.' + options.cssclass).each(function() {
				this.parentNode.firstChild.nodeName;
				with (this.parentNode) {
					replaceChild(this.firstChild, this);
					normalize();
				}
			 }).end();
		};
		function innerHighlight(node, pat, param_cssclass, param_htmltag) {
			var regex = new RegExp(param_htmltag, 'i');
			if ( (1 == node.nodeType) && ( regex.test(node.tagName)) )
				return 0; // skip already highlighted parts
			
			var skip = 0;
			if (node.nodeType == 3) {
				var pos = node.data.toUpperCase().indexOf(pat);
				if (pos >= 0) {
					var spannode    = document.createElement(param_htmltag);					
					var middlebit   = node.splitText(pos);
					var endbit      = middlebit.splitText(pat.length);
					var middleclone = middlebit.cloneNode(true);
															
					spannode.className = param_cssclass;
																				
					spannode.appendChild(middleclone);
					middlebit.parentNode.replaceChild(spannode, middlebit);
					skip = 1;
				}
			}
			else if (node.nodeType == 1 && node.childNodes && !/(script|style)/i.test(node.tagName)) {
								
				for (var i = 0; i < node.childNodes.length; ++i) {
					i += innerHighlight(node.childNodes[i], pat, param_cssclass, param_htmltag);
				}
			}
			return skip;
		};
		return this;
	}; // sem_highlight jQuery plugin
}; // function sem_highlightLoad


/**
 * quicksearch adapter for core admin menu
 */
function sem_qsProviderMenu(options) {
	var defaults = {
			source:   'ul#adminmenu li.wp-has-submenu .wp-submenu li, ul#adminmenu li.menu-top:not(.wp-has-submenu)'
	};
	var options  = jQuery.extend(true, defaults, options);
	var dynparent = new sem_qsProviderDefault(options);
	jQuery.extend(this, dynparent);
	this.termlast = '';
	
	this.onSearch = function(term, qsobj) {
		qsobj.provider.termlast = term;
		if ( typeof(qsobj.openedElements) == 'undefined') {
			qsobj.openedElements = new Array();							
		}
		if (term.length && 0 == qsobj.openedElements.length) {
			// open all submenus to better search them on start
			jQuery('ul#adminmenu li.wp-has-submenu .wp-submenu').each(function(i){
				var el = this;
				if ( jQuery(el).is(':hidden') ) {
					jQuery(el).show().parent().toggleClass('wp-menu-open');
					qsobj.openedElements.push(el);
				}
			});			
			jQuery('ul#adminmenu .wp-menu-separator').hide();
			
		} else if (0 == term.length) {
			// close them afterwards
			var key = 0;
			for (key in qsobj.openedElements) {
				var el = qsobj.openedElements[key];
				if ( jQuery(el).is(':visible') ) {									
					jQuery(el).hide().parent().toggleClass('wp-menu-open');
				}	
			}
			jQuery('ul#adminmenu .wp-menu-separator').show();
			qsobj.openedElements = new Array();
		}				
	},
	
	this.getindex = function() {
		var indexed = new Object();
		jQuery(options.source).each(function(i) {
			var text = jQuery(this).parent().prev().text() + " " + jQuery(this).text();
			indexed[i] = text;
		});
		last_index = indexed;
		return indexed;
	}; // function index
	
	this.show = function(list) {
		jQuery(options.source).each(function(i) {
			if ( typeof(list[i]) == 'undefined' ) {
				list[i] = false;
			}
			jQuery(this).toggle( list[i] );
		});
		
		
		// first test that there is something to test right now (going in)
		var term = this.termlast;
		
		jQuery('ul#adminmenu li.wp-has-submenu .wp-submenu').each(function(i) {			
			var el = jQuery(this);
			var tohide = ( ( el.height() < 2 ) && ( term.length > 0 ) )
			var subelements = 'div.wp-menu-image, div.wp-menu-toggle, a.menu-top';
						
			if (tohide) {
				el.parent().css('height', '0');
				el.parent().css('min-height', '0');
				el.parent().css('overflow', 'hidden');
				el.parent().find(subelements).hide();				
			} else {
				el.parent().css('height', '');
				el.parent().css('min-height', '');
				el.parent().css('overflow', '');
				el.parent().find(subelements).show();
			}
		});
		jQuery('ul#adminmenu li.menu-top:not(.wp-has-submenu)').each(function(i) {
			var el = jQuery(this);
		});
		 
	}; // function show
	
	this.highlight = function(terms, highlightoptions) {
		jQuery(options.source).add(jQuery('ul#adminmenu li.menu-top a.menu-top')).sem_highlight('clear', highlightoptions).sem_highlight('markall', terms, highlightoptions);
	}; // function hightlight
}; // menu

/**
 * quicksearch adapter for core plugin table
 */
function sem_qsProviderPlugin(options) {
	var defaults = {
			source: 'table tbody.plugins tr:even'
	};
	var options  = jQuery.extend(true, defaults, options);
	var dynparent = new sem_qsProviderDefault(options);
	jQuery.extend(this, dynparent);
	
	this.getindex = function() {
		var indexed = new Object();
		jQuery(options.source).each(function(i) {
			var text = jQuery(this).add(jQuery(this).next()).text();
			indexed[i] = text;
		});
		last_index = indexed;
		return indexed;		
	}; // function index
	
	this.show = function(list) {
		jQuery(options.source).each(function(i) {
			if ( typeof(list[i]) == 'undefined' ) {
				list[i] = false;
			}
			jQuery(this).add(jQuery(this).next()).toggle( list[i] );
		});
	}; // function show
	
	this.highlight = function(terms, highlightoptions) {
		jQuery(options.source).parent().sem_highlight('clear', highlightoptions).sem_highlight('markall', terms, highlightoptions);
	}; // function hightlight
}; // plugin

/**
 * quicksearch adapter for the semiologic plugin page
 */
function sem_qsProviderDefault(options) {
	var defaults = {
			source: 'table tbody.plugins tr'
	};	
	
	var options    = jQuery.extend(true, defaults, options);
	var last_index = null;
	
	this.getindex = function() {
		var indexed = new Object();
		jQuery(options.source).each(function(i) {
			var text = jQuery(this).text();
			indexed[i] = text;
		});
		last_index = indexed;
		return indexed;		
	}; // function index
	
	this.index = function() {
		if ( last_index == null )
			return this.getindex();
		return last_index;
	}; // function index
	
	this.show = function(list) {
		jQuery(options.source).each(function(i) {
			if ( typeof(list[i]) == 'undefined' ) {
				list[i] = false;
			}
			jQuery(this).toggle( list[i] );
		});
	}; // function show
	
	this.highlight = function(terms, highlightoptions) {
		jQuery(options.source).sem_highlight('clear', highlightoptions).filter(options.source + ':visible').sem_highlight('markall', terms, highlightoptions);
	}; // function hightlight

}; // sem_qsProviderDefault

/**
 * 
 * @param (object) quicksearch options
 * @return
 */
function sem_qsMain(options) {
	var self     = this;
	this.last    = undefined;	
	var defaults = {
			inputs:     '.sem_quicksearch',
			provider:   'Default',
			options:    { double: 'duty' },			
			doeshighlight: true,
			highlight:  {
							cssclass:'sem_highlight', 
							extra   : 1
						},		
			doesinsert: true,
			insert: 	{							
							anchor: 'div.tablenav div.actions',
							html:   '<!-- quicksearch --><label for="qs" class="screen-reader-text">Quicksearch</label><input type="text" value="" name="qs" class="sem_quicksearch" title="Quicksearch" />'
						},
			doesfocus:  true
		};
	
	this.options = jQuery.extend(true, defaults, options); 
	
	if ( !jQuery('#sem_quicksearch_styles').length ) {
		var css = "span." + this.options.highlight.cssclass + " {background:#f0f; color:#fff;}\n";
		css    += "span." + this.options.highlight.cssclass + ".term-0 {background:#ff0; color:#000;}\n";
		css    += "span." + this.options.highlight.cssclass + ".term-1 {background:#0ff; color:#000;}\n";
		css    += "#adminmenu input.sem_quicksearch_menu {-moz-user-select:text; -webkit-user-select:text;}\n";
		css    += "#adminmenu * {-moz-user-select:-moz-none;}\n";
		css    += ".folded #adminmenu li.menusearch {display: none;}\n";
		css    += "#wphead div.menusearch {float:left; margin:12px 9px 0 15px; width:normal; }\n";		
		css    += "#wphead input.sem_quicksearch_menu {background-color:" + jQuery('#user_info').css('color') + "; }\m;"
		
		jQuery('head').append('<style type="text/css" id="sem_quicksearch_styles"><!--/*--><![CDATA[/*><!--*/' + "\n" + css + '/*]]>*/--></style>');
	};
	
	this.providerFactory = function(name, provideroptions) {
		switch( name ) {
			case 'Menu':
				var instance_provider = new sem_qsProviderMenu(provideroptions);
				break;
			case 'Plugin':
				var instance_provider = new sem_qsProviderPlugin(provideroptions);
				break;
			case 'Default':
			default:
				var instance_provider = new sem_qsProviderDefault(provideroptions);				
		};
		return instance_provider;
	}
	
	this.init = function() {
		var options = self.options;
		if (options.doesinsert) {
			jQuery(options.insert.anchor).prepend(options.insert.html);
			if ( options.insert.afterInsert )
				options.insert.afterInsert(self);
		}
		if ( 0 == jQuery(options.inputs).length )
			return;

		jQuery(options.inputs).keyup( function(e) {
			self.trigger(this.value);			
		});
		self.trigger();
		if (options.doesfocus)
			jQuery(options.inputs).get(0).focus();
		
	}; // init function
	
	/**
	 * search terms in text
	 * 
	 * @param  (int) mode 0: and (must find all terms), 1: or (must find one term)
	 * @return (int) 1 if found, 0 if not
	 */
	this.searchTerms = function(terms, text, mode) {
		var found = 0;
		var count = 0;
		var key   = 0;
		for (key in terms)
			if ( -1 < text.search(new RegExp(terms[key], 'i')) ) {		
				if ( mode ) { // OR search
					found = 1;
					break;
				} else {
					count++;
				}
			}
		if ( count == terms.length )
			found = 1;
		return found;		
	}; // function searchTerms

	this.search = function(term) {		
		var terms    = term.split(' ');
		var provider = this.provider;		
		var list     = provider.index();		
		var found    = new Array();
		var text     = '';
		var key      = 0;
		
		if ( this.options.options.onSearch ) {			
			this.options.options.onSearch(term, this);
		} else if ( this.provider.onSearch )  {
			this.provider.onSearch(term, this);
		}
		
		for (key in list)
			if ( this.searchTerms(terms, list[key]) )				
				found[key] = true;
		
		provider.show(found);
		
		if (this.options.doeshighlight)
			provider.highlight(terms, this.options.highlight);
		
	}; // search function

	this.trigger = function(term) {
		if ( typeof(term) == 'undefined' )
			term = jQuery(this.options.inputs).get(0).value;
		term = this.normalize(term);		
		if ( false == this.changed(term) )
			return;
		this.search(term);
	}; // trigger function
	
	this.changed = function(term) {
		if (this.last == term)
			return false;				
		this.last = term;
		return true;
	}; // changed function
	
	this.normalize = function(term, minlen) {
		if ( minlen == undefined )
				minlen = 1; // 2
		else
				minlen--;		
		term  = term.replace(/^\s+|\s+$/, '');
		term  = term.replace(/(\s{2,})/g, ' ');
		terms = term.split(' ');
		build = new Array();
		for (key in terms)
			if ( (test = terms[key]) && (test.length > minlen) && !this.exists(build, test))
				build.push(test);
		return build.join(' '); 		
	}; // normalize function

	this.exists = function(array, o) {
		for(var i = 0; i < array.length; i++)
		   if(array[i] === o)
		     return true;
		return false;
	}; // exists function

	this.provider = this.providerFactory(this.options.provider, this.options.options);
	sem_highlightLoad();
	jQuery(document).ready(this.init);
}; // function sem_quicksearchMain


// auto-loader
jQuery(document).ready(function(){
	var alone = true;
	if ( jQuery('body.wp-admin.plugins-php').length ) {
		new sem_qsMain({provider: 'Plugin'});
		alone = false;
	}
	if ( jQuery('body.wp-admin.plugin-install-php').length ) {	
		new sem_qsMain({provider: 'Default'});
		alone = false;
	}
	if ( jQuery('body.wp-admin.tools_page_sem-tools').length ) {	
		new sem_qsMain({provider: 'Default'});
		alone = false;
	}
	if ( jQuery('body.wp-admin').length ) {
		// whitelist: index-php
		// blacklist: post-new-php; post-php; edit-tags-php; categories-php; media-new-php; ...
		if (jQuery('input:text').length)
			alone = false;
		if (jQuery('body.index-php').length)
			alone = true;
		new sem_qsMain({
			inputs:   '.sem_quicksearch_menu',
			provider: 'Menu',
			doeshighlight: true,
			doesfocus: alone,
			insert: 	{							
				anchor: '#wphead',
				html:   '<!-- quicksearch --><div class="menusearch"><label for="qsmenu" class="screen-reader-text">Quicksearch</label><input type="text" value="" name="qsmenu" class="sem_quicksearch_menu" title="Quicksearch" /></div>'
			}
		});
	}
});