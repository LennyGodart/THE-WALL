<link href="<?= h(asset('vendor/leaflet/leaflet.css')) ?>" rel="stylesheet">
<link href="<?= h(asset('css/map.css')) ?>" rel="stylesheet">
<div id="map"></div>

<form class="bar" id="search" role="search">
  <input id="q" type="text" placeholder="Esch-sur-Alzette" aria-label="Search for a place" autocomplete="off" spellcheck="false" maxlength="120">
  <button id="go" type="submit">Find</button>
</form>

<div class="foot">
  <div class="info">
    <p class="readout" id="readout" role="status" aria-live="polite"></p>
    <p class="traffic" id="traffic"></p>
  </div>
  <button class="live" id="live" type="button" aria-pressed="true" aria-label="Live flights"><span class="dot" aria-hidden="true"></span><span>Live</span></button>
</div>
