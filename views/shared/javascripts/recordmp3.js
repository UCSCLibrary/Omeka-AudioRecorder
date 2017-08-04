(function(window){

  var WORKER_PATH = AUDIO_RECORDER_JAVASCRIPT_DIR+'recordMP3lib/recorderWorker.js';
  var encoderWorker = new Worker(AUDIO_RECORDER_JAVASCRIPT_DIR+'/recordMP3lib/mp3Worker.js');

  var Recorder = function(source, cfg){
    var config = cfg || {};
    var bufferLen = config.bufferLen || 4096;
    var numChannels = config.numChannels || 2;
    this.context = source.context;
    this.node = (this.context.createScriptProcessor ||
                 this.context.createJavaScriptNode).call(
                   this.context,
                   bufferLen, 
                   numChannels, 
                   numChannels);

    var worker = new Worker(config.workerPath || WORKER_PATH);
    worker.postMessage({
      command: 'init',
      config: {
        sampleRate: this.context.sampleRate,
        numChannels: numChannels
      }
    });
    var recording = false,
        currCallback;

    this.node.onaudioprocess = function(e){
      if (!recording) return;
      var buffer = [];
      for (var channel = 0; channel < numChannels; channel++){
        buffer.push(e.inputBuffer.getChannelData(channel));
      }
      worker.postMessage({
        command: 'record',
        buffer: buffer
      });
    }

    this.configure = function(cfg){
      for (var prop in cfg){
        if (cfg.hasOwnProperty(prop)){
          config[prop] = cfg[prop];
        }
      }
    }

    this.record = function(){
      recording = true;
    }

    this.stop = function(){
      recording = false;
    }

    this.clear = function(){
      worker.postMessage({ command: 'clear' });
    }

    this.getBuffer = function(cb) {
      currCallback = cb || config.callback;
      worker.postMessage({ command: 'getBuffer' })
    }

    this.exportWAV = function(cb, type){
      currCallback = cb || config.callback;
      type = type || config.type || 'audio/wav';
      if (!currCallback) throw new Error('Callback not set');
      worker.postMessage({
        command: 'exportWAV',
        type: type
      });
    }

    function addRecordingLi(){
      var li = document.createElement('li');
      var p = document.createElement('p');
      var au = document.createElement('audio');
      var hf = document.createElement('a');

      p.innerHTML = "Converting recording to MP3...";
      li.appendChild(p);

      au.controls = true;
      au.className += "audio";
      hf.download = 'audio_recording_' + new Date().getTime() + '.mp3';
      hf.innerHTML = '<button class="download" title="Save to your computer">Save</button>';
      li.appendChild(au);
      li.appendChild(hf);

      var up = document.createElement('button');
      up.className += "ar-upload "
      up.innerHTML = "Upload";
      up.title="Upload to the collection";

      li.appendChild(up);

      var del = document.createElement('button');
      del.innerHTML = "X";
      del.onclick = function(){recordingslist.removeChild(li);};
      li.appendChild(del);

      li.className += "ar-recording ";
      li.className += "disabled ";
      jQuery(li).find('button').prop('disabled',true);
      jQuery("h3#recordings").show();
      recordingslist.appendChild(li);

      return li;
    }

    function activateLi(li,url,mp3Blob){
      liObj = jQuery(li);
      liObj.children('.ar-upload').click(function(){
        li.className += " disabled ";
        liObj.find('button').prop('disabled',true);
        showDialog(mp3Blob);
      });
      liObj.children('audio').attr('src',url);
      liObj.children('a').attr('href',url);
      liObj.children('p').html("");
      liObj.removeClass('disabled');
      liObj.find('button').prop('disabled',false);
    }

    //Mp3 conversion
    worker.onmessage = function(e){
      var blob = e.data;
      //console.log("the blob " +  blob + " " + blob.size + " " + blob.type);

      var arrayBuffer;
      var fileReader = new FileReader();

      fileReader.onload = function(){
	arrayBuffer = this.result;
	var buffer = new Uint8Array(arrayBuffer),
            data = parseWav(buffer);
        
	log.innerHTML += "\n" + "Converting to Mp3";
	li = addRecordingLi();

        encoderWorker.postMessage({ cmd: 'init', config:{
          mode : 3,
	  channels:1,
	  samplerate: data.sampleRate,
	  bitrate: data.bitsPerSample
        }});

        encoderWorker.postMessage({ cmd: 'encode', buf: Uint8ArrayToFloat32Array(data.samples) });
        encoderWorker.postMessage({ cmd: 'finish'});
        encoderWorker.onmessage = function(e) {
          if (e.data.cmd == 'data') {
	    var mp3Blob = new Blob([new Uint8Array(e.data.buf)], {type: 'audio/mp3'});
            //uploadAudio(mp3Blob);
            
	    var url = 'data:audio/mp3;base64,'+encode64(e.data.buf);
            activateLi(li,url,mp3Blob);
          }
        };
      };
      fileReader.readAsArrayBuffer(blob);
      currCallback(blob);
    }


    function encode64(buffer) {
      var binary = '',
	  bytes = new Uint8Array( buffer ),
	  len = bytes.byteLength;

      for (var i = 0; i < len; i++) {
	binary += String.fromCharCode( bytes[ i ] );
      }
      return window.btoa( binary );
    }

    function parseWav(wav) {
      function readInt(i, bytes) {
	var ret = 0,
	    shft = 0;

	while (bytes) {
	  ret += wav[i] << shft;
	  shft += 8;
	  i++;
	  bytes--;
	}
	return ret;
      }
      if (readInt(20, 2) != 1) throw 'Invalid compression code, not PCM';
      if (readInt(22, 2) != 1) throw 'Invalid number of channels, not 1';
      return {
	sampleRate: readInt(24, 4),
	bitsPerSample: readInt(34, 2),
	samples: wav.subarray(44)
      };
    }

    function Uint8ArrayToFloat32Array(u8a){
      var f32Buffer = new Float32Array(u8a.length);
      for (var i = 0; i < u8a.length; i++) {
	var value = u8a[i<<1] + (u8a[(i<<1)+1]<<8);
	if (value >= 0x8000) value |= ~0x7FFF;
	f32Buffer[i] = value / 0x8000;
      }
      return f32Buffer;
    }

    function showDialog(mp3Data){
      dialog  = jQuery("#ar-dialog").dialog({
        autoOpen: false,
        height: 513,
        width: 600,
        modal: true,
        buttons: {
          "Upload to Collection": function(){
            var form = jQuery('#ar-upload-form');
  	    var fd = new FormData(form[0]);          
            fd.append('ar-title',jQuery('#ar-title').val());
            fd.append('ar-username',jQuery('#ar-username').val());
            fd.append('ar-email',jQuery('#ar-email').val());
            uploadAudio(mp3Data,fd);
            dialog.dialog( "close" );
          },
          Cancel: function() {
            dialog.dialog( "close" );
            jQuery('ul#recordingslist li').removeClass('disabled');
            jQuery('ul#recordingslist').find('button').prop('disabled',false);
          }
        },
        close: function() {
          jQuery('li.disabled').find('button').prop("disabled",false);
          jQuery('li.disabled').find('button.ar-upload').prop("disabled",true);
          jQuery('ul#recordingslist li').removeClass('disabled');
          jQuery('#ar-upload-form')[0].reset();
          jQuery('input').removeClass( "ui-state-error" );
        }
      });
      dialog.dialog("open");
    }

    function uploadAudio(mp3Blob,fd){
      var reader = new FileReader();
      reader.onload = function(event){
	var mp3Name = encodeURIComponent('audio_recording_' + new Date().getTime() + '.mp3');
	fd.append('fname', mp3Name);
	fd.append('data', event.target.result);
	jQuery.ajax({
	  type: 'POST',
	  url: AUDIO_RECORDER_UPLOAD_URL,
	  data: fd,
	  processData: false,
	  contentType: false
	}).done(function(data) {
          if(data.indexOf("Success") > -1)
	    alert('Upload processed successfully! Your story will appear here once it has been approved by our curators. You may record more stories in the mean time. Please do not submit the same stories repeatedly.');
          else
	    alert('There was a problem uploading your audio file. Please try again, and do not hesitate to contact our support staff if you experience repeated problems.');
	});
      };
      reader.readAsDataURL(mp3Blob);
    }

    source.connect(this.node);
    this.node.connect(this.context.destination);    //this should not be necessary
  };

  /*Recorder.forceDownload = function(blob, filename){
    console.log("Force download");
    var url = (window.URL || window.webkitURL).createObjectURL(blob);
    var link = window.document.createElement('a');
    link.href = url;
    link.download = filename || 'output.wav';
    var click = document.createEvent("Event");
    click.initEvent("click", true, true);
    link.dispatchEvent(click);
    }*/

  window.Recorder = Recorder;

})(window);
