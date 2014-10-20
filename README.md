环信 REST API SDK
=======
部分接口未实现,只做了目前项目上用到的接口，有时间把所有接口全部做出来。

欢迎大家帮我把剩下的接口补充起来。

##Composer
本项目支持Composer安装

    $ composer require jasonwwl/easemob

##快速使用
本项目依赖 [php-curl-class/php-curl-class](https://github.com/php-curl-class/php-curl-class)

注意`easemob/storage`目录要可写，因为需要保存token信息
```php
require 'Curl.php';
require 'Easemob.php';

$easemob = new Easemob(array(
  'client_id' => '环信client_id',
  'client_secret' => '环信client_secret',
  'org_name' => 'org_name',
  'app_name' => 'app_name'
));
```

###创建新用户[授权模式]

```php
$easemob->userAuthorizedRegister('username','password');
```

###查看用户是否在线

```php
$easemob->userOnline('username');
```

###向群组中加一个人

```php
$easemob->groupAddUser('group_id','username');
```

###删除一个用户

```php
$easemob->userDelete('username');
```

###给指定的群/用户发送消息

考虑到环信的webim目前不支持REST过来的消息读取扩展字段`ext`，所以下方options数组中可设置mixed键，为`true`时 `ext`的内容会被格式化成`JSON`字符串并放入消息内容中。

群`group_id` 或 用户`username` 可为单个`String` 也可为多个 `一维数组`

群：

```php
$easemob->sendToGroups('group_id','from who?',array(
    'mixed' => true,
    'ext' => array(
        'a' => 'aa',
        'b' => 'bb'
    )
));
```
用户：
```php
$easemob->sendToUsers('username','from who?',array(
    'mixed' => true,
    'ext' => array(
        'a' => 'aa',
        'b' => 'bb'
    )
));
```

目前就实现了这些，其余接口欢迎大家增加。
