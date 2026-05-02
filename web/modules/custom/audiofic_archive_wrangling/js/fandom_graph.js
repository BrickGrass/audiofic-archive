(function (Drupal) {
  'use strict';

  Drupal
})



import cytoscape from 'cytoscape';

// TODO: Wait until document is ready!
var cy = cytoscape({
    container: document.getElementById('fandomGraph')
});

// TODO: Actually build API endpoints to query to fetch
// a fandom graph.