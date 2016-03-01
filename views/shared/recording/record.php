<?php 

echo head(array(
    'title' => "Record Audio for $title", 
    'bodyclass' => 'page audio-record',
)); ?>

<div id="primary">
   <h1>Record Audio for <?php echo $title; ?></h1>
   <?php echo $this->partial('audio_recorder_widget.phtml',array('item_id'=>$this->item->id,'username'=>$this->username,'email'=>$this->email)) ?>
</div>
<?php echo foot();?>
