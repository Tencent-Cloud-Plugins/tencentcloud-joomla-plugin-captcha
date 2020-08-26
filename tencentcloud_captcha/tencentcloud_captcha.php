<?php

/*
 * Copyright (C) 2020 Tencent Cloud.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

defined('_JEXEC') or die;

require_once JPATH_BASE. '/plugins/captcha/tencentcloud_captcha/vendor/autoload.php';
use Joomla\Utilities\IpHelper;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Captcha\V20190722\CaptchaClient;
use TencentCloud\Captcha\V20190722\Models\DescribeCaptchaResultRequest;
use TencentCloud\Ms\V20180408\MsClient;
use TencentCloud\Ms\V20180408\Models\DescribeUserBaseInfoInstanceRequest;


class PlgCaptchaTencentcloud_captcha extends JPlugin{

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;
    //验证码验证通过标记 1-通过 其它-失败
    const VERIFY_SUCCESS_FLG = 1;


    /**
     * 应用名称
     * @var string
     */
    private $site_app = 'Joomla';

    /**
     * 插件类型
     * @var string
     */
    private $plugin_type = 'captcha';

    /**
     * 用户uin
     * @var
     */
    private $uin = '';

    /**
     * 上报url
     * @var string
     */
    private $log_server_url = 'https://openapp.qq.com/api/public/index.php/upload';


    /**
     * Initialise the captcha
     *
     * @param   string  $id  The id of the field.
     *
     * @return  Boolean	True on success, false otherwise
     *
     * @since   2.5
     * @throws  \RuntimeException
     */
    public function onInit($id = 'dynamic_tencentcloud_captcha')
    {
        $pluginConf = $this->getPluginConf();
        if (!$pluginConf) {
            $this->report('activate');
            $this->setPluginConf();
        } else {
            if (time() - $pluginConf['use_time'] >= 24*3600) {
                $this->report('activate');
                $this->updatePluginConf();
            }
        }

        $captchaAppId = $this->params->get('CaptchaAppId', '');

        if ($captchaAppId === '') {
            throw new \RuntimeException(JText::_('PLG_TENCENTCLOUD_CAPTCHA_ERROR_NO_APPID'));
        }
        // Load callback first for browser compatibility
        JHtml::_('script', 'tencentcloud_captcha/tencentcloudcaptcha.js', array('version' => 'auto', 'relative' => true));
        JHtml::_('script', 'https://ssl.captcha.qq.com/TCaptcha.js');

        return true;
    }



    /**
     * Gets the challenge HTML
     *
     * @param   string  $name   The name of the field. Not Used.
     * @param   string  $id     The id of the field.
     * @param   string  $class  The class of the field.
     *
     * @return  string  The HTML to be embedded in the form.
     *
     * @since  2.5
     */
    public function onDisplay($name = null, $id = 'dynamic_tencentcloud_captcha', $class = '')
    {
        $captchaAppId = $this->params->get('CaptchaAppId', '');
        $html ='<div id="'.$id.'">
	    <button type="button" name="codeVerifyButton" onclick="tencentcaptcha()" id="codeVerifyButton" data-appid="'.$captchaAppId.'" class="button">我不是人机</button>
	    <input type="button" id="codePassButton" disabled="disabled" style="display: none" value="已通过验证" />
	    <input type="hidden" id="codeVerifyTicket" name="codeVerifyTicket" value=""/>
	    <input type="hidden" id="codeVerifyRandstr" name="codeVerifyRandstr" value=""/></div>';
        return $html;
    }

    /**
     * Calls an HTTP POST function to verify if the user's guess was correct
     *
     * @param   string  $code  Answer provided by user. Not needed for the Recaptcha implementation
     *
     * @return  True if the answer is correct, false otherwise
     *
     * @since   2.5
     * @throws  \RuntimeException
     */
    public function onCheckAnswer($code = null)
    {
        $input      = \JFactory::getApplication()->input;
        $secretId = $this->params->get('SecretId');
        $secretKey = $this->params->get('SecretKey');
        $captchaAppId = $this->params->get('CaptchaAppId');
        $captchaAppKey = $this->params->get('CaptchaAppKey');

        $result = self::verifyCodeReal($secretId,$secretKey, $input->getString('codeVerifyTicket'),$input->getString('codeVerifyRandstr'),$captchaAppId,$captchaAppKey);
        //判断返回结果是否通过
        if ($result['CaptchaCode'] != self::VERIFY_SUCCESS_FLG) {
            return false;
        }else{
            return true;
        }
        return true;
    }

    /**
     * 验证码服务端验证
     * @param $secretID 腾讯云密钥ID
     * @param $secretKey 腾讯云密钥Key
     * @param $ticket 用户验证票据
     * @param $randStr 用户验证时随机字符串
     * @param $codeAppId 验证码应用ID
     * @param $codeSecretKey 验证码应用蜜月
     * @return array|mixed
     */
    public static function verifyCodeReal($secretID, $secretKey,$ticket, $randStr, $codeAppId, $codeSecretKey){
        try {
            $remote_ip = IpHelper::getIp();
            $cred = new Credential($secretID, $secretKey);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("captcha.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new CaptchaClient($cred, "", $clientProfile);
            $req = new DescribeCaptchaResultRequest();
            $params = array('CaptchaType' => 9, 'Ticket' => $ticket, 'Randstr' => $randStr, 'CaptchaAppId' => intval($codeAppId), 'AppSecretKey' => $codeSecretKey, 'UserIp' => $remote_ip);
            $req->fromJsonString(json_encode($params));
            $resp = $client->DescribeCaptchaResult($req);
            return json_decode($resp->toJsonString(), JSON_OBJECT_AS_ARRAY);
        } catch (TencentCloudSDKException $e) {
            return array('requestId' => $e->getRequestId(), 'errorCode' => $e->getErrorCode(), 'errorMessage' => $e->getMessage());
        }
    }


    private function report($action)
    {
        //数据上报
        $conf = $this->getConf();
        if ($params = $this->getParams()) {
            //获取用户UserUin
            if (!$this->uin) {
                $this->uin = $this->getUserUinBySecret($params['SecretId'],$params['SecretKey']);
            }

            $data = array(
                'action' => $action,
                'plugin_type' => $this->plugin_type,
                'data' => array(
                    'site_id'  => $conf['site_id'],
                    'site_url' => $conf['site_url'],
                    'site_app' => $this->site_app,
                    'uin' => $this->uin ?: '',
                    'others' => json_encode(array('captcha_appid'=>$params['CaptchaAppId']))
                )
            );
            $this->sendUserExperienceInfo($data);
        }
    }

    /**
     * 获取腾讯云配置
     */
    private function getConf()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['site_id','site_url','uin']))
            ->from($db->quoteName('#__tencentcloud_conf'))
            ->where('1=1 limit 1');
        $db->setQuery($query);

        try
        {
            $row = $db->loadAssoc();
        }
        catch (RuntimeException $e)
        {
            return false;
        }

        return $row;
    }


    /**
     * 获取腾讯云插件配置
     * @return bool|mixed|null
     */
    private function getPluginConf()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['type','uin','use_time']))
            ->from($db->quoteName('#__tencentcloud_plugin_conf'))
            ->where($db->quoteName('type') . " = '". $this->plugin_type ."' limit 1");
        $db->setQuery($query);

        try
        {
            $row = $db->loadAssoc();
        }
        catch (RuntimeException $e)
        {
            return false;
        }

        return $row;
    }


    /**
     * 写入腾讯云插件配置
     */
    private function setPluginConf()
    {
        $type = $this->plugin_type;
        $useTime = time();

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__tencentcloud_plugin_conf'))
            ->columns(array($db->quoteName('type'), $db->quoteName('uin'), $db->quoteName('use_time')))
            ->values($db->quote($type) . ', ' . $db->quote($this->uin) . ', ' . $db->quote($useTime));
        $db->setQuery($query);

        try
        {
            $db->execute();
        }
        catch (RuntimeException $e)
        {
            return false;
        }
    }



    /**
     * 更新腾讯云插件配置
     */
    private function updatePluginConf()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__tencentcloud_conf'))
            ->set($db->quoteName('uin') . ' = ' . $this->uin .' , '.$db->quoteName('use_time') . ' = ' . time())
            ->where("type = '" . $this->type . "'");
        $db->setQuery($query);

        try
        {
            $db->execute();
        }
        catch (RuntimeException $e)
        {
            return false;
        }
    }


    /**
     * 发送post请求
     * @param   string    地址
     * @param   mixed   参数
     */
    private static function sendPostRequest($url, $data)
    {
        ob_start();
        $json_data = json_encode($data);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);
        curl_exec($curl);
        curl_exec($curl);
        curl_close($curl);
        ob_end_clean();
    }


    /**
     * 上报数据
     * @param $data
     * @return bool|void
     */
    private function sendUserExperienceInfo($data)
    {
        if (empty($data) || !is_array($data) || !isset($data['action'])) {
            return ;
        }
        $url = $this->log_server_url;
        $this->sendPostRequest($url, $data);
        return true;
    }

    /**
     * 获取腾讯云验证码插件的用户密钥
     * @return array|bool   用户密钥
     */
    private function getParams()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('params','type')))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('name') . " = 'plg_tencentcloud_captcha'");
        $db->setQuery($query);

        $params = $db->loadAssoc();
        if (!isset($params['params']) || !$params['params']) {
            return false;
        }
        return json_decode($params['params'], true);
    }


    /**
     * 获取用户基础信息 UserUin
     * @param   $option string  腾讯云账号的密钥信息 SecretId 和SecretKey
     * @return  bool|mixed      UserUin的值
     */
    private function getUserUinBySecret($secretId, $secretKey)
    {
        if ( empty($secretId) || empty($secretKey)) {
            return '';
        }
        try {
            $cred = new Credential($secretId, $secretKey);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("ms.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new MsClient($cred, "", $clientProfile);
            $req = new DescribeUserBaseInfoInstanceRequest();
            $params = "{}";
            $req->fromJsonString($params);

            $resp = $client->DescribeUserBaseInfoInstance($req);
            if (is_object($resp)) {
                $result = json_decode($resp->toJsonString(), true);
                return isset($result['UserUin']) ? $result['UserUin'] : '';
            } else {
                return '';
            }
        } catch (TencentCloudSDKException $e) {
            echo '';
        }
    }
}
