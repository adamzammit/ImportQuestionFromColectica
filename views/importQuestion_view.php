<div id='edit-question-body' class='side-body <?php echo getSideBodyClass(false); ?>'>
    <h3><?php eT("Import from Colectica"); ?></h3>
    <div class="row">
        <div class="col-lg-12">
            <p><a href='<?php echo($rurl); ?>'><?php eT("<-- Return to Browse or Search Colectica for a question"); ?></a></p>
        </div>
        <div class="col-lg-12">
             <?php echo CHtml::beginForm(['admin/pluginhelper', 'sa' => 'sidebody', 'plugin' => 'ImportQuestionFromColectica', 'method' => 'actionImport', 'surveyId' => $surveyId]);?>
                <div class="form-group">
                    <label class=" control-label" for='colecticaquestions'><?php eT("Select question(s) to import");?>
                    </label>
                    <div class="">
                        <select name='colecticaquestions[]' id='colecticaquestions[]' multiple>
                        <?php foreach ($questions as $key => $val) {
                            print "<option value='{$key} {$val['agencyid']}'>[{$val['code']}] - {$val['question']}</option>";
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
                <input type='submit' value='<?php eT("Import question(s)"); ?>' />
            <?php echo CHtml::endForm();?>
        </div>
    </div>
</div>
