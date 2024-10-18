playlist = [];
now_playing = 0;

function detect_rendering_engine() {
  if (window.navigator.userAgent.includes("Chrome")) {
    // chrome/edge
    $(".full-work-player").addClass("playlist-chrome");
  } else if (window.navigator.userAgent.includes("AppleWebKit")) {
    // safari
    $(".full-work-player").addClass("playlist-safari");
  }
  // default is firefox, don't need to check for it
}

function set_playlist_text_overflow() {
  $(".full-work-player-playlist-name").each(function(index, element) {
    if ($(this).prop("scrollHeight") > $(this).height()) {
      // overflowing, enable fade out gradient
      $(this).addClass("overflow")
    } else {
      // remove if no longer needed
      $(this).removeClass("overflow")
    }
  });
}

function populate_playlist_ui(audio_element, index) {
  let duration = new Date(Math.round(audio_element.duration) * 1000).toISOString().slice(11, 19);
  $($(".full-work-player-playlist-length")[index]).text(duration)

  if (index === 0) {
    // populate now playing
    let title = $($(".full-work-player-playlist-name")[index]).text();
    $(".full-work-player-now-playing span").text(`Now Playing: ${title}`);
  }
}

function switch_track(new_track, autoplay = true) {
  let all_streams = $(".full-work-player-streaming-item");

  // Ensure all players are paused and reset
  for (audio of playlist) {
    audio.pause();
    audio.currentTime = 0;
  }

  // Switch visible player
  $(all_streams).addClass("not-playing");
  $(all_streams[new_track]).removeClass("not-playing");

  // Populate now playing
  let title = $($(".full-work-player-playlist-name")[new_track]).text();
  $(".full-work-player-now-playing span").text(`Now Playing: ${title}`);

  // Disable/enable prev + next buttons
  if (new_track == 0) {
    $("#player-prev").prop('disabled', true);
  } else {
    $("#player-prev").prop('disabled', false);
  }
  if (playlist.length - 1 == new_track) {
    $("#player-next").prop('disabled', true);
  } else {
    $("#player-next").prop('disabled', false);
  }

  // Skip if hidden
  if ($(all_streams[new_track]).hasClass("d-none")) {
    let next = new_track + 1;
    if (playlist.length - 1 == next) {
      next = 0;
    }
    now_playing = new_track;
    switch_track(next, autoplay);
    return;
  }

  // Begin playback
  if (autoplay) {
    playlist[new_track].play();
  }
  now_playing = new_track;
}

function skip_hidden_tracks() {
  let all_streams = $(".full-work-player-streaming-item");
  let current = now_playing;

  while (true) {
    // This track has been hidden! skip it
    if ($(all_streams[current]).hasClass("d-none")) {
      if (playlist.length - 1 == current + 1) {
        // None of the tracks are visible, give up
        return;
      }
      // Try the next track
      current++;
      continue;
    }

    // A visible track has been found!
    break;
  }

  if (current !== 0) {
    switch_track(current, false);
  }
}

function collect_playlist_data() {
  let files_loaded = 0;
  let total_files = $(".full-work-player-streaming-item audio").length;
  $(".full-work-player-streaming-item audio").each(function(index, element) {
    audio_element = $(this)[0];
    playlist[index] = audio_element;

    // if metadata is already loaded, collect it
    if (audio_element.readyState != 0) {
      populate_playlist_ui(audio_element, index);
    }

    files_loaded++;
    if (files_loaded === total_files) {
      skip_hidden_tracks();
    }

    $(this).on({
      // if metadata wasn't already ready, collect it when it becomes available
      loadedmetadata: function() {
        populate_playlist_ui($(this)[0], index);
      },
      // at the end of a track, play the next one
      ended: function() {
        let audio_element = $(this)[0];

        // playback hasn't yet reached end of file
        if (audio_element.duration != audio_element.currentTime) {
          return;
        }

        // this is the last track in the playlist
        if (playlist.length - 1 == now_playing) {
          return;
        }

        switch_track(now_playing + 1);
      },
      // whenever playback starts, pause all other tracks, just incase
      play: function() {
        for (const [i, audio] of playlist.entries()) {
          if (i != index) {
            audio.pause();
            audio.currentTime = 0;
          }
        }
      }
    });
  });
}

function create_playlist_track_events() {
  $(".full-work-player-playlist button").each(function(index, element) {
    $(this).on("click", function() {
      switch_track(index);
    })
  })
}

$(document).ready(function() {
  detect_rendering_engine();
  set_playlist_text_overflow();
  collect_playlist_data();
  create_playlist_track_events();
  $("#player-prev").prop('disabled', true);
});

$(window).on("resize", function() {
  set_playlist_text_overflow();
});

$("#player-prev").on("click", function() {
  if (now_playing == 0) {
    return;
  }

  switch_track(now_playing - 1);
})

$("#player-next").on("click", function() {
  if (playlist.length - 1 == now_playing) {
    return;
  }

  switch_track(now_playing + 1);
})