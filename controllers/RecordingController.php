<?php
class AudioRecorder_RecordingController extends Omeka_Controller_AbstractActionController
{	
    private function _getItem(){
        $item = get_record_by_id('Item',(int)$this->getParam('id'));
        if(is_object($item))
            $hasItem=true;

        if(get_option('audio_recorder_attachment') =='file' && $hasItem) {
            //TODO add relations for file attachment?
            return $item;
        }

        //create a new item and attach it as much as possible

        $username = is_object($user = current_user()) ? $user->name : "An Anonymous Contributor";
        $username = isset($_REQUEST['ar-username']) && $_REQUEST['ar-username'] ? $_REQUEST['ar-username'] : $username;
        if ($_REQUEST['ar-anon'] != "yes"){
            /* Make sure user didn't want to be anonymous, or that their email was to remain hidden */
            $userString = $username;
        }else{
            $userString = "An Anonymous Contributor";
        }
        

        $elementTable = get_db()->getTable('Element');

        $creatorElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Creator');
        $elements[$creatorElement->id] = array(
            array(
                'text'=> $userString,
                'html' => "0"
            ));

        if($username == $user->name)
            $creator = $user;
        
        if(is_object($user) && $username !== $user->name){
            $contributor = $user;
            $contributorElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Contributor');
            $elements[$contributorElement->id] = array(
                array(
                    'text'=> "$user->name ($user->email)",
                    'html' => "0"
                ));           
         }else{
            $contributorElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Contributor');
            $elements[$contributorElement->id] = array(
                array(
                    'text'=> "$username (".$_REQUEST['ar-email'].")",
                    'html' => "0"
                )); 
         }

        $rightsString = (isset($_REQUEST['ar-researchRights']) && $_REQUEST['ar-researchRights'] == 'yes' ? 'Research & ' : 'No Research & ') . (isset($_REQUEST['ar-displayRights']) && $_REQUEST['ar-displayRights'] == 'yes' ? 'Display' : 'No Display');
        $rightsElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Rights');
        $elements[$rightsElement->id] = array(
            array(
                'text'=> "$rightsString",
                'html' => "0"
            )
        );

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

            $title = isset($_REQUEST['ar-title']) && $_REQUEST['ar-title'] != '' ? $_REQUEST['ar-title'] : "Audio Recording ".date('Y-m-d');

            $titleElement = $elementTable->findByElementSetNameAndElementName('Dublin Core','Title');
            $elements[$titleElement->id] = array(
                array(
                    'text'=> $title,
                    'html' => "0"
                ));
        }

        $oralHistoryType = get_db()->getTable('ItemType')->findByName('Oral History');
        
        $fauxPost = array(
	    'Elements'=>$elements,
	    'item_type_id'=>$oralHistoryType->id, //get the id of the Audio item type
//	    'collection_id'=>'',
            'public'=>(boolean)get_option('audio_recorder_public'),
	);

        $recItem = new Item();
        $recItem->setPostData($fauxPost);
        $recItem->save();
        $creator = isset($creator) ? $creator : null;
        $contributor = isset($contributor) ? $contributor : null;
        $this->_addRelations($recItem,$item,$creator,$contributor);
        return $recItem;
    }
    
    public function uploadAction() {

        if ($_REQUEST['ar-researchRights'] == 'no'){
            die("If you want to upload, please allow us Storage and Research rights, please.");
        }
        
        if (class_exists('Omeka_Form_SessionCsrf')) {
            $csrf = new Omeka_Form_SessionCsrf;
            if (!$csrf->isValid($_POST))
                die('Invalid token. Are you using a weird proxy or something?');
        }

        if(!is_object(
            $item = $this->_getItem()))
                die("Failure finding or creating item");

        //           #TODO check CSRF
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
    
    private function _addRelations($recItem,$item,$creator=nil,$contributor = nil,$recType='Item')
    {

        //Primary topic (relates new item to item it describes)
        $relationProps[] = array(
                'subject_record_type' => $recType,
                'object_record_type' => 'Item',
                'subject_id'=> $recItem->id,
                'object_id' => $item->id,
                'property_id' => get_record_relations_property_id(FOAF, 'primaryTopic'),
                'public' => true,                
                'user_id' => is_object($user=current_user()) ? $user->id : $item->owner_id,
            );
        
        $relationProps[] = array(
                'subject_record_type' => 'Item',
                'subject_id' => $item->id,
                'object_id' => $recItem->id,
                'object_record_type' => $recType,
                'property_id' => get_record_relations_property_id(FOAF, 'isPrimaryTopicOf'),
                'public' => true,
                'user_id' => is_object($user) ? $user->id : $item->owner_id,
            );

        //add record relation for creator
        if(isset($creator) && is_object($creator)) {
            $relationProps[] = array(
                'subject_record_type' => $recType,
                'subject_id' => $recItem->id,
                'object_id' => $creator->id,
                'object_record_type' => 'User',
                'property_id' => get_record_relations_property_id(FRBR, 'creator'),
                'public' => true,
                'user_id' => is_object($user) ? $user->id : $item->owner_id,
            );

            $relationProps[] = array(
                'object_record_type' => $recType,
                'object_id' => $recItem->id,
                'subject_id' => $creator->id,
                'subject_record_type' => 'User',
                'property_id' => get_record_relations_property_id(FRBR, 'creatorOf'),
                'public' => true,
                'user_id' => is_object($user) ? $user->id : $item->owner_id,
            );
 
        }
            
        //add record relation for contributor
        if(isset($contributor) && is_object($contributor)) {
            $relationProps[] = array(
                'subject_record_type' => $recType,
                'subject_id' => $recItem->id,
                'object_id' => $contributor->id,
                'object_record_type' => 'User',
                'property_id' => get_record_relations_property_id(FRBR, 'producer'),
                'public' => true,
                'user_id' => is_object($user) ? $user->id : $item->owner_id,
            );

           $relationProps[] = array(
                'object_record_type' => $recType,
                'object_id' => $recItem->id,
                'subject_id' => $contributor->id,
                'subject_record_type' => 'User',
                'property_id' => get_record_relations_property_id(FRBR, 'producerOf'),
                'public' => true,
                'user_id' => is_object($user) ? $user->id : $item->owner_id,
            );
        }

        foreach($relationProps as $props) {
            if(!isset($props['user_id']))
                $props['user_id'] = 0;
/*
            if(!isset($props['property_id']) || $props['property_id'] == null) {
                print_r($props);
                die();
            }
*/
            $relation = new RecordRelationsRelation();
            $relation->setProps($props);
            $relation->save();
        }
    }

}
?>
