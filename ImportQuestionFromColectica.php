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

class ImportQuestionFromColectica extends LimeSurvey\PluginManager\AuthPluginBase
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
        $this->storage = $this->get('storage_base', null, null, $this->settings['storage_base']['default']);
		$this->subscribe('beforeControllerAction');
        $this->subscribe('beforeSurveyBarRender');
    }


    public function beforeControllerAction() {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $controller = $this->getEvent()->get('controller');
        $action = $this->getEvent()->get('action');
        $subaction = $this->getEvent()->get('subaction');
        $sid = Yii::app()->getRequest()->getParam('surveyid');

        if($controller=='admin' && $subaction=="newquestion") {
            $gid = Yii::app()->getRequest()->getParam('gid');
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

        $gid = intval(Yii::app()->getRequest()->getParam('gid'));
        $search = Yii::app()->getRequest()->getParam('colecticasearch');

        if (empty($search)) {
	        $aData = [];
	
	        $aData['pluginClass'] = get_class($this);
	        $aData['surveyId'] = intval($surveyId);
	        $aData['gid'] = $gid;
	
            return $this->renderPartial('searchQuestion_view', $aData, true);

		} else {
       
			//TODO: Don't do this every time check for error first
	        $this->refreshToken();
	        //get example list using apicall
	        $apidata = $this->apiCall($this->get('colectica_api_url', null, null, true) . "/api/v1/_query", ["itemTypes" => ["a1bb19bd-a24a-4443-8728-a6ad80eb42b8"], "maxResults" => "10", "searchTerms" => [$search]], "post");
	
			$questions = $this->qList($apidata);
	     
	        $aData = [];
	
	        $aData['pluginClass'] = get_class($this);
	        $aData['surveyId'] = intval($surveyId);
	        $aData['title'] = "Import from Colectica";
	        $aData['gid'] = $gid;
	        $aData['questions'] = $questions; 
	//        $aData['aSettings'] = $aSettings;
	//        $aData['assetUrl'] = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/');
	
	
	         return $this->renderPartial('importQuestion_view', $aData, true);
		}

	}
  
 
    private function qList($apidata) 
    {
        $rs = json_decode($apidata);
        $return = [];

        foreach($rs->Results as $r) {
            $return[$r->Identifier] = ["code" => $r->ItemName->en, "question" => $r->Summary->en];
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
        if (isset(Yii::app()->session['ImportQuestionFromColecticaAccessToken'])) {
             $header[] = "Authorization: Bearer " . Yii::app()->session['ImportQuestionFromColecticaAccessToken'];
        }
        if ($method == 'get' && !empty($params)) {
            $url = ($url . '?' . http_build_query($params));
        } else if ($method == 'post') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            $header[]  =  'Content-Type: application/json';
            $header[]  =  'Accept: */*';
            $header[] =  'Content-Length: ' . strlen(json_encode($params));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($curl, CURLOPT_VERBOSE, true);
        }
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
