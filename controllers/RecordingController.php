<?php
class AudioRecorder_RecordingController extends Omeka_Controller_AbstractActionController
{	
	public function uploadAction() {

            if(!$item_id = (int)$this->getParam('id'))
                die("Failure. Bad item ID.");
            if(!$item = get_record_by_id("Item",$item_id))
                die("Failure. Couldn't find item");

//           #TODO check CSRF
/*            $tempDir = sys_get_temp_dir()."/omeka-audio-recorder";
            if(!is_dir($tempDir))
	        $res = mkdir($tempDir,0777); 
            echo ("Res: $res"); 
*/            
            $filename = 'audio_recording_' . date( 'Y-m-d-H-i-s' ) .'.mp3';
            $filename = is_null($_POST['fname']) ? $_POST['fname'] : $filename;
            
            $username = is_object($user = current_user()) ? $user->name : "An Anonymous Contributor";
            $username = isset($_POST['audio_recorder_username']) ? $_POST['audio_recorder_username'] : $username;
            $creators = array($username);
            if(is_object($user))
                $creators[] = $user->email;

            //if the user is submitting on behalf of someone else, 
            //we should save both of their info
            $contributors = array();
            if(is_object($user) && $username !== $user->name){
                $contributors[] = $user->name;
                $contributors[] = $user->email;
            }

            $filepath = tempnam(sys_get_temp_dir(),'audio-recording_').'.mp3';

            $data = substr($_POST['data'], strpos($_POST['data'], ",") + 1);
            $fp = fopen($filepath, 'wb');
            fwrite($fp, $data);
            fclose($fp);

//            die('preparing to insert file:'.$filepath);

            insert_files_for_item($item,'Filesystem',
                                  array(
                                      'source'=>$filepath,
                                      'name'=>$filename
                                  ),
                                  array(
                                      'Dublin Core'=>array(
                                          'Creator'=>$creators,
                                          'Contributor'=>$contributors,
                                          'Source'=>array('Audio Recorder Plugin'))
                                  )
            );
            die("Success: $filename");
	}	
	public function recordAction() {
            $item = get_record_by_id("Item",$this->getParam('id'));
            $this->view->item = $item;
            $this->view->title = is_object($item) ? metadata($this->view->item,array('Dublin Core','Title')) : "Untitled";

            $this->view->username = is_object($user = current_user()) ? $user->name : "";
            $this->view->email = is_object($user) ? $user->email : "";
	}	
}
?>
