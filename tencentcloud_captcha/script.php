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

/**
 * Script file of Joomla CMS
 *
 * @since  1.6.4
 */


class Plgcaptchatencentcloud_captchaInstallerScript
{
    /**
     * db
     * @var JDatabaseDriver|null
     */
    private $db;

    /**
     * 插件商
     * @var string
     */
    private $name = 'tencentcloud';

    /**
     * 上报url
     * @var string
     */
    private $log_server_url = 'https://openapp.qq.com/api/public/index.php/upload';

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



    public function __construct()
    {
        $this->db = JFactory::getDbo();
    }


    /**
     * 安装事件
     * @param   string  $action
     * @param   object  $installer
     */
    public function postflight($action, $installer)
    {
        try {
            //创建表
            $this->createConfTable();
            $this->createPluginConfTable();
            //获取配置
            $conf = $this->getConf();
            //如果没有腾讯配置，则认为是第一次安装,写入初始化配置
            if (!$conf) {
                $this->setConf();
            }
        }
        catch (RuntimeException $e)
        {
            return false;
        }
    }


    /**
     * 卸载事件
     * @param   object  $installer
     */
    public function uninstall($installer)
    {
        try {
            $this->report('uninstall');
            $this->dropPluginConfTable();
        }
        catch (RuntimeException $e)
        {
            return false;
        }

    }


    /**
     * 创建腾讯云全局配置表
     * @return bool|void
     */
    private function createConfTable()
    {
        $db = $this->db;
        $serverType = $db->getServerType();
        if ($serverType != 'mysql')
        {
            return;
        }
        $creaTabSql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__tencentcloud_conf')
            . ' (' . $db->quoteName('name') . " varchar(100) NOT NULL DEFAULT '', "
            . $db->quoteName('site_id') . " varchar(100) NOT NULL DEFAULT '', "
            . $db->quoteName('site_url') . " varchar(255) NOT NULL DEFAULT '', "
            . $db->quoteName('uin') . " varchar(100) NOT NULL DEFAULT '' "
            . ') ENGINE=InnoDB';

        if ($db->hasUTF8mb4Support())
        {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;';
        }
        else
        {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8 DEFAULT COLLATE=utf8_unicode_ci;';
        }
        $db->setQuery($creaTabSql)->execute();
        return true;
    }


    /**
     * 创建腾讯云插件配置表
     * @return bool|void
     */
    private function createPluginConfTable()
    {
        $db = $this->db;
        $serverType = $db->getServerType();
        if ($serverType != 'mysql')
        {
            return;
        }
        $creaTabSql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__tencentcloud_plugin_conf')
            . ' (' . $db->quoteName('type') . " varchar(20) NOT NULL DEFAULT '', "
            . $db->quoteName('uin') . " varchar(20) NOT NULL DEFAULT '',"
            . $db->quoteName('use_time') . " int(11) NOT NULL DEFAULT 0"
            . ') ENGINE=InnoDB';

        if ($db->hasUTF8mb4Support())
        {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;';
        }
        else
        {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8 DEFAULT COLLATE=utf8_unicode_ci;';
        }
        $db->setQuery($creaTabSql)->execute();
        return true;
    }


    private function dropPluginConfTable()
    {
        $db = $this->db;
        $serverType = $db->getServerType();
        if ($serverType != 'mysql')
        {
            return;
        }
        $creaTabSql = 'DROP TABLE IF EXISTS ' . $db->quoteName('#__tencentcloud_plugin_conf');

        $db->setQuery($creaTabSql)->execute();
        return true;
    }



    /**
     * 获取腾讯云配置
     */
    private function getConf()
    {
        $db = $this->db;
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
     * 写入腾讯云配置
     */
    private function setConf()
    {
        $name = $this->name;
        $siteId = uniqid('joomla_');
        $siteUrl = $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER['HTTP_HOST'];

        $db = $this->db;
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__tencentcloud_conf'))
            ->columns(array($db->quoteName('name'), $db->quoteName('site_id'), $db->quoteName('site_url')))
            ->values($db->quote($name) . ', ' . $db->quote($siteId) . ', ' . $db->quote($siteUrl));
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
     * 发送post请求
     * @param   string  地址
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
     * 发送用户信息（非敏感）
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
     * @param   string  $action 上报方法
     */
    private function report($action)
    {
        //数据上报
        $conf = $this->getConf();
        $pluginConf = $this->getPluginConf();
        $params = $this->getParams();
        $captcha_appid = $params ? $params['CaptchaAppId'] : '';
        $data = array(
            'action' => $action,
            'plugin_type' => $this->plugin_type,
            'data' => array(
                'site_id'  => $conf['site_id'],
                'site_url' => $conf['site_url'],
                'site_app' => $this->site_app,
                'uin' => $pluginConf['uin'],
                'others' => json_encode(array('captcha_appid'=>$captcha_appid))
            )
        );
        $this->sendUserExperienceInfo($data);
    }
}