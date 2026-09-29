<link href="<?= h(asset('vendor/leaflet/leaflet.css')) ?>" rel="stylesheet">
<link href="<?= h(asset('css/map.css')) ?>" rel="stylesheet">
<div id="map"></div>

<form class="bar" id="search" role="search">
  <input id="q" type="text" placeholder="Bertrange" aria-label="Find a village or stop" autocomplete="off" spellcheck="false" maxlength="60" aria-controls="hits" aria-expanded="false">
  <button id="go" type="submit">Find</button>
</form>
<div class="hits" id="hits" role="group" aria-label="Matching stops" hidden></div>

<div class="foot">
  <div class="info">
    <p class="readout" id="readout" role="status" aria-live="polite"></p>
    <p class="traffic" id="legend"></p>
  </div>
</div>
