(function () {
  'use strict';
  if (!window.elev8DeviceSession || !window.fetch) return;
  var key = 'elev8_os_device_id_v1';
  var id = '';
  try { id = window.localStorage.getItem(key) || ''; } catch (e) {}
  if (!id) {
    id = (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : ('dev-' + Date.now() + '-' + Math.random().toString(36).slice(2));
    try { window.localStorage.setItem(key, id); } catch (e) {}
  }
  var last = 0;
  try { last = parseInt(window.localStorage.getItem(key + '_seen') || '0', 10); } catch (e) {}
  var now = Date.now();
  var refreshKey = key + '_refreshed';
  var refreshed = 0;
  try { refreshed = parseInt(window.localStorage.getItem(refreshKey) || '0', 10); } catch (e) {}
  if (window.elev8DeviceSession.refreshEndpoint && now - refreshed >= (window.elev8DeviceSession.refreshInterval || 43200000)) {
    fetch(window.elev8DeviceSession.refreshEndpoint, {method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':window.elev8DeviceSession.nonce}}).then(function(r){if(r.ok){try{window.localStorage.setItem(refreshKey,String(Date.now()));}catch(e){}}}).catch(function(){});
  }
  if (now - last < 15 * 60 * 1000) return;
  fetch(window.elev8DeviceSession.endpoint, {
    method: 'POST', credentials: 'same-origin',
    headers: {'Content-Type':'application/json','X-WP-Nonce':window.elev8DeviceSession.nonce},
    body: JSON.stringify({device_id:id,user_agent:navigator.userAgent || '',platform:navigator.platform || ''})
  }).then(function (response) {
    if (response.ok) { try { window.localStorage.setItem(key + '_seen', String(Date.now())); } catch (e) {} }
  }).catch(function () {});
}());
