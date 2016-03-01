<?php
class AudioRecorder_RecordingController extends Omeka_Controller_AbstractActionController
{	
    private function _getItem(){
        $item = get_record_by_id('Item',(int)$this->getParam('id'));
        if(is_object($item))
            $hasItem=true;

        if(get_option('audio_recorder_attachment') =='file' && $hasItem)
            return $item;

        //create a new item and attach it as much as possible

        $username = is_object($user = current_user()) ? "$user->name ($user->email)" : "An Anonymous Contributor";
        $username = isset($_POST['audio_recorder_username']) ? $_POST['audio_recorder_username'] : $username;

        $elementTable = get_db()->getTable('Element');

        $creatorElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Creator');
        $elements[$creatorElement->id] = array(
            array(
                'text'=> $username,
                'html' => "0"
            ));
        //todo - if record relations is installed, 
        //add an appropriate relation
        
        if(is_object($user) && $username !== $user->name){
            $contributorElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Contributor');
            $elements[$contributorElement->id] = array(
                array(
                    'text'=> "$user->name ($user->email)",
                    'html' => "0"
                ));           
            //todo - if record relations is installed, 
            //add an appropriate relation
        }

        $sourceElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Source');
        $elements[$sourceElement->id] = array(
            array(
                'text'=> "Audio Recorder plugin",
                'html' => "0"
            )
        );

        if($hasItem){
            $relationElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Relation');
            $elements[$relationElement->id] = array(
                array(
                    'text'=> 'This recording describes, or is directly related to, the following item: <br> <a href="'.absolute_url('items/show/'.$item->id).'">'.metadata($item,array('Dublin Core','Title')).'</a>',
                    'html' => "1"
                )
            );
            //todo - if record relations is installed, 
            //add an appropriate relation

        $titleElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Title');
        $elements[$titleElement->id] = array(
            array(
                'text'=> "Audio Recording ".date('Y-m-d'),
                'html' => "0"
            ));

        }
        
        $fauxPost = array(
	    'Elements'=>$elements,
	    'item_type_id'=>'', //get the id of the Audio item type
//	    'collection_id'=>''
            'public'=>(boolean)get_option('audio_recorder_public'),
	);

        $recItem = new Item();
        $recItem->setPostData($fauxPost);
        $recItem->save();
        return $recItem;
    }
    
    public function uploadAction() {

        if(!is_object(
            $item = $this->_getItem()))
                die("Failure finding or creating item");

        //           #TODO check CSRF
        /*            $tempDir = sys_get_temp_dir()."/omeka-audio-recorder";
           if(!is_dir($tempDir))
	   $res = mkdir($tempDir,0777); 
           echo ("Res: $res"); 
         */            
        $filename = 'audio_recording_' . date( 'Y-m-d-H-i-s' ) .'.mp3';
        $filename = is_null($_POST['fname']) ? $_POST['fname'] : $filename;

        $filepath = tempnam(sys_get_temp_dir(),'audio-recording_').'.mp3';

        $data = base64_decode(substr($_POST['data'], strpos($_POST['data'], ",") + 1));
        

        $fp = fopen($filepath, 'wb');
        fwrite($fp, $data);
        fclose($fp);

        $files = insert_files_for_item($item,'Filesystem',
                                       array(
                                           'source'=>$filepath,
                                           'name'=>$filename
                                       ),
                                       array()
        );
        $minOrder=99;
        foreach($files as $file){
            $file->order=$minOrder;
            $file->save();
            $minOrder++;
        }
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
