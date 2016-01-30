<div class="field">
    <div id="audio_recorder_item_show_label" class="two columns alpha">
        <label for="audio_recorder_item_show"><?php echo __('Include audio recorder on Item page?'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __(
            'If checked, this plugin will include a widget on each item page for users to record and upload audio to the collection.'
        ); ?></p>
        <?php echo get_view()->formCheckbox('audio_recorder_item_show', true, 
        array('checked'=>(boolean)get_option('audio_recorder_item_show'))); ?>
    </div>
</div>

<div class="field">
    <div id="audio_recorder_item_record_route_label" class="two columns alpha">
        <label for="audio_recorder_item_record_route"><?php echo __('Create dedicated page for each item for recording audio?'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __(
            'If checked, this plugin will create a new page for each item at "/items/record/ITEMID" with a widget to upload audio for that item. This page can be linked to on the item show page or item browse results.'
        ); ?></p>
        <?php echo get_view()->formCheckbox('audio_recorder_item_record_route', true, 
        array('checked'=>(boolean)get_option('audio_recorder_item_record_route'))); ?>
    </div>
</div>


<div class="field">
    <div id="audio_recorder_role" class="two columns alpha">
        <label for="audio_recorder_role"><?php echo __('Who should be allowed to upload recorded audio about items?'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __(
            'Please select which users should be allowed to use the audio upload widgets to add audio files to the collection.'
        ); ?></p>
       <?php echo get_view()->formSelect(
           'audio_recorder_role',
           get_option('audio_recorder_role'),
           null,
           array(
               'public' => 'Everyone',
               'guests' => 'Anyone who creates a guest account with a valid email',
               'editors' => 'Users allowed to edit the item',
               'admins' => 'Administrative users only',
           )
       ); ?>
    </div>
</div>
