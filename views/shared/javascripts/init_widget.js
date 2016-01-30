function __log(e, data) {
  log.innerHTML += "\n" + e + " " + (data || '');
}

function setStatus(status,tooltip) {
  jQuery('#ar-status').html(status);
  jQuery('div#ar-widget').attr('data-tooltip',tooltip);
}

var audio_context;
var recorder;
var animationID;

function startUserMedia(stream) {
  var input = audio_context.createMediaStreamSource(stream);
  __log('Media stream created.' );
  __log("input sample rate " +input.context.sampleRate);

  // Feedback!
  //input.connect(audio_context.destination);
  __log('Input connected to audio context destination.');

  recorder = new Recorder(input, {
    numChannels: 1
  });
  __log('Recorder initialised.');
  ready();
  setTimeout(function(){
    recorder && recorder.record();
    startRecording();
  }, 300);
}

function pauseRecording(button) {
  clearInterval(animationID);
  jQuery('#ar-pause-btn').unbind('click').click(function(){unpauseRecording(this)});
  setStatus("Recording paused...","Your recording is now paused. Press the pause button again to continue recording on the same file, or press stop to process this file and begin a new one.");
}

function unpauseRecording(button) {
  jQuery('#ar-pause-btn').unbind();
  jQuery('#ar-pause-btn').click(function(){
    pauseRecording(this);
  });
  startRecording(button);
}

function requestMic(div) {
  jQuery('div#ar-widget').prop('onclick',null).off('click');
  jQuery('#ar-widget').addClass("disabled");
  setStatus('Waiting for microphone',"Your browser should now be requesting access to your computer's microphone. You may see a popup dialog box asking whether to grant this permission. Please click Yes if you would like to use this tool.");
  establishAudioContext();
}

function fail(message) {

}

function ready() {
  jQuery('div#ar-widget').removeClass('disabled');
  jQuery('button#ar-record-btn').prop('disabled',false);
  jQuery('button#ar-record-btn').unbind('click').click(function(){
    recorder && recorder.record();
    startRecording(this);
  });
  jQuery('button#ar-pause-btn').prop('disabled',true);
  jQuery('button#ar-stop-btn').prop('disabled',true);
  setStatus("Ready","Click record to begin recording audio!");
}

function startRecording(button) {
  recordBtn = jQuery('#ar-record-btn');
  recordBtn.prop('disabled',true);
  recordBtn.siblings().prop('disabled',false);
  animationID = setInterval(function(){
    recordBtn.animate({fontSize:'0'},250,"linear",function(){
      recordBtn.animate({fontSize:'30px'},250);
    });
  },600);
  __log('Recording...');
  setStatus('Recording...',"You are now recording audio. After you press stop you will have an opportunity to listen to or save the recording, and choose whether to upload it to our server.");
  
}

function stopRecording(button) {
  clearInterval(animationID);
  recorder && recorder.stop();
  button.disabled = true;
  button.previousElementSibling.disabled = false;
  __log('Stopped recording.');
  setStatus('Stopped recording.',"You have completed recording an audio segment. The segement will now be converted to mp3 and should appear below. When the conversion is complete you can proceed to upload the file to our servers.");
  setTimeout(ready(), 2000);

  // create WAV download link using audio data blob
  createDownloadLink();

  recorder.clear();
}

function createDownloadLink() {
  //TODO append to recordingsList here
  // and add a disabled entry with 
  // a "converting to mp3" status entry
  recorder && recorder.exportWAV(function(blob) {
    /*var url = URL.createObjectURL(blob);
      var li = document.createElement('li');
      var au = document.createElement('audio');
      var hf = document.createElement('a');

      au.controls = true;
      au.src = url;
      hf.href = url;
      hf.download = new Date().toISOString() + '.wav';
      hf.innerHTML = hf.download;
      li.appendChild(au);
      li.appendChild(hf);
      recordingslist.appendChild(li);*/
  });
}

function establishAudioContext() {
  try {
    // webkit shim
    window.AudioContext = window.AudioContext || window.webkitAudioContext;
    navigator.getUserMedia = ( navigator.getUserMedia ||
                               navigator.webkitGetUserMedia ||
                               navigator.mozGetUserMedia ||
                               navigator.msGetUserMedia);
    window.URL = window.URL || window.webkitURL;

    audio_context = new AudioContext;
    __log('Audio context set up.');
    __log('navigator.getUserMedia ' + (navigator.getUserMedia ? 'available.' : 'not present!'));
  } catch (e) {
    console.log(e);
    alert('No web audio support in this browser!');
  }

  navigator.getUserMedia({audio: true}, startUserMedia, function(e) {
    __log('No live audio input: ' + e);
  });
}

window.onload = function init() {
  jQuery('#ar-record-btn').click(function(){
    requestMic(this);
  });
  jQuery('#ar-pause-btn').click(function(){
    pauseRecording(this);
  });
//  establishAudioContext();
};
