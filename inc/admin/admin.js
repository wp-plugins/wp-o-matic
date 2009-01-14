/*
 * jqModal - Minimalist Modaling with jQuery
 *   (http://dev.iceburg.net/jquery/jqModal/)
 *
 * Copyright (c) 2007,2008 Brice Burgess <bhb@iceburg.net>
 * Dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *   http://www.gnu.org/licenses/gpl.html
 * 
 * $Version: 07/06/2008 +r13
 */
(function($) {
$.fn.jqm=function(o){
var p={
overlay: 50,
overlayClass: 'jqmOverlay',
closeClass: 'jqmClose',
trigger: '.jqModal',
ajax: F,
ajaxText: '',
target: F,
modal: F,
toTop: F,
onShow: F,
onHide: F,
onLoad: F
};
return this.each(function(){if(this._jqm)return H[this._jqm].c=$.extend({},H[this._jqm].c,o);s++;this._jqm=s;
H[s]={c:$.extend(p,$.jqm.params,o),a:F,w:$(this).addClass('jqmID'+s),s:s};
if(p.trigger)$(this).jqmAddTrigger(p.trigger);
});};

$.fn.jqmAddClose=function(e){return hs(this,e,'jqmHide');};
$.fn.jqmAddTrigger=function(e){return hs(this,e,'jqmShow');};
$.fn.jqmShow=function(t){return this.each(function(){$.jqm.open(this._jqm,t);});};
$.fn.jqmHide=function(t){return this.each(function(){$.jqm.close(this._jqm,t)});};

$.jqm = {
hash:{},
open:function(s,t){var h=H[s],c=h.c,cc='.'+c.closeClass,z=(parseInt(h.w.css('z-index'))),z=(z>0)?z:3000,o=$('<div></div>').css({height:'100%',width:'100%',position:'fixed',left:0,top:0,'z-index':z-1,opacity:c.overlay/100});if(h.a)return F;h.t=t;h.a=true;h.w.css('z-index',z);
 if(c.modal) {if(!A[0])L('bind');A.push(s);}
 else if(c.overlay > 0)h.w.jqmAddClose(o);
 else o=F;

 h.o=(o)?o.addClass(c.overlayClass).prependTo('body'):F;
 if(ie6){$('html,body').css({height:'100%',width:'100%'});if(o){o=o.css({position:'absolute'})[0];for(var y in {Top:1,Left:1})o.style.setExpression(y.toLowerCase(),"(_=(document.documentElement.scroll"+y+" || document.body.scroll"+y+"))+'px'");}}

 if(c.ajax) {var r=c.target||h.w,u=c.ajax,r=(typeof r == 'string')?$(r,h.w):$(r),u=(u.substr(0,1) == '@')?$(t).attr(u.substring(1)):u;
  r.html(c.ajaxText).load(u,function(){if(c.onLoad)c.onLoad.call(this,h);if(cc)h.w.jqmAddClose($(cc,h.w));e(h);});}
 else if(cc)h.w.jqmAddClose($(cc,h.w));

 if(c.toTop&&h.o)h.w.before('<span id="jqmP'+h.w[0]._jqm+'"></span>').insertAfter(h.o);	
 (c.onShow)?c.onShow(h):h.w.show();e(h);return F;
},
close:function(s){var h=H[s];if(!h.a)return F;h.a=F;
 if(A[0]){A.pop();if(!A[0])L('unbind');}
 if(h.c.toTop&&h.o)$('#jqmP'+h.w[0]._jqm).after(h.w).remove();
 if(h.c.onHide)h.c.onHide(h);else{h.w.hide();if(h.o)h.o.remove();} return F;
},
params:{}};
var s=0,H=$.jqm.hash,A=[],ie6=$.browser.msie&&($.browser.version == "6.0"),F=false,
i=$('<iframe src="javascript:false;document.write(\'\');" class="jqm"></iframe>').css({opacity:0}),
e=function(h){if(ie6)if(h.o)h.o.html('<p style="width:100%;height:100%"/>').prepend(i);else if(!$('iframe.jqm',h.w)[0])h.w.prepend(i); f(h);},
f=function(h){try{$(':input:visible',h.w)[0].focus();}catch(_){}},
L=function(t){$()[t]("keypress",m)[t]("keydown",m)[t]("mousedown",m);},
m=function(e){var h=H[A[A.length-1]],r=(!$(e.target).parents('.jqmID'+h.s)[0]);if(r)f(h);return !r;},
hs=function(w,t,c){return w.each(function(){var s=this._jqm;$(t).each(function() {
 if(!this[c]){this[c]=[];$(this).click(function(){for(var i in {jqmShow:1,jqmHide:1})for(var s in this[i])if(H[this[i][s]])H[this[i][s]].w[i](this);return F;});}this[c].push(s);});});};
})(jQuery);

Object.extend(String.prototype, {
	toSlug: function() {
    return this.toLowerCase().replace(/\W/g, ' ').replace(/\ +/g, '-').replace(/\-$/g, '').replace(/^\-/g, '');
  },
	
	test: function(regex, params) {
		return ((typeof regex == 'string') ? new RegExp(regex, params) : regex).test(this);		
	}
})

var finishedFetch = function(id) {
	$('campaign_process-' + id).addClassName('finished');
	// close modal window
	
	checkFetchCampaign();
};

var checkFetchCampaign = function() {
	$$('.campaigns_fetch li').each(function(el) {
		if(! el.hasClassName('finished')) {
			fetchCampaign(el.id.replace('campaign_process-', ''));
			return;
		}		
	});
};

var fetchCampaign = function(id) {
	var campaign = $('campaign_process-' + id);
	// set timer to 5 seconds
	var timer = $(document.createElement('p'));
	timer.addClassName('timer');
	timer.innerHTML = '5';
	campaign.appendChild(timer);	
	new PeriodicalExecuter(function(p, i) { timer.innerHTML = 5 - i; if(i == 5) { 
		p.stop();
		fetchCampaignNow(id);
	} }, 1);
};

var fetchCampaignNow = function(id) {
	var campaign = $('campaign_process-' + id);
	var iframe = $(document.createElement("iframe"));
	iframe.src = campaign.getElementsByClassName('.start_process').href;
	iframe.width = '200';
	iframe.height = '100';
	iframe.addClassName('campaign_iframe');	
	campaign.appendChild(iframe);
}

Event.observe( window, 'load', function(){
		
	// Fetch campaigns with iframe (only jquery)
	if(typeof jQuery !== 'undefined') {
		$$('.fetch').each(function(el) {
			Element.observe(el, 'click', function(event) {
				// add new iframe and 
				if(! el.fetchdiv) {
					var fetchdiv = $(document.createElement('div'));
					fetchdiv.addClassName('fetch_window');
					fetchdiv.innerHTML = WPOMATIC_TEXT_LOADING;
					
					var oncomplete = function(html) { 						
	          var t = typeof t === 'string' ? t : t.responseText;
						fetchdiv.innerHTML = t; 
					};
					
					// make ajax call to bring the campaign
					if(typeof jQuery !== 'undefined')
	          jQuery.post("admin-ajax.php", {action: "fetch-feed", id: el.rel, 'cookie': encodeURIComponent(document.cookie)}, oncomplete);
	        else if(typeof Ajax !== 'undefined')
	          new Ajax.Request("admin-ajax.php", { method: "post", parameters: "action=fetch-feed&id="+el.rel+'&cookie=' +  encodeURIComponent(document.cookie), onComplete: oncomplete })
	        else
	          return false;
					
					jQuery(el.fetchdiv).jqm({ overlay: 0 });
				}
				jQuery(el.fetchdiv).jqmShow();
				Event.stop(event);
			});
		});
	}
	$$('.start_process').each(function(el) {
		var campaign_id = el.parentNode.parentNode.replace('campaign_process-', '');
		Element.observe(el, 'click', function(event) {
			fetchCampaign(campaign_id);
			Event.stop(event);
		});
	});
	checkFetchCampaign();
		
	if($('edit_tabs')) {
		$$('#edit_tabs a').each(function(el){  
			Event.observe(el, 'click', function(event){ 
				Element.removeClassName($$('#edit_tabs .current').first(), 'current');
				Element.addClassName(el.parentNode, 'current')      
				                                                                           
				Element.removeClassName($$('#edit_sections .current').first(), 'current');
				Element.addClassName($('section_' + el.id.replace('tab_', '')), 'current');   
				                           
				Event.stop(event);
			}, false);
		});             
		
		// Basic tab
		Event.observe('campaign_title', 'keyup', function(){
	    $('campaign_slug').value = $F('campaign_title').toSlug();
		});
		       
		// Feeds tab
		
		//- Test feed links
		var check_feed = function(el) {
		  el.className = 'input_text';
      if($F(el).length > 0)
      {
        var oncomplete = function(t) {
          var t = typeof t === 'string' ? t : t.responseText;
          el.className = (t == '1') ? 'ok input_text' : 'err input_text';
        };
        if(typeof jQuery !== 'undefined')
          jQuery.post("admin-ajax.php", {action: "test-feed", url: el.value, 'cookie': encodeURIComponent(document.cookie)}, oncomplete);
        else if(typeof Ajax !== 'undefined')
          new Ajax.Request("admin-ajax.php", { method: "post", parameters: "action=test-feed&url="+el.value+'&cookie=' +  encodeURIComponent(document.cookie), onComplete: oncomplete })
        else
          return false;
          
        el.className = 'load input_text';
      }
		};
		
    var update_feeds = function() {
      $$('#edit_feed div input[type=text]').each(function(el){
        Event.stopObserving(el, 'blur');
        Event.stopObserving(el, 'focus');
        
        Event.observe(el, 'focus', function(e){
          el.className = 'input_text';          
        });
        
        Event.observe(el, 'blur', function(e){
          check_feed(el);
        });
      });
    };
    
    update_feeds();
		
		//- Add feed link
		feed_index = $$('#edit_feed label').length;
		Event.observe('add_feed', 'click', function(){    
		  feed_index++;                 
			var label = $$('#edit_feed label').first().innerHTML;			
			new Insertion.Bottom('edit_feed',  '<div class="inlinetext"><label for="campaign_feed_new_'+feed_index+'">'+ label + '</label> <input type="text" name="campaign_feed[new][]" id="campaign_feed_new_'+feed_index+'" />');																				
			$$('#edit_feed input').last().focus();				
			update_feeds();											
		}, false);                           
		
		Event.observe('test_feeds', 'click', function(e){
		  Event.stop(e);
		  $$('#edit_feed input').each(function(el){ check_feed(el); });
		});
		
		// Categories
		Event.observe('quick_add', 'click', function(){
			new Insertion.Bottom('categories', '<li><input type="checkbox" checked="checked" name="campaign_newcat[]" /> <input type="text" name="campaign_newcatname[]" class="input_text" /></li>');
			$$('#categories input').last().focus();															
		}, false);     
		
		// Rewrite
		var rewrite_index = 2;
		var rewrite_keys = function(){
		  $$('#edit_words .rewrite textarea', '#edit_words .relink textarea').each(function(area){
        var check = '';
        var inputs = $A(area.parentNode.getElementsByTagName('INPUT'));
        inputs.each(function(input){
          if(input.type.toLowerCase() == 'checkbox')
            check = input;
        });
        
        Event.stopObserving(area, 'keyup');
        Event.observe(area, 'keyup', function(){
          check.checked = (area.value.length > 0);
        });
		  });
		};
		
		rewrite_keys();
		
		Event.observe('add_word', 'click', function(e){
		  Event.stop(e);
		  rewrite_index++;
		  var originvar = $('edit_words').getElementsBySelector('.origin label').first().innerHTML;
		  var regexvar = $('edit_words').getElementsBySelector('.origin .regex span').first().innerHTML;
		  var rewritevar = $('edit_words').getElementsBySelector('.rewrite label span').last().innerHTML;
      var relinkvar = $('edit_words').getElementsBySelector('.relink label span').last().innerHTML;
      
      var li = document.createElement('LI');
      li.innerHTML = '<div class="textarea"><label>'+originvar+'</label><textarea name="campaign_word_origin[new'+rewrite_index+']"></textarea><label class="regex"><input type="checkbox" name="campaign_word_option_regex[new'+rewrite_index+']" /> '+regexvar+'</label></div><div class="rewrite textarea"><label><input type="checkbox" value="1" name="campaign_word_option_rewrite[new'+rewrite_index+']" /> '+rewritevar+'</label><textarea name="campaign_word_rewrite[new'+rewrite_index+']"></textarea></div><div class="relink textarea"><label><input type="checkbox" value="1" name="campaign_word_option_relink[new'+rewrite_index+']" /> '+relinkvar+'</label><textarea name="campaign_word_relink[new'+rewrite_index+']"></textarea></div>';
      li.className = 'word';      
      $('edit_words').appendChild(li);

		  rewrite_keys();
		});
		
		// - Options
		Event.observe('campaign_templatechk', 'click', function(){
			if(!$('campaign_templatechk').checked) Element.removeClassName('post_template', 'current') 
			else Element.addClassName('post_template', 'current');	
		}, false);
		
		Event.observe('enlarge_link', 'click', function() {
		  Element.toggleClassName('campaign_template', 'large');
		  return false;
		}, false);
	}  
	   
	$$('a.help_link').each(function(el){
	  Event.observe(el, 'click', function(event){
	    window.open(el.href, 'popup', 'width=450,height=400,top=' + (screen.height - 400)/2 + ',left=' + (screen.width - 450)/2+',scrollbars=1,menubar=0,toolbar=0');
	    Event.stop(event);			
	  }, false);
	});
  
  if($('option_cachepath'))
    Event.observe('option_cachepath', 'keyup', function(){
      $('cachepath_input').innerHTML = $F(this);
    });
	
	$$('.check a').each(function(el){
	  el.checked = true;
	  Event.observe(el, 'click', function(e){
	    Event.stop(e);
	    el.checked = !el.checked;
	    var inputs = $A(el.parentNode.parentNode.getElementsByTagName('INPUT'));
	    inputs.each(function(i){ i.checked = el.checked; });
	  });
	});
	
	// setup steps
	if($('wpo-section-setup'))
	{
	  var stepsnum = $A($('setup_steps').getElementsByTagName('LI')).length;
	  var current = $('setup_steps').getElementsBySelector('.step_current').first();
	  var current_index = parseInt(current.id.replace('step_', ''));
	  
	  var enable_button = function(input) {
	    var input = $(input);
	    input.disabled = false;
	    Element.removeClassName(input, 'disabled');
	  }
	  
	  var disable_button = function(input) {
	    var input = $(input);
	    input.disabled = 'disabled';
	    Element.addClassName(input, 'disabled');
	  }
	  
	  var update_buttons_status = function() {
	    disable_button('setup_button_submit');
	    disable_button('setup_button_next');
	    disable_button('setup_button_previous');
	    if(current_index > 1) enable_button('setup_button_previous');
	    if(current_index < stepsnum) enable_button('setup_button_next');
	    if(current_index == stepsnum) enable_button('setup_button_submit');
	  }
	  
	  var show_page = function(index)
	  {
	    Element.removeClassName('step_' + current_index, 'step_current');
	    current_index = index;	    
	    Element.addClassName('step_' + current_index, 'step_current');
      update_buttons_status();  
      $('current_indicator').innerHTML = index;
	  }
	  
	  Event.observe('setup_button_next', 'click', function(){
	    if(current_index < stepsnum ) show_page(current_index + 1);
	  });
	  
	  Event.observe('setup_button_previous', 'click', function(){
	    if(current_index > 1) show_page(current_index - 1);
	  });
	}
	       
	if($('import_mode_2'))
  	Event.observe('import_custom_campaign', 'change', function(){ $('import_mode_2').checked = true });  	
  	
  if($('import_mode_3'))
	  Event.observe('import_new_campaign', 'keyup', function(){ $('import_mode_3').checked = true });		                                                                  
}, false );