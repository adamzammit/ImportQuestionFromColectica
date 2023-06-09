<?php

/**
 * LimeSurvey plugin for importing questions from a Colectica repository
 * via the REST API
 * php version 7.4
 *
 * @category Plugin
 * @package  LimeSurvey
 * @author   Adam Zammit <adam.zammit@acspri.org.au>
 * @license  GPLv3 https://www.gnu.org/licenses/gpl-3.0.en.html
 * @link     https://github.com/adamzammit/ImportQuestionFromColectica
 */

/**
 * LimeSurvey plugin for importing questions from a Colectica repository
 * via the REST API
 *
 * @category Plugin
 * @package  LimeSurvey
 * @author   Adam Zammit <adam.zammit@acspri.org.au>
 * @license  GPLv3 https://www.gnu.org/licenses/gpl-3.0.en.html
 * @link     https://github.com/adamzammit/ImportQuestionFromColectica
 */

class ImportQuestionFromColectica extends LimeSurvey\PluginManager\PluginBase
{
    protected $storage = 'LimeSurvey\PluginManager\DbStorage';

    protected static $description = 'Allow users to import questions from a Colectica repository via the REST API';
    protected static $name = 'ImportQuestionFromColectica';

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

    /**
     * Set subscribed actions for this plugin
     *
     * @return none
     */
    public function init()
    {
        $this->subscribe('beforeControllerAction');
        $this->subscribe('newQuestionAttributes', 'addSourceAttribute');
        $this->subscribe('getQuestionAttributes', 'addSourceAttribute');
    }

    /**
     * Adds the sourceURL attribute to all question types
     *
     * @return none
     */
    public function addSourceAttribute()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $sourceAttributes = array(
            'sourceURL' => array(
                'name'      => 'source_url',
                'types'     => '15ABCDEFGHIKLMNOPQRSTUWXYZ!:;|*', /* all question types */
                'category'  => $this->gT('Question Source'),
                'sortorder' => 1,
                'inputtype' => 'text',
                'default'   => $this->gT('Question not sourced from a metadata repository'),
                'readonly'  => true,
                'help'      => $this->gT("The link back to the question source in the metadata respository"),
                'caption'   => $this->gT('Question source URL'),
            ),
        );
        if (method_exists($this->getEvent(), 'append')) {
            $this->getEvent()->append('questionAttributes', $sourceAttributes);
        } else {
            $questionAttributes = (array)$this->event->get('questionAttributes');
            $questionAttributes = array_merge($questionAttributes, $sourceAttributes);
            $this->event->set('questionAttributes', $questionAttributes);
        }
    }

    /**
     * Inject the link to importing from Colectica into the UI
     *
     * @return none
     */
    public function beforeControllerAction()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $controller = $this->getEvent()->get('controller');
        $action = $this->getEvent()->get('action');
        $subaction = $this->getEvent()->get('subaction');
        $sid = Yii::app()->getRequest()->getParam('surveyid');

        if ($controller == 'admin' && $subaction == "newquestion") { //3.x LTS
            $gid = Yii::app()->getRequest()->getParam('gid');
            if ($gid == 0) {
                $gidresult = QuestionGroup::model()->findAllByAttributes(array('sid' => $sid, 'language' => 'en'), array('order' => 'group_order'));
                if (isset($gidresult[0]->attributes['gid'])) {
                    $gid = $gidresult[0]->attributes['gid'];
                }
            }
            $url = Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionBrowsesearch',
                    'surveyId' => $sid,
                    'gid' => $gid,
                )
            );
            //custom JS for inserting button
            $buttonScript = "$( document ).ready(function() {
                $('a[href*=\"importview\"]').after('&nbsp;<a class=\"btn btn-default\" href=\"$url\" role=\"button\"><span class=\"icon-import\"></span>Import from Colectica</a>');
                });";
            App()->getClientScript()->registerScript('insertColecticaButton', $buttonScript, CClientScript::POS_BEGIN);
        } elseif ($controller == 'questionAdministration' && $action == "create") { //5.x
            $gid = Yii::app()->getRequest()->getParam('gid');
            if ($gid == 0) {
                $gidresult = QuestionGroup::model()->findAllByAttributes(array('sid' => $sid), array('order' => 'group_order'));
                if (isset($gidresult[0]->attributes['gid'])) {
                    $gid = $gidresult[0]->attributes['gid'];
                }
            }
            $url = Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionBrowsesearch',
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
    }

    /**
     * Select a questionnare to browse or search a repository
     *
     * @param $surveyId The survey id
     *
     * @return none
     */
    public function actionBrowsesearch($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, gt('This survey does not seem to exist.'));
        }

        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }

        $gid = intval(Yii::app()->getRequest()->getParam('gid'));

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
                'method' => 'actionList',
                'surveyId' => intval($surveyId),
                'gid' => $gid,
            )
        );
        return $this->renderPartial('searchBrowseQuestion_view', $aData, true);
    }

    /**
     * List questions based on selected instrument
     *
     * @param $surveyId The survey id
     *
     * @return none
     */
    public function actionList($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, gt('This survey does not seem to exist.'));
        }

        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }

        $gid = intval(Yii::app()->getRequest()->getParam('gid'));
        $instrument = Yii::app()->getRequest()->getParam('instrument');
        $agencyid = Yii::app()->getRequest()->getParam('agencyid');

        $aData = [];

        //find all questions within this instrument
        $this->refreshToken();
        $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/_query/set", ["rootItem" => ["agencyId" => $agencyid, "identifier" => $instrument, "version" => "1"] , "facet" => ["itemTypes" => ["a1bb19bd-a24a-4443-8728-a6ad80eb42b8"]], "predicate" => "3fa85f64-5717-4562-b3fc-2c963f66afa6", "reverseTraversal" => false, "maxResults" => "10"], "post");
        $questions = $this->iList($apidata, $agencyid);

        $aData['pluginClass'] = get_class($this);
        $aData['surveyId'] = intval($surveyId);
        $aData['gid'] = $gid;
        $aData['questions'] = $questions;
        $aData['rurl'] = Yii::app()->createUrl(
            'admin/pluginhelper',
            array(
                'sa' => 'sidebody',
                'plugin' => get_class($this),
                'method' => 'actionBrowsesearch',
                'surveyId' => $surveyId,
                'gid' => $gid,
            )
        );
        return $this->renderPartial('importQuestion_view', $aData, true);
    }

    /**
     * Search the repository
     *
     * @param $surveyId The survey id
     *
     * @return none
     */
    public function actionSearch($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, gt('This survey does not seem to exist.'));
        }

        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }

        $search = Yii::app()->getRequest()->getParam('colecticasearch');
        $gid = intval(Yii::app()->getRequest()->getParam('gid'));

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
                'method' => 'actionBrowsesearch',
                'surveyId' => $surveyId,
                'gid' => $gid,
            )
        );

        return $this->renderPartial('importQuestion_view', $aData, true);
    }

    /**
     * Import selected questions to a group
     *
     * @param $surveyId The survey id
     *
     * @return none
     */
    public function actionImport($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, gt('This survey does not seem to exist.'));
        }

        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }

        $gid = intval(Yii::app()->getRequest()->getParam('gid'));
        $questions = Yii::app()->getRequest()->getParam('colecticaquestions');

        if (is_array($questions) && count($questions) > 0) { //Import the selected questions from the repository
            $lastimportedqid = null;
            foreach ($questions as $q) {
                $insertdata = [];
                $insertdata['sid'] = $surveyId;
                $insertdata['gid'] = $gid;
                list($id,$agencyid) = explode(" ", $q);
                $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$id", [], "get");
                $a = json_decode($apidata);
                $ddifragment = $a->Item;
                $ddi = new SimpleXMLElement($ddifragment);
                $insertdata['type'] = "S"; //short free text by default;
                $insertdata['question'] = (string) $ddi->QuestionItem->QuestionText->LiteralText->Text;
                $insertdata['title'] = (string) $ddi->QuestionItem->QuestionItemName->children('r', true)->String;
                $insertdata['help'] = ""; // <r:useratttributepair><r:attributekey>extension:QuestionInstruction</r:attributekey><r:attributevalue>HELPTEXT</r:attributevalue></r:userattributepair>
                $insertdata['question_order'] = getMaxQuestionOrder($gid, $surveyId);
                //see if there is a "codedomain" fragment, if so this is a single choice question
                if (isset($ddi->QuestionItem->CodeDomain)) {
                    $insertdata['type'] = "L"; //list radio
                    $qanswers = [];
                    //populate answer list by finding all items in the codedomain
                    $cid = $ddi->QuestionItem->CodeDomain->children('r', true)->CodeListReference->ID;
                    $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$cid", [], "get");
                    $a = json_decode($apidata);
                    $cddifragment = $a->Item;
                    $cddi = new SimpleXMLElement($cddifragment);
                    foreach ($cddi->CodeList->Code as $ccode) {
                        $aid = $ccode->children('r', true)->CategoryReference->ID;
                        $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$aid", [], "get");
                        $a = json_decode($apidata);
                        $addifragment = $a->Item;
                        $addi = new SimpleXMLElement($addifragment);
                        $qanswers[(string) $ccode->children('r', true)->Value] = (string)$addi->Category->children('r', true)->Label->Content;
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
                        foreach ($qanswers as $key => $val) {
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
                if (version_compare(Yii::app()->getConfig('versionnumber'), "5", "<")) {
                    Yii::app()->controller->redirect(['admin/questions/sa/view', 'surveyid' => $surveyId, 'gid' => $gid, 'qid' => $lastimportedqid]);
                } else {
                    Yii::app()->controller->redirect(['questionAdministration/view', 'surveyid' => $surveyId, 'gid' => $gid, 'qid' => $lastimportedqid]);
                }
            }
        }
        //either no questions selected or failed to import - go back to page
        Yii::app()->setFlashMessage("Failed to import or no questions selected for import", 'error');
        Yii::app()->controller->redirect(['admin/pluginhelper', 'surveyid' => $surveyId, 'gid' => $gid, 'method' => 'actionBrowsesearch', 'plugin' => get_class($this), 'sa' => 'sidebody']);
    }

    /**
     * Convert a Colectica JSON encoded item list to an array
     *
     * @param $apidata  JSON data returned from Colectica
     * @param $agencyid The Colectica agencyid
     *
     * @return array    An array containing all listed items returned indexed by the item identifier
     */
    private function iList($apidata, $agencyid)
    {
        $rs = json_decode($apidata);
        $return = [];

        foreach ($rs as $r) {
            $itemid = $r->Item1->Item1;
            $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/item/$agencyid/$itemid", [], "get");
            $a = json_decode($apidata);
            $ddifragment = $a->Item;
            $ddi = new SimpleXMLElement($ddifragment);
            $return[$a->Identifier] = ["code" => $ddi->QuestionItem->QuestionItemName->children('r', true)->String, "question" => $ddi->QuestionItem->QuestionText->LiteralText->Text, "agencyid" => $agencyid];
        }

        return $return;
    }

    /**
     * Convert a Colectica JSON encoded question list to an array
     *
     * @param $apidata JSON data returned from Colectica
     *
     * @return array   An array containing all listed items returned indexed by the item identifier
     */
    private function qList($apidata)
    {
        $rs = json_decode($apidata);
        $return = [];

        foreach ($rs->Results as $r) {
            $labelIterator = new ArrayIterator($r->Label);
            $summaryIterator = new ArrayIterator($r->Summary);
            $itemNameIterator = new ArrayIterator($r->ItemName);
            $return[$r->Identifier] = ["code" => $itemNameIterator->current(), "question" => $summaryIterator->current(), "label" => $labelIterator->current(), "agencyid" => $r->AgencyId];
        }

        return $return;
    }

    /**
     * Refresh the Colectica access token
     *
     * @return none
     */
    private function refreshToken()
    {
        $data = json_decode($this->apiCall($this->get('colectica_api_url', null, null, true) . "/token/CreateToken", ['username' => $this->get('colectica_username', null, null, true), 'password' => $this->get('colectica_password', null, null, true)], 'post'));
        Yii::app()->session->add("ImportQuestionFromColecticaAccessToken", $data->access_token);
    }

    /**
     * See if the call to Collectica was successful
     *
     * @param $data Array returned from the API call
     *
     * @return bool|string false if call failed, otherwise the json encoded string
     */
    private function checkCallSuccess($data)
    {
        if (isset($data['http_response_code']) && $data['http_response_code'] == 401) {
            $this->refreshToken();//Fetches new access token
            return false;
        } else {
            return json_encode($data);
        }
    }

    /**
     * Make the api call via curl
     *
     * @param $url    API base URL
     * @param $params array containing URL parameters
     * @param $method HTTP method (get or post)
     *
     * @return array|string array containing HTTP response code if failed else string data
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
        } elseif ($method == 'post') {
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
