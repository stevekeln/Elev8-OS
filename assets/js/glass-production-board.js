(function(){
  'use strict';
  var board=document.querySelector('[data-board]');
  if(!board||typeof Elev8GlassBoard==='undefined'){return;}
  var dragged=null;
  function request(card,status,assigned){
    var body=new URLSearchParams();
    body.set('action','elev8_os_move_glass_job');body.set('nonce',Elev8GlassBoard.nonce);
    body.set('job_id',card.dataset.jobId);body.set('status',status);body.set('assigned_user_id',assigned);
    var note=card.querySelector('[data-card-status]'); if(note){note.textContent='Saving…';}
    return fetch(Elev8GlassBoard.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
      .then(function(r){return r.json();}).then(function(data){if(!data.success){throw new Error(data.data&&data.data.message?data.data.message:Elev8GlassBoard.errorMessage);} if(note){note.textContent='Saved';setTimeout(function(){note.textContent='';},1800);} return data;})
      .catch(function(err){if(note){note.textContent=err.message;} throw err;});
  }
  board.addEventListener('dragstart',function(e){var card=e.target.closest('[data-job-id]');if(!card){return;}dragged=card;card.classList.add('is-dragging');e.dataTransfer.effectAllowed='move';});
  board.addEventListener('dragend',function(){if(dragged){dragged.classList.remove('is-dragging');}dragged=null;document.querySelectorAll('.is-drag-over').forEach(function(el){el.classList.remove('is-drag-over');});});
  board.addEventListener('dragover',function(e){var zone=e.target.closest('[data-dropzone]');if(!zone||!dragged){return;}e.preventDefault();zone.classList.add('is-drag-over');});
  board.addEventListener('dragleave',function(e){var zone=e.target.closest('[data-dropzone]');if(zone){zone.classList.remove('is-drag-over');}});
  board.addEventListener('drop',function(e){var zone=e.target.closest('[data-dropzone]');if(!zone||!dragged){return;}e.preventDefault();zone.classList.remove('is-drag-over');var column=zone.closest('[data-status]');var status=column.dataset.status;var oldZone=dragged.parentNode;var select=dragged.querySelector('[data-status-select]');var assigned=dragged.querySelector('[data-assignee]').value;zone.appendChild(dragged);if(select){select.value=status;}request(dragged,status,assigned).catch(function(){oldZone.appendChild(dragged);});});
  board.addEventListener('click',function(e){var btn=e.target.closest('[data-save-card]');if(!btn){return;}var card=btn.closest('[data-job-id]');var status=card.querySelector('[data-status-select]').value;var assigned=card.querySelector('[data-assignee]').value;request(card,status,assigned).then(function(){var target=board.querySelector('[data-status="'+CSS.escape(status)+'"] [data-dropzone]');if(target&&card.parentNode!==target){target.appendChild(card);}});});
})();
