(function(){
  'use strict';
  function initUserMenu(shell){
    var button=shell.querySelector('.elev8-app-shell__user-button');
    var menu=shell.querySelector('.elev8-app-shell__menu');
    if(!button||!menu){return;}
    function close(){menu.hidden=true;button.setAttribute('aria-expanded','false');}
    button.addEventListener('click',function(event){event.stopPropagation();var open=menu.hidden;menu.hidden=!open;button.setAttribute('aria-expanded',open?'true':'false');});
    document.addEventListener('click',function(event){if(!shell.contains(event.target)){close();}});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'){close();}});
  }
  function initCommandPalette(){
    var palette=document.querySelector('[data-elev8-command-palette]');
    var input=palette&&palette.querySelector('[data-elev8-command-input]');
    var results=palette&&palette.querySelector('[data-elev8-command-results]');
    var status=palette&&palette.querySelector('[data-elev8-command-status]');
    var openers=document.querySelectorAll('[data-elev8-command-open]');
    if(!palette||!input||!results||!openers.length){return;}
    var activeIndex=-1,requestId=0,timer=null,lastFocus=null;
    function escapeHtml(value){var div=document.createElement('div');div.textContent=value||'';return div.innerHTML;}
    function close(){palette.hidden=true;document.body.classList.remove('elev8-command-open');input.value='';results.innerHTML='';activeIndex=-1;if(lastFocus&&lastFocus.focus){lastFocus.focus();}}
    function open(){lastFocus=document.activeElement;palette.hidden=false;document.body.classList.add('elev8-command-open');window.setTimeout(function(){input.focus();search('');},20);}
    function items(){return Array.prototype.slice.call(results.querySelectorAll('[data-command-result]'));}
    function setActive(index){var list=items();if(!list.length){activeIndex=-1;return;}activeIndex=Math.max(0,Math.min(index,list.length-1));list.forEach(function(item,i){item.classList.toggle('is-active',i===activeIndex);item.setAttribute('aria-selected',i===activeIndex?'true':'false');});list[activeIndex].scrollIntoView({block:'nearest'});}
    function render(list){results.innerHTML='';activeIndex=-1;if(!list.length){results.innerHTML='<div class="elev8-command-palette__empty">'+escapeHtml((window.Elev8OSCommandPalette||{}).emptyMessage||'No results found.')+'</div>';return;}list.forEach(function(item){var link=document.createElement('a');link.href=item.url;link.className='elev8-command-palette__result';link.setAttribute('data-command-result','');link.setAttribute('role','option');link.setAttribute('aria-selected','false');link.innerHTML='<span class="elev8-command-palette__icon">'+escapeHtml(item.icon||'→')+'</span><span class="elev8-command-palette__copy"><strong>'+escapeHtml(item.label)+'</strong><small>'+escapeHtml(item.description||'')+'</small></span><span class="elev8-command-palette__type">'+escapeHtml(item.type||item.group||'command')+'</span>';link.addEventListener('mouseenter',function(){setActive(items().indexOf(link));});results.appendChild(link);});setActive(0);}
    function search(query){var config=window.Elev8OSCommandPalette||{};if(!config.ajaxUrl){return;}var id=++requestId;status.textContent=query?'Searching Elev8 OS…':'Quick actions';var url=config.ajaxUrl+'?action=elev8_os_command_search&nonce='+encodeURIComponent(config.nonce||'')+'&q='+encodeURIComponent(query);fetch(url,{credentials:'same-origin'}).then(function(response){return response.json();}).then(function(payload){if(id!==requestId){return;}if(!payload||!payload.success){throw new Error('search_failed');}var list=(payload.data&&payload.data.results)||[];status.textContent=query?list.length+' result'+(list.length===1?'':'s'):'Quick actions';render(list);}).catch(function(){if(id!==requestId){return;}status.textContent=config.errorMessage||'Search is temporarily unavailable.';render([]);});}
    openers.forEach(function(button){button.addEventListener('click',open);});palette.querySelectorAll('[data-elev8-command-close]').forEach(function(button){button.addEventListener('click',close);});input.addEventListener('input',function(){window.clearTimeout(timer);timer=window.setTimeout(function(){search(input.value.trim());},160);});input.addEventListener('keydown',function(event){var list=items();if(event.key==='ArrowDown'){event.preventDefault();setActive(activeIndex+1);}if(event.key==='ArrowUp'){event.preventDefault();setActive(activeIndex-1);}if(event.key==='Enter'&&list[activeIndex]){event.preventDefault();list[activeIndex].click();}});document.addEventListener('keydown',function(event){var target=event.target;var typing=target&&(/INPUT|TEXTAREA|SELECT/.test(target.tagName)||target.isContentEditable);if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='k'){event.preventDefault();palette.hidden?open():close();}else if(event.key==='Escape'&&!palette.hidden){event.preventDefault();close();}else if(event.key==='/'&&!typing&&palette.hidden){event.preventDefault();open();}});
  }
  document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-elev8-app-shell]').forEach(initUserMenu);initCommandPalette();});
})();
