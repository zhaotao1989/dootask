<?php

namespace App\Models;


use App\Module\Base;
use Cache;

/**
 * Class User
 * @package App\Models
 */
class User extends AbstractModel
{
    protected $hidden = [
        'encrypt',
        'userpass',
    ];

    /**
     * 昵称
     * @param $value
     * @return string
     */
    public function getNicknameAttribute($value)
    {
        return $value ?: $this->username;
    }

    /**
     * 头像地址
     * @param $value
     * @return string
     */
    public function getUseringAttribute($value)
    {
        return self::userimg($value);
    }

    /**
     * 身份权限
     * @param $value
     * @return array
     */
    public function getIdentityAttribute($value)
    {
        return is_array($value) ? $value : explode(",", trim($value, ","));
    }



    /**
     * userid获取用户名
     * @param $userid
     * @return mixed
     */
    public static function userid2username($userid)
    {
        if (empty($userid)) {
            return '';
        }
        return self::whereUserid(intval($userid))->value('username');
    }

    /**
     * 用户名获取userid
     * @param $username
     * @return int
     */
    public static function username2userid($username)
    {
        if (empty($username)) {
            return 0;
        }
        return intval(self::whereUsername($username)->value('userid'));
    }

    /**
     * token获取会员userid
     * @return int
     */
    public static function token2userid()
    {
        return self::authFind('userid', Base::getToken());
    }

    /**
     * token获取会员账号
     * @return int
     */
    public static function token2username()
    {
        return self::authFind('username', Base::getToken());
    }

    /**
     * token获取encrypt
     * @return mixed|string
     */
    public static function token2encrypt()
    {
        return self::authFind('encrypt', Base::getToken());
    }

    /**
     * 获取token身份信息
     * @param $find
     * @param null $token
     * @return array|mixed|string
     */
    public static function authFind($find, $token = null)
    {
        if ($token === null) {
            $token = Base::getToken();
        }
        list($userid, $username, $encrypt, $timestamp) = explode("@", base64_decode($token) . "@@@@");
        $array = [
            'userid' => intval($userid),
            'username' => $username ?: '',
            'encrypt' => $encrypt ?: '',
            'timestamp' => intval($timestamp),
        ];
        if (isset($array[$find])) {
            return $array[$find];
        }
        if ($find == 'all') {
            return $array;
        }
        return '';
    }

    /**
     * 用户身份认证（获取用户信息）
     * @return array|mixed
     */
    public static function auth()
    {
        global $_A;
        if (isset($_A["__static_auth"])) {
            return $_A["__static_auth"];
        }
        $authorization = Base::getToken();
        if ($authorization) {
            $authInfo = self::authFind('all', $authorization);
            if ($authInfo['userid'] > 0) {
                $loginValid = floatval(Base::settingFind('system', 'loginValid')) ?: 720;
                $loginValid *= 3600;
                if ($authInfo['timestamp'] + $loginValid > time()) {
                    $row = self::whereUserid($authInfo['userid'])->whereUsername($authInfo['username'])->whereEncrypt($authInfo['encrypt'])->first();
                    if ($row) {
                        if ($row->token) {
                            $timestamp = self::authFind('timestamp', $row->token);
                            if ($timestamp + $loginValid > time()) {
                                $upArray = [];
                                if (Base::getIp() && $row->lineip != Base::getIp()) {
                                    $upArray['lineip'] = Base::getIp();
                                }
                                if ($row->linedate + 30 < time()) {
                                    $upArray['linedate'] = time();
                                }
                                if ($upArray) {
                                    $row->updateInstance($upArray);
                                    $row->save();
                                }
                                return $_A["__static_auth"] = $row;
                            }
                        }
                    }
                }
            }
        }
        return $_A["__static_auth"] = false;
    }

    /**
     * 用户身份认证（获取用户信息）
     * @return array
     */
    public static function authE()
    {
        $user = self::auth();
        if (!$user) {
            $authorization = Base::getToken();
            if ($authorization) {
                return Base::retError('身份已失效,请重新登录！', $user, -1);
            } else {
                return Base::retError('请登录后继续...', [], -1);
            }
        }
        return Base::retSuccess("auth", $user);
    }

    /**
     * 生成token
     * @param $userinfo
     * @return string
     */
    public static function token($userinfo)
    {
        return base64_encode($userinfo['userid'] . '@' . $userinfo['username'] . '@' . $userinfo['encrypt'] . '@' . time() . '@' . Base::generatePassword(6));
    }

    /**
     * 判断用户权限（身份）
     * @param $identity
     * @return array
     */
    public static function identity($identity)
    {
        $user = self::auth();
        if (is_array($user->identity)
            && in_array($identity, $user->identity)) {
            return Base::retSuccess("success");
        }
        return Base::retError("权限不足！");
    }

    /**
     * 判断用户权限（身份）
     * @param $identity
     * @return bool
     */
    public static function identityCheck($identity)
    {
        if (is_array($identity)) {
            foreach ($identity as $id) {
                if (!Base::isError(self::identity($id)))
                    return true;
            }
            return false;
        }
        return Base::isSuccess(self::identity($identity));
    }

    /**
     * 判断用户权限（身份）
     * @param $identity
     * @param $userIdentity
     * @return bool
     */
    public static function identityRaw($identity, $userIdentity)
    {
        $userIdentity = is_array($userIdentity) ? $userIdentity : explode(",", trim($userIdentity, ","));
        return $identity && in_array($identity, $userIdentity);
    }

    /**
     * userid 获取 基本信息
     * @param int $userid 会员ID
     * @return array
     */
    public static function userid2basic(int $userid)
    {
        global $_A;
        if (empty($userid)) {
            return [];
        }
        if (isset($_A["__static_userid2basic_" . $userid])) {
            return $_A["__static_userid2basic_" . $userid];
        }
        $fields = ['userid', 'username', 'nickname', 'userimg'];
        $userInfo = self::whereUserid($userid)->select($fields)->first();
        if ($userInfo) {
            $userInfo->userimg = self::userimg($userInfo->userimg);
        }
        return $_A["__static_userid2basic_" . $userid] = ($userInfo ?: []);
    }

    /**
     * username 获取 基本信息
     * @param string $username 用户名
     * @return array
     */
    public static function username2basic(string $username)
    {
        global $_A;
        if (empty($username)) {
            return [];
        }
        if (isset($_A["__static_username2basic_" . $username])) {
            return $_A["__static_username2basic_" . $username];
        }
        $fields = ['userid', 'username', 'nickname', 'userimg'];
        $userInfo = self::whereUsername($username)->select($fields)->first();
        if ($userInfo) {
            $userInfo->userimg = self::userimg($userInfo->userimg);
        }
        return $_A["__static_username2basic_" . $username] = ($userInfo ?: []);
    }

    /**
     * 用户头像，不存在时返回默认
     * @param string $var 头像地址 或 会员用户名
     * @return string
     */
    public static function userimg(string $var)
    {
        if (!Base::strExists($var, '.')) {
            if (empty($var)) {
                $var = "";
            } else {
                $userInfo = self::username2basic($var);
                $var = $userInfo['userimg'];
            }
        }
        return $var ? Base::fillUrl($var) : url('images/other/avatar.png');
    }

    /**
     * 更新首字母
     * @param $userid
     */
    public static function AZUpdate($userid)
    {
        $row = self::whereUserid($userid)->select(['username', 'nickname'])->first();
        if ($row) {
            $row->az = Base::getFirstCharter($row->nickname);
            $row->save();
        }
    }

    /**
     * 是否需要验证码
     * @param $username
     * @return array
     */
    public static function needCode($username)
    {
        $loginCode = Base::settingFind('system', 'loginCode');
        switch ($loginCode) {
            case 'open':
                return Base::retSuccess('need');

            case 'close':
                return Base::retError('no');

            default:
                if (Cache::get("code::" . $username) == 'need') {
                    return Base::retSuccess('need');
                } else {
                    return Base::retError('no');
                }
        }
    }
}
