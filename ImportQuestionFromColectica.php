<?php

/*
 * LimeSurvey Plugin for importing questions from a Colectica repository via the REST API
 * Author: Adam Zammit <adam.zammit@acspri.org.au>
 * License: GNU General Public License v3.0
 *
 * This plugin is based on the following LimeSurvey Plugins:
 * URL: https://github.com/LimeSurvey/LimeSurvey/blob/master/application/core/plugins/Authwebserver/Authwebserver.php
 * URL: https://github.com/LimeSurvey/LimeSurvey/blob/master/application/core/plugins/AuthLDAP/AuthLDAP.php
 */

class ImportQuestionFromColectica extends LimeSurvey\PluginManager\PluginBase
{
    protected $storage = 'LimeSurvey\PluginManager\DbStorage';

    static protected $description = 'Allow users to import questions from a Colectica repository via the REST API';
    static protected $name = 'ImportQuestionFromColectica';

    protected $settings = array(
        'colectica_api_url' => array(
            'type' => 'string',
            'label' => 'URL for the Colectica API',
            'default' => '',
        ),
        'colectica_username' => array(
            'type' => 'string',
            'label' => 'Username for Colectica',
            'default' => '',
        ),
        'colectica_password' => array(
            'type' => 'string',
            'label' => 'Password for Colectica',
            'default' => '',
        ),
    );

    public function init() {
        $this->subscribe('beforeControllerAction');
        $this->subscribe('newQuestionAttributes','addSourceAttribute');
        $this->subscribe('getQuestionAttributes','addSourceAttribute');
    }

    public function addSourceAttribute() {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $sourceAttributes = array(
            'sourceURL' => array(
                'name'      => 'source_url',
                'types'     => '15ABCDEFGHIKLMNOPQRSTUWXYZ!:;|*', /* all question types */
                'category'  => $this->gT('Question Source'),
                'sortorder'=>1,
                'inputtype'=>'text',
                'default'=>$this->gT('Question not sourced from a metadata repository'),
                'readonly'=>true,
                'help'=>$this->gT("The link back to the question source in the metadata respository"),
                'caption'=>$this->gT('Question source URL'),
            ),
        );
        if(method_exists($this->getEvent(),'append')) {
            $this->getEvent()->append('questionAttributes', $sourceAttributes);
        } else {
            $questionAttributes=(array)$this->event->get('questionAttributes');
            $questionAttributes=array_merge($questionAttributes,$sourceAttributes);
            $this->event->set('questionAttributes',$questionAttributes);
        }
    }

    public function beforeControllerAction() {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $controller = $this->getEvent()->get('controller');
        $action = $this->getEvent()->get('action');
        $subaction = $this->getEvent()->get('subaction');
        $sid = Yii::app()->getRequest()->getParam('surveyid');

        if($controller=='admin' && $subaction=="newquestion") { //3.x LTS
            $gid = Yii::app()->getRequest()->getParam('gid');
            if ($gid == 0) {
                $gidresult = QuestionGroup::model()->findAllByAttributes(array('sid' => $sid, 'language' => 'en'), array('order'=>'group_order'));
                if (isset($gidresult[0]->attributes['gid'])) {
                    $gid = $gidresult[0]->attributes['gid'];
                }
            }
            $url = Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionImportcolectica',
                    'surveyId' => $sid,
                    'gid' => $gid,
                )
            );
            //custom JS for inserting button
            $buttonScript = "$( document ).ready(function() {
                $('a[href*=\"importview\"]').after('&nbsp;<a class=\"btn btn-default\" href=\"$url\" role=\"button\"><span class=\"icon-import\"></span>Import from Colectica</a>');
        });";
                App()->getClientScript()->registerScript('insertColecticaButton', $buttonScript, CClientScript::POS_BEGIN);
        } else if ($controller=='questionAdministration' && $action=="create") { //5.x
            $gid = Yii::app()->getRequest()->getParam('gid');
            if ($gid == 0) {
                $gidresult = QuestionGroup::model()->findAllByAttributes(array('sid' => $sid), array('order'=>'group_order'));
                if (isset($gidresult[0]->attributes['gid'])) {
                    $gid = $gidresult[0]->attributes['gid'];
                }
            } 
            $url = Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionImportcolectica',
                    'surveyId' => $sid,
                    'gid' => $gid,
                )
            );
            //custom JS for inserting button
            $buttonScript = "$( document ).ready(function() {
                $('a[href*=\"importView\"]').after('&nbsp;<a class=\"btn btn-default\" href=\"$url\" role=\"button\"><span class=\"icon-import\"></span>Import from Colectica</a>');
        });";
                App()->getClientScript()->registerScript('insertColecticaButton', $buttonScript, CClientScript::POS_BEGIN);
        }

        if(empty($sid) && Yii::app()->getRequest()->getIsPostRequest()) {
            $sid = Yii::app()->getRequest()->getPost('sid');
        }
        if(empty($sid)) {
            return;
        }


        //    $iSurveyID = (int) $surveyid;
        //   if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'import')) {
        //        Yii::app()->session['flashmessage'] = gT("We are sorry but you don't have permissions to do this.");
        //       $this->getController()->redirect(array('admin/survey/sa/listquestions/surveyid/'.$iSurveyID));
        //   }
        if ($subaction == "importcolecticaview") {
            $survey = Survey::model()->findByPk($sid);
            $aData = [];
            $aData['sidemenu']['state'] = false;
            $aData['sidemenu']['questiongroups'] = true;
            $aData['surveybar']['closebutton']['url'] = '/admin/survey/sa/listquestiongroups/surveyid/'.$iSurveyID; // Close button
            $aData['surveybar']['savebutton']['form'] = true;
            $aData['surveybar']['savebutton']['text'] = gt('Import');
            $aData['surveyid'] = $iSurveyID;
            $aData['groupid'] = $groupid;
            $aData['title_bar']['title'] = $survey->currentLanguageSettings->surveyls_title." (".gT("ID").":".$iSurveyID.")";
            $this->renderPartial('importQuestion_view', $aData);
        }
    }

    public function actionImportcolectica($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, gt('This survey does not seem to exist.'));
        }

        if(!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }

        $gid = intval(Yii::app()->getRequest()->getParam('gid'));
        $search = Yii::app()->getRequest()->getParam('colecticasearch');
        $instrument = Yii::app()->getRequest()->getParam('instrument');
        $agencyid = Yii::app()->getRequest()->getParam('agencyid');
        $questions = Yii::app()->getRequest()->getParam('colecticaquestions');

        if (is_array($questions) && count($questions) > 0) { //Import the selected questions from the repository
            $lastimportedqid = null;
            foreach($questions as $q) {
                $insertdata = [];
                $insertdata['sid'] = $surveyId;
                $insertdata['gid'] = $gid;
                list($id,$agencyid) = explode(" ", $q);
                $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$id", [], "get");
                $a = json_decode($apidata);
                $ddifragment = $a->Item;
                $ddi = new SimpleXMLElement($ddifragment);
                $insertdata['type'] = "S"; //short free text by default;
                $insertdata['question'] = (string)$ddi->QuestionItem->QuestionText->LiteralText->Text;
                $insertdata['title'] = (string)$ddi->QuestionItem->QuestionItemName->children('r',TRUE)->String;
                $insertdata['help'] = ""; // <r:useratttributepair><r:attributekey>extension:QuestionInstruction</r:attributekey><r:attributevalue>HELPTEXT</r:attributevalue></r:userattributepair>
                $insertdata['question_order'] = getMaxQuestionOrder($gid,$surveyId);
                //see if there is a "codedomain" fragment, if so this is a single choice question
                if (isset($ddi->QuestionItem->CodeDomain)) {
                    $insertdata['type'] = "L"; //list radio
                    $qanswers = [];
                    //populate answer list by finding all items in the codedomain
                    $cid = $ddi->QuestionItem->CodeDomain->children('r',TRUE)->CodeListReference->ID;
                    $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$cid", [], "get");
                    $a = json_decode($apidata);
                    $cddifragment = $a->Item;
                    $cddi = new SimpleXMLElement($cddifragment);
                    foreach($cddi->CodeList->Code as $ccode) {
                        $aid = $ccode->children('r',TRUE)->CategoryReference->ID;
                        $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$aid", [], "get");
                        $a = json_decode($apidata);
                        $addifragment = $a->Item;
                        $addi = new SimpleXMLElement($addifragment);
                        $qanswers[(string)$ccode->children('r',TRUE)->Value] = (string)$addi->Category->children('r',TRUE)->Label->Content;
                    }
                }
                //add the question to the current $gid in $surveyId
                $oQuestion = new Question('import');
                $oQuestion->setAttributes($insertdata, false);
                if (!$oQuestion->validate(['title'])) {
                    $oQuestion->title = $oQuestion->getNewTitle(); //retitle if conflict / invalid
                }
                if (!$oQuestion->save()) {
                    //error importing this question
                } else {
                    $lastimportedqid = $oQuestion->qid;
                    $l10saved = false;
                    if (class_exists("QuestionL10n")) { // LimeSurvey 5.x or greater
                        $l10n = new QuestionL10n();
                        $l10n->qid = $oQuestion->qid;
                        $l10n->language = 'en';
                        $l10n->question = $insertdata['question'];
                        $l10n->help = $insertdata['help'];
                        if ($l10n->save()) {
                            $l10saved = true;
                        }
                    }
                    if ($insertdata['type'] == 'L') {
                        $sortorder = 0;
                        foreach($qanswers as $key => $val) {
                            $oAnswer = new Answer();
                            $oAnswer->qid = $oQuestion->qid;
                            $oAnswer->code = $key;
                            $oAnswer->sortorder = $sortorder;
                            if (!$l10saved) { //3.x
                                $oAnswer->answer = $val;
                                $oAnswer->language = 'en';
                                $oAnswer->scale_id = 0;
                                $oAnswer->assessment_value = 0;
                            }
                            if ($oAnswer->save()) {
                                $sortorder++;
                                $oAnswer->refresh();
                                if ($l10saved) {
                                    $l10n = new AnswerL10n();
                                    $l10n->aid = $oAnswer->aid;
                                    $l10n->language = 'en';
                                    $l10n->answer = $val;
                                    if (!$l10n->save()) {
                                        //error saving question text
                                    }
                                }
                            }
                        }
                    }
                    // insert question source attribute
                    $oQuestionAttribute = new QuestionAttribute();
                    $oQuestionAttribute->qid = $oQuestion->qid;
                    $oQuestionAttribute->attribute = 'sourceURL';
                    $oQuestionAttribute->value = $this->get('colectica_api_url', null, null, true) . "/item/$agencyid/$id";
                    $oQuestionAttribute->save();

                }
            }

            if ($lastimportedqid != null) {
                //redirect to last imported question
                if(version_compare(Yii::app()->getConfig('versionnumber'),"5","<")) {
                    Yii::app()->controller->redirect(['admin/questions/sa/view', 'surveyid' => $surveyId, 'gid' => $gid, 'qid' => $lastimportedqid]);
                } else {
                    Yii::app()->controller->redirect(['questionAdministration/view', 'surveyid' => $surveyId, 'gid' => $gid, 'qid' => $lastimportedqid]);
                }
            }

        } else if (empty($search) && empty($instrument)) { //first action - select or search
            $aData = [];

            //find all instruments
            $this->refreshToken();
            $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/_query", ["itemTypes" => ["f196cc07-9c99-4725-ad55-5b34f479cf7d"], "maxResults" => "100"], "post");

            $instruments = $this->qList($apidata);

            $aData['pluginClass'] = get_class($this);
            $aData['surveyId'] = intval($surveyId);
            $aData['gid'] = $gid;
            $aData['instruments'] = $instruments;
            $aData['burl'] = Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionImportcolectica',
                    'surveyId' => intval($surveyId),
                    'gid' => $gid,
                )
            );
            return $this->renderPartial('searchBrowseQuestion_view', $aData, true);

        } else if (!empty($instrument) && !empty($agencyid)) { //questions by instrument browse
            $aData = [];

            //find all questions within this instrument
            $this->refreshToken();
            $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/_query/set", ["rootItem" => ["agencyId" => $agencyid, "identifier" => $instrument, "version" => "1"] , "facet" => ["itemTypes" => ["a1bb19bd-a24a-4443-8728-a6ad80eb42b8"]], "predicate" => "3fa85f64-5717-4562-b3fc-2c963f66afa6", "reverseTraversal" => false, "maxResults" => "10"], "post");
            $questions = $this->iList($apidata,$agencyid);

            $aData['pluginClass'] = get_class($this);
            $aData['surveyId'] = intval($surveyId);
            $aData['gid'] = $gid;
            $aData['questions'] = $questions;
            $aData['rurl'] = Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionImportcolectica',
                    'surveyId' => $surveyId,
                    'gid' => $gid,
                )
            );
            return $this->renderPartial('importQuestion_view', $aData, true);

        } else if(!empty($search)) { //search results

            //TODO: Don't do this every time check for error first
            $this->refreshToken();
            //get example list using apicall
            $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/_query", ["itemTypes" => ["a1bb19bd-a24a-4443-8728-a6ad80eb42b8"], "maxResults" => "100", "searchTerms" => [$search]], "post");

            $questions = $this->qList($apidata);

            $aData = [];

            $aData['pluginClass'] = get_class($this);
            $aData['surveyId'] = intval($surveyId);
            $aData['title'] = "Import from Colectica";
            $aData['gid'] = $gid;
            $aData['questions'] = $questions;
            $aData['rurl'] = Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionImportcolectica',
                    'surveyId' => $surveyId,
                    'gid' => $gid,
                )
            );

            return $this->renderPartial('importQuestion_view', $aData, true);
        }

    }


    private function iList($apidata,$agencyid)
    {
        $rs = json_decode($apidata);
        $return = [];

        foreach($rs as $r) {
            $itemid = $r->Item1->Item1;
            $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$itemid", [], "get");
            $a = json_decode($apidata);
            $ddifragment = $a->Item;
            $ddi = new SimpleXMLElement($ddifragment);
            $return[$a->Identifier] = ["code" => $ddi->QuestionItem->QuestionItemName->children('r',TRUE)->String, "question" => $ddi->QuestionItem->QuestionText->LiteralText->Text, "agencyid" => $agencyid];
        }

        return $return;
    }

    private function qList($apidata)
    {
        $rs = json_decode($apidata);
        $return = [];

        foreach($rs->Results as $r) {
            $labelIterator = new ArrayIterator($r->Label);
            $summaryIterator = new ArrayIterator($r->Summary);
            $itemNameIterator = new ArrayIterator($r->ItemName);
            $return[$r->Identifier] = ["code" => $itemNameIterator->current(), "question" => $summaryIterator->current(), "label" => $labelIterator->current(), "agencyid" => $r->AgencyId];
        }

        return $return;
    }


    private function refreshToken()
    {
        $data = json_decode($this->apiCall($this->get('colectica_api_url', null, null, true) . "/token/CreateToken", ['username' => $this->get('colectica_username', null, null, true), 'password' => $this->get('colectica_password', null, null, true)],'post'));
        Yii::app()->session->add("ImportQuestionFromColecticaAccessToken", $data->access_token);
    }

    private function checkCallSuccess($data)
    {
        if (isset($data['http_response_code']) && $data['http_response_code'] == 401) {
            $this->refreshToken();//Fetches new access token
            return false;
        } else {
            return json_encode($data);
        }
    }

    /* apiCall function
     * source: https://write.corbpie.com/automatically-refreshing-oauth-access-tokens-with-php/
     */
    private function apiCall($url, $params = array(), $method = 'get')
    {
        $data = null;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $header = [];
        $header[]  =  'Content-Type: application/json';
        $header[]  =  'Accept: */*';
        if (isset(Yii::app()->session['ImportQuestionFromColecticaAccessToken'])) {
            $header[] = "Authorization: Bearer " . Yii::app()->session['ImportQuestionFromColecticaAccessToken'];
        }
        if ($method == 'get' && !empty($params)) {
            $url = ($url . '?' . http_build_query($params));
        } else if ($method == 'post') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            $header[] =  'Content-Length: ' . strlen(json_encode($params));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        $response = curl_exec($curl);
        $responseInfo = curl_getinfo($curl);
        if ($responseInfo['http_code'] == 200) {
            $data = $response;
        } else {
            $data = array('http_response_code' => $responseInfo['http_code']);//Call failed
        }
        return $data;
    }

}
