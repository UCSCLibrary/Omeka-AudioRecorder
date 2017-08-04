<?php
/**
 * Audio Recorder plugin
 *
 * @package   AudioRecorder
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Audio plugin class
 * 
 * @package AudioRecorder
 */
class AudioRecorderPlugin extends Omeka_Plugin_AbstractPlugin
{
    public function __toString() 
    {
        return $this->name;
    }
    
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array('define_routes','config_form','config','admin_items_show','public_items_show', 'public_head');

    function showWidget($item){

        if (class_exists('Omeka_Form_SessionCsrf')) {
            $csrf = new Omeka_Form_SessionCsrf;
        } else {
            $csrf = '';
        }

        echo(get_view()->partial('audio_recorder_widget.phtml',array('item_id'=>$item->id,'csrf'=>$csrf)));

            $relationParams = array();
            $relationParams['object_record_type']='Item';
            $relationParams['object_id'] = $item->id;
            $relationParams['subject_record_type']=ucfirst(get_option('audio_recorder_attachment'));
            if(plugin_is_active('RecordRelations')) {
                $relationsParams['property_id']=get_record_relations_property_id(FRBR, 'primaryTopic');
                $recordings = get_db()->getTable('RecordRelationsRelation')->findSubjectRecordsByParams($relationParams);
                echo(get_view()->partial('audio_recorder_related.phtml',array('related'=>$recordings)));            
            }


    }

    function hookPublicHead(){
        queue_js_file('getUserMedia.min');
        queue_css_file('audio_recorder');
        queue_css_file('dialog/jquery-ui.min');
        queue_css_file('dialog/jquery-ui.theme.min');
        queue_css_file('dialog/jquery-ui.structure.min');
    }

    function hookPublicItemsShow($args){
        $item = $args['item'];
        if(!get_option('audio_recorder_item_show'))
            return;
        $user = current_user();
        $role = is_object($user) ? $user->role : false;
        switch(get_option('audio_recorder_role')){
            case 'admins':
                if ( !$role || ($role !== 'admin' && $role !== 'super'))
                    return;
                break;
            case 'guests':
                if(!$role)
                    return;
                break;
            case 'editors':
                if (!get_acl()->isAllowed($user,'edit',$item))
                    return;
                break;
        }
        $this->showWidget($item);
    }

    function hookAdminItemsShow($args){
        $item = $args['item'];
        if(!get_option('audio_recorder_item_show'))
            return;
        $user = current_user();
        $role = is_object($user) ? $user->role : false;
        switch(get_option('audio_recorder_role')){
            case 'admins':
                if ( $role !== 'admin' && $role !== 'super')
                    return;
                break;
            case 'guests':
            case 'public':
                return;
                break;
            case 'editors':
                if (!get_acl()->isAllowed($user,'edit',$item))
                    return;
                break;
        }
        $this->showWidget($item);
    }

    function hookConfigForm(){
        require dirname(__FILE__) . '/config_form.php';
    }

    function hookConfig($args)
    {
        set_option('audio_recorder_item_show',(int)(boolean)$_POST['audio_recorder_item_show']);
        set_option('audio_recorder_item_record_route',(int)(boolean)$_POST['audio_recorder_item_record_route']);
        set_option('audio_recorder_role',$_POST['audio_recorder_role']);
        set_option('audio_recorder_attachment',$_POST['audio_recorder_attachment']);
        set_option('audio_recorder_public',$_POST['audio_recorder_public']);
    }

    function hookDefineRoutes($args)
    {
        // Don't add these routes on the admin side to avoid conflicts.
        if (is_admin_theme()) {
            return;
        }
        $router = $args['router'];

        // Add custom routes based on the page slug.
        $router->addRoute(
            'ajax-upload-audio',
            new Zend_Controller_Router_Route(
                'items/upload-audio/:id',
                array(
                    'module'       => 'audio-recorder',
                    'controller'   => 'recording',
                    'action'       => 'upload'
                )
            )
        );

        if(get_option('audio_recorder_item_record_route'))
            $router->addRoute(
                'item-record-audio',
                new Zend_Controller_Router_Route(
                    'items/record/:id',
                    array(
                        'module'       => 'audio-recorder',
                        'controller'   => 'recording',
                        'action'       => 'record'
                    )
                )
            );

    }
}
