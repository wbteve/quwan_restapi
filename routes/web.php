<?php

$prefix     = 'quwan';
$namespace  = 'App\Http\Controllers';

//$app->get($prefix, function () {
//    return 'Welcome to quwan';
//});

/**
 * 允许的版本,即当前主版本
 */
$allowVersion = array(1, 2);

$versionAccept = $app->make('request')->header('accept');
$version = getVersion($versionAccept, $allowVersion) ?: '\V1';


//无需用户认证
$unAuthGroup = [
    'prefix'     => $prefix,
    'namespace'  => $namespace.$version,
    'middleware' => ['lang']
];
$app->group($unAuthGroup, function () use ($app) {


    $app->post('index_count', 'XSController@getDbTotal'); //参看索引的文档总量
    $app->post('clean_index', 'XSController@cleanIndex'); //清空索引
    $app->post('add_index', 'XSController@addIndex'); //添加文档到索引
    $app->post('edit_index', 'XSController@editIndex'); //修改文档
    $app->post('del_index', 'XSController@delIndex'); //删除文档
    $app->post('search', 'XSController@search'); //搜索
    $app->post('search_suggest', 'XSController@suggest'); //搜索建议

    //$app->get('/', 'HomeController@wx'); //添加文档到索引

//    $app->get('oauth_callback', 'TestController@oauthCallback'); //授权回调
    $app->post('get_openid', 'OrderController@getOpenid'); //获取微信openid
    $app->get('send_merchant_pay', 'OrderController@sendMerchantPay'); //企业支付
    $app->get('send_refund', 'OrderController@sendRefundo'); //退款
    $app->get('send_hong_bao', 'OrderController@sendHongBao'); //发送红包
//    $app->get('add_order', 'OrderController@addOrder'); //创建订单
    $app->get('notify_url', 'OrderController@notifyUrl'); //订单回调
    $app->post('notify_url', 'OrderController@notifyUrl'); //订单回调
   $app->get('send_xcx_moban', 'OrderController@sendMoban'); //发送小程序模板消息
   $app->get('get_access_token', 'OrderController@getAccessToken'); //获取微信get_access_token
//    $app->get('send_moban', 'TestController@sendMoban'); //发送模板消息

    $app->post('login', 'LoginController@login'); //登录

   $app->post('test_send_sms', 'TestController@sendSms'); //测试发送短信
    //$app->post('qiniu', 'TestController@qiniu'); //上传到7牛
   $app->post('del_cache', 'TestController@delCache'); //删除指定缓存


//    $app->get('add_data', 'HomeController@addData'); //增加默认

    $app->get('home', 'HomeController@index'); //首页数据
    $app->get('mudi/{destination_id}', 'MudiController@index'); //目的地详情页数据
    $app->get('mudi/list/attractions', 'MudiController@attractions'); //目的地->相关景点
    $app->get('mudi/list/route', 'MudiController@route'); //目的地->相关线路
    $app->get('mudi/list/hotel', 'MudiController@hotel'); //目的地->相关酒店
    $app->get('mudi/list/hall', 'MudiController@hall'); //目的地->相关餐厅
    $app->get('hotel/{hotel_id}', 'HotelController@index'); //酒店详情页数据
    $app->get('hall/{hall_id}', 'HallController@index'); //餐厅详情页数据
    $app->get('holiday/{holiday_id}', 'HolidayController@index'); //节日详情页数据
    $app->get('attractions/{attractions_id}', 'AttractionsController@index'); //景点详情页数据
    $app->get('route/{route_id}', 'RouteController@index'); //线路详情页数据
    $app->get('attractions_cid', 'AttractionsController@cid'); //景点分类列表


    $app->get('score', 'ScoreController@index'); //评价列表
    $app->get('hongbao', 'OrderController@hongbao'); //红包设置

    $app->get('auto_holiday_sms', 'OrderController@autoHolidaySms'); //节日来临提醒
    $app->get('auto_order_cancel', 'OrderController@autoOrderCancel'); //自动取消未支付订单

});


//需要用户认证
$authGroup = [
    'prefix'     => $prefix,
    'namespace'  => $namespace.$version,
    'middleware' => ['lang', 'jwt']
];
$app->group($authGroup, function () use ($app) {


    $app->post('use_route', 'RouteController@use'); //使用线路
    $app->post('add_route', 'RouteController@add'); //添加线路
    $app->post('edit_route', 'RouteController@edit'); //编辑线路
    $app->get('my_route', 'RouteController@myRoute'); //我的线路
    $app->post('del_route', 'RouteController@del'); //删除我的线路

    $app->post('fav', 'FavController@add'); //收藏/取消
    $app->get('fav_list', 'FavController@favList'); //收藏列表

    $app->post('add_score', 'ScoreController@add'); //发布评价
    $app->post('add_suggest', 'SuggestController@addSuggest'); //发布建议反馈


    $app->post('edit_lbs', 'UserController@editLbs'); //修改用户经纬度信息
    $app->get('user_info', 'UserController@userInfo'); //获取用户信息
    $app->post('edit_user_info', 'UserController@editUserInfo'); //编辑用户信息
    $app->post('bind_mobile', 'UserController@bindMobile'); //绑定用户手机


    $app->post('buy', 'OrderController@buy'); //购买 [景点,节日]
    $app->post('buy_route', 'OrderController@buyRoute'); //购买线路
    $app->post('edit_original', 'OrderController@editOriginal'); //更新主订单信息(购买线路,离开后调用)
    $app->get('order_count', 'UserController@orderCount'); //订单统计信息
    $app->get('order_list', 'OrderController@orderList'); //订单列表
    $app->get('order_info', 'OrderController@orderInfo'); //订单详情
    $app->post('order_buy', 'OrderController@orderBuy'); //订单支付
    $app->post('order_cancel', 'OrderController@orderCancel'); //手动取消订单
    $app->post('order_refund', 'OrderController@orderRefund'); //订单退款
    $app->post('send_hongbao', 'OrderController@sendMerchantPay'); //发红包(企业支付)


    $app->get('message_list', 'MessageController@messageList'); //消息列表
    $app->get('message_info/{message_id}', 'MessageController@messageInfo'); //消息详情
    $app->post('message_read', 'MessageController@messageRead'); //所有消息已读




    $app->post('send_sms', 'UserController@sendSms'); //发送短信
    $app->post('qiniu', 'UserController@qiniu'); //上传到7牛

    $app->post('share_ok', 'UserController@shareOk'); //分享成功增加统计



    $app->get('logout', 'UserController@logout'); //登出


});
