<div id='edit-question-body' class='side-body <?php echo getSideBodyClass(false); ?>'>
    <h3><?php eT("Import from Colectica"); ?></h3>
    <div class="row">
		<div class="col-lg-12">
			<p><a href='<?php echo($rurl); ?>'><?php eT("<-- Return to Browse or Search Colectica for a question"); ?></a></p>
		</div>
        <div class="col-lg-12">
            <?php echo CHtml::form(array("admin/questions/sa/import"), 'post', array('id'=>'importquestion', 'class'=>'', 'name'=>'importquestion', 'enctype'=>'multipart/form-data','onsubmit'=>"return window.LS.validatefilename(this, '".gT("Please select a file to import!",'js')."');")); ?>
                <div class="form-group">
                    <label class=" control-label" for='colecticaquestions'><?php eT("Select question(s) to import");?>
                    </label>
                    <div class="">
                        <select name='colecticaquestions' id='colecticaquestions' multiple>
						<?php foreach($questions as $key => $val) {
							print "<option value='{$key}'>[{$val['code']}] - {$val['question']}</option>";
						}?>
						</select>
                    </div>
                </div>
                <div class="form-group">
                    <label class=" control-label" for='the_file'><?php eT("Destination question group:"); ?></label>
                    <div class="">
                        <select name='gid' id='gid' class="form-control">
                            <?php echo getGroupList3($gid, $surveyId); ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class=" control-label" for='translinksfields'><?php eT("Automatically rename question code if already exists?"); ?></label>
                    <div class="">
                        <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                            'name' => 'autorename',
                            'id'=>'autorename',
                            'value' => 1,
                            'onLabel'=>gT('On'),
                            'offLabel' => gT('Off')));
                        ?>
                    </div>
                </div>
                <p>
                <input type='submit' value='<?php eT("Import question(s)"); ?>' />
                <input type='hidden' name='action' value='importquestion' />
                <input type='hidden' name='sid' value='<?php echo $surveyid; ?>' />
            </form>
        </div>
    </div>
</div>







