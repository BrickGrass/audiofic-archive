(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.jPlayerPlaylist = {
    attach: function (context, settings) {
      var playlist = []; // TODO: Populate this array with your MP3 files.

      // Populate the playlist array with the MP3 files.
      if (drupalSettings.audiofic_player && drupalSettings.audiofic_player.mp3Files) {
        for (var i = 0; i < drupalSettings.audiofic_player.mp3Files.length; i++) {
          var file = drupalSettings.audiofic_player.mp3Files[i];
          playlist.push({
            title: file.title,
            mp3: file.url
          });
        }
      }

      new jPlayerPlaylist({
        jPlayer: "#jquery_jplayer_1",
        cssSelectorAncestor: "#jp_container_1"
      }, playlist, {
        swfPath: "https://cdnjs.cloudflare.com/ajax/libs/jplayer/2.9.2/jplayer",
        supplied: "mp3",
        wmode: "window",
        useStateClassSkin: true,
        autoBlur: false,
        smoothPlayBar: true,
        keyEnabled: true,
      });
    }
  };
})(jQuery, Drupal, drupalSettings);