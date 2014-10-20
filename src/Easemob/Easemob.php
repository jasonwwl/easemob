<?php
namespace Easemob;

use Curl\Curl;
use Whoops\Exception\ErrorException;

class Easemob
{
    const URL = 'https://a1.easemob.com';
//    const
    private $client_id;
    private $client_secret;
    private $org_name;
    private $app_name;
    private $url;

    /**
     * 初始化环形参数
     *
     * @param array $options
     * @param $options ['client_id']
     * @param $options ['client_secret']
     * @param $options ['org_name']
     * @param $options ['app_name']
     */
    public function __construct($options)
    {
        $paramsMap = array(
            'client_id',
            'client_secret',
            'org_name',
            'app_name'
        );
        foreach ($paramsMap as $paramsName) {
            if (!isset($options[$paramsName])) {
                throw new \InvalidArgumentException("初始化未设置[{$paramsName}]");
            } else {
                $this->$paramsName = $options[$paramsName];
            }
        }
        $this->url = self::URL . '/' . $this->org_name . '/' . $this->app_name;
    }

    /**
     * 创建新用户[授权模式]
     * @param $username
     * @param $password
     * @return mixed
     * @throws \ErrorException
     */
    public function userAuthorizedRegister($username, $password)
    {
        $url = $this->url . '/users';
        return $this->contact($url, array(
            'username' => $username,
            'password' => $password
        ));
    }

    /**
     * 查看用户是否在线
     * @param $username
     * @return bool
     * @throws \ErrorException
     */
    public function userOnline($username)
    {
        $url = $this->url . '/users/' . $username . '/status';
        $res = $this->contact($url, '', 'GET');
        if (isset($res['data'])) {
            if (isset($res['data'][$username])) {
                return ($res['data'][$username] === 'online');
            }
        }
        return false;
    }

    /**
     * 向群组中加一个人
     * @param $groupId
     * @param $username
     * @return mixed
     * @throws \ErrorException
     */
    public function groupAddUser($groupId, $username)
    {
        $url = $this->url . '/chatgroups/' . $groupId . '/users/' . $username;
        return $this->contact($url);
    }

    /**
     * 删除一个用户
     * @param $username
     * @return mixed
     * @throws \ErrorException
     */
    public function userDelete($username)
    {
        $url = $this->url . '/users/' . $username;
        return $this->contact($url, '', 'DELETE');
    }

    /**
     * @param string|array $groupId 发给群ID
     * @param string $from 谁发的
     * @param array $options
     * @param $options ['mixed'] 是否需要将ext的内容同时发送到txt里 环信的webim不支持接受ext 故加入此功能
     * @param $options ['msg'] 消息内容
     * @param $options ['ext'] 扩展消息内容
     * @return mixed
     */
    public function sendToGroups($groupId, $from, $options)
    {
        return $this->sendMessage($from, $groupId, $options, 'chatgroups');
    }

    /**
     * @param string|array $username 发给谁
     * @param string $from 谁发的
     * @param array $options
     * @param $options ['mixed'] 是否需要将ext的内容同时发送到txt里 环信的webim不支持接受ext 故加入此功能
     * @param $options ['msg'] 消息内容
     * @param $options ['ext'] 扩展消息内容
     * @return mixed
     */
    public function sendToUsers($username, $from, $options)
    {
        return $this->sendMessage($from, $username, $options);
    }

    /**
     * @param string $from 谁发的
     * @param string|array $to 发给谁,人或群
     * @param array $options
     * @param $options ['mixed'] 是否需要将ext的内容同时发送到txt里 环信的webim不支持接受ext 故加入此功能
     * @param $options ['msg'] 消息内容
     * @param $options ['ext'] 扩展消息内容
     * @param string $target_type 群还是人
     * @return mixed
     * @throws \ErrorException
     */
    private function sendMessage($from, $to, $options, $target_type = 'users')
    {
        $data = array(
            'target_type' => $target_type,
            'target' => is_array($to) ? $to : array($to),
            'from' => $from,
        );
        if (isset($options['mixed'])) {
            $data['msg'] = array(
                'type' => 'txt',
                'msg' => json_encode($options['ext'])
            );
        }
        if (isset($options['msg'])) {
            $data['msg'] = array(
                'type' => 'txt',
                'msg' => json_encode($options['msg'])
            );
        }
        if (isset($options['ext'])) {
            $data['ext'] = $options['ext'];
        }
        $url = $this->url . '/messages';
        return $this->contact($url, $data);
    }

    /**
     * 获取token
     * @return bool
     * @throws \ErrorException
     */
    private function getToken()
    {
        $token = $this->cacheToken();
        if ($token) {
            return $token;
        } else {
            $option ['grant_type'] = "client_credentials";
            $option ['client_id'] = $this->client_id;
            $option ['client_secret'] = $this->client_secret;
            $token = $this->contact($this->url . '/token', $option);
            if (isset($token['access_token'])) {
                $this->cacheToken($token);
                return $token['access_token'];
            } else {
                return false;
            }
        }
    }

    /**
     * 持久化token
     * @param bool $saveToken
     * @return bool
     */
    private function cacheToken($saveToken = false)
    {
        $cacheFilePath = dirname(dirname(dirname(__FILE__))) . '/storage/data';
        if ($saveToken) {
            $saveToken['expires_in'] = $saveToken['expires_in'] + time();
            $fp = @fopen($cacheFilePath, 'w');
            @fwrite($fp, serialize($saveToken));
            fclose($fp);
        } else {
            $fp = @fopen($cacheFilePath, 'r');
            if ($fp) {
                $data = unserialize(fgets($fp));
                fclose($fp);
                if (!isset($data['expires_in']) || !isset($data['access_token'])) {
                    return false;
                }
                if ($data['expires_in'] < time()) {
                    return false;
                } else {
                    return $data['access_token'];
                }
            }
            return false;
        }
    }

    /**
     * 向环信请求
     * @param $url
     * @param string $params
     * @param string $type POST|GET
     * @return mixed
     * @throws \ErrorException
     */
    private function contact($url, $params = '', $type = 'POST')
    {
        $postData = '';
        if (is_array($params)) {
            $postData = json_encode($params);
        }
        $curl = new Curl();
        $curl->setUserAgent('Jasonwx/Easemob SDK; Jason Wang<jasonwx@163.com>');
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOpt(CURLOPT_SSL_VERIFYHOST, false);
        $curl->setHeader('Content-Type', 'application/json');
        if ($url !== $this->url . '/token') {
            $token = $this->getToken();
            $curl->setHeader('Authorization', 'Bearer ' . $token);
        }
        switch ($type) {
            case 'POST': {
                $curl->post($url, $postData);
                break;
            }
            case 'GET': {
                $curl->get($url);
                break;
            }
            case 'DELETE': {
                $curl->delete($url);
                break;
            }
        }
        $curl->close();
        if ($curl->error) {
            throw new \ErrorException('CURL Error: ' . $curl->error_message, $curl->error_code);
        }
        return json_decode($curl->raw_response, true);
    }


}