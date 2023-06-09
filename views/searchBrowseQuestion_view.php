<div id='edit-question-body' class='side-body <?php echo getSideBodyClass(false); ?>'>
    <h3><?php eT("Browse or Search Colectica for a question"); ?></h3>
    <div class="row">
        <div class="col-lg-12">
                <div class="form-group">
                    <label class=" control-label" for='colecticainstruments'><?php eT("Choose an Instrument to Browse (or search all below)");?>
                    </label>
                    <div class="">
                        <ol>
                        <?php foreach ($instruments as $key => $val) {
                            print "<li><a href='$burl&instrument={$key}&agencyid={$val['agencyid']}'>{$val['label']}</a></li>";
                        }?>
                        </ol>
                    </div>
                </div>
            <?php echo CHtml::beginForm(['admin/pluginhelper', 'sa' => 'sidebody', 'plugin' => 'ImportQuestionFromColectica', 'method' => 'actionSearch', 'surveyId' => $surveyId, 'gid' => $gid]);?>
                <div class="form-group">
                    <label class=" control-label" for='colecticasearch'><?php eT("Search query");?>
                    </label>
                    <div class="">
                        <input type='text'  name='colecticasearch' id='colecticasearch'/>
                    </div>
                </div>
                <input type='submit' value='<?php eT("Search Colectica"); ?>' />
            <?php echo CHtml::endForm();?>
        </div>
    </div>
</div>
