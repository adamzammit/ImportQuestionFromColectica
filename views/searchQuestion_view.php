<?php
/**
 * This view display the page to add a new question to a controller, and to choose its group.
 * TODO : It will have to be merged with other question function such as "edit" or "copy".
 *
 */
?>
<div id='edit-question-body' class='side-body <?php echo getSideBodyClass(false); ?>'>
    <h3><?php eT("Search Colectica for a question"); ?></h3>
    <div class="row">
        <div class="col-lg-12">
            <form>
                <div class="form-group">
                    <label class=" control-label" for='colecticasearch'><?php eT("Search query");?>
                    </label>
                    <div class="">
                        <input type='text'  name='colecticasearch' id='colecticasearch'/>
                    </div>
                </div>
                <input type='submit' value='<?php eT("Search Colectica"); ?>' />
                <input type='hidden' name='action' value='searchcolectica' />
                <input type='hidden' name='sa' value='sidebody' />
                <input type='hidden' name='surveyId' value='<?php echo $surveyId; ?>' />
                <input type='hidden' name='gid' value='<?php echo $gid; ?>' />
                <input type='hidden' name='plugin' value='ImportQuestionFromColectica' />
                <input type='hidden' name='method' value='actionImportcolectica' />
            </form>
        </div>
    </div>
</div>







