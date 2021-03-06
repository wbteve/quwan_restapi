<?php

namespace App\Http\Controllers\V1;


use App\Exceptions\UnprocessableEntityHttpException;
use App\Models\Article;
use App\Models\User;
use App\Services\QiNiuService;
use App\Services\SmsService;
use App\Services\YanzhenService;
use EasyWeChat\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\TokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Qcloud\Sms\SmsSingleSender;

/**
 * Class TestController
 * @package App\Http\Controllers\V1
 */
class TestController extends Controller
{
    const CACHE_TAG = 'QUWAN'; //缓存模块tag
    protected $tokenService;
    protected $request;
    protected $XS;
    protected $XSIndex;
    protected $XSDocument;
    protected $XSSearch;
    protected $params;
    protected $yanzhenService;
    protected $smsService;

    public function __construct(
        TokenService $tokenService,
        Request $request,
        YanzhenService $yanzhenService,
        SmsService $smsService
    )
    {

        parent::__construct();
        $this->tokenService = $tokenService;
        $this->request = $request;
        $this->yanzhenService = $yanzhenService;
        $this->smsService = $smsService;

        //接受到的参数
        $this->params = $this->request->all();

    }


    public function sendMoban()
    {
        $wxConfig = config('wx');

        $app = new Application($wxConfig);

        $notice = $app->notice;
        $userId = 'ovwAZuBLwSiize3Zjd-DiCZPWTf8';
        $templateId = 'khV6wM2PO7inzv9octCaRQvp_HbvdHG4J1zIZFSn8xU';
        $url = 'http://www.baidu.com';
        $data = array(
            "first"  => "恭喜你购买成功！",
            "product"   => "巧克力",
            "price"  => "39.8元",
            "time"  => date('Y-m-d H:i:s', time()),
            "remark" => "欢迎再次购买！",
        );

        $result = $notice->uses($templateId)->withUrl($url)->andData($data)->andReceiver($userId)->send();
        return $result;

    }


    //wx
    public function oauthCallback()
    {
        $wxConfig = config('wx');

        $app = new Application($wxConfig);
        $oauth = $app->oauth;

        // 获取 OAuth 授权结果用户信息
        $user = $oauth->user();
        $userArr = $user->toArray();
        Log::error('登录用户: ', $userArr);



        if (false === empty($userArr)) {

            //$targetUrl = empty($userArr['target_url']) ? '/' : $userArr['target_url'];
            $targetUrl = '/quwan/?openid='.$userArr['id'];

            //存在
            $cacheKey = 'quwan:openid:'.$userArr['id'];
            $tag = Cache::tags('quwan')->get($cacheKey);
            if($tag){
                header('location:'. $targetUrl); // 跳转到 user/profile
            }


            //检测用户是否存在不存在注册
            $tag = User::where('openid','=',$userArr['id'])->count();
            if (!$tag) {
                $arr = [
                  'user_nickname' => $userArr['nickname'],
                  'user_sex'  => $userArr['original']['sex'],
                  'user_avatar'  => $userArr['avatar'],
                  'openid'  => $userArr['id'],
                  'user_created_at' => time(),
                  'user_updated_at'  => time(),
                ];
                User::create($arr);
            }

            $cacheKey = 'quwan:openid:'.$userArr['id'];
            Cache::tags('quwan')->put($cacheKey, $userArr['id'],60);

            header('location:'. $targetUrl); // 跳转到 user/profile
        }


    }


    //添加文档到索引
    public function addIndex()
    {

//        //导入数据索引
//        php ./Indexer.php --rebuild --source=mysql://vpgame:vpgame_hangzhouweipei2015@192.168.1.8/vpgame --sql="SELECT * FROM article" --project=article4
//
//        //强制停止重建
//        php ./Indexer.php --stop-rebuild article4
//
//        //查看索引状态
//        php ./Indexer.php --info -p  article4
//
//        //强制刷新 demo 项目的搜索日志
//        php ./Indexer.php --flush-log --project article4
//
//        //清空 demo 项目的索引数据
//        php ./Indexer.php --clean article4


        $value = $this->params;
        // if (!$value['id'] || !$value['author'] || !$value['title'] || !$value['content'] || !$value['post_time']) {
//            throw new UnprocessableEntityHttpException(850005);
        // }

        try {
            $xs = new \XS('article4');

            //初始化索引
            $index = $xs->index;

            // 创建文档对象
            $doc = new \XSDocument();

            $value['content'] = strip_tags($value['content']);
            $value['content'] = lose_space($value['content']);
            $data = array(
                'id' => $value['id'], // 此字段为主键，必须指定
                'author' => $value['author'], // 此字段为主键，必须指定
                'title' => $value['title'], // 此字段为主键，必须指定
                'content' => $value['content'], // 此字段为主键，必须指定
                'post_time' => $value['post_time'], // 此字段为主键，必须指定
                //'chrono' => time()
            );


            $doc->setFields($data);

            //添加到索引
            $tag = $index->add($doc);

            //刷新索引缓存
            $index->flushIndex();
            sleep(2);

            //刷新搜索日志
            $index->flushLogging();
            sleep(2);
            return response_success(['msg' => $tag]);

        } catch (\XSException $e) {
            echo $e;               // 直接输出异常描述
            if (defined('DEBUG'))  // 如果是 DEBUG 模式，则输出堆栈情况
            {
                echo "\n" . $e->getTraceAsString() . "\n";
            }
        }

    }

    public function xs()
    {

        try {
            $key = $this->params['key'] ?? '';
            if (!$key) {
                throw new UnprocessableEntityHttpException(850005);
            }

            $search_begin = microtime(true); //开始执行搜索时间

            $xs = new \XS('article4');


            if (substr_count($key, ' ')) {
                $logKey = $key;
                $key = str_replace(' ', 'AND', $key);
                var_dump('-------连词-------', $key);
            } else {
                //分词 setIgnore过滤标点 setMulti分词长短 getResult获取分词结果
                $tokenizer = new \XSTokenizerScws();
                $key = $tokenizer->setIgnore(true)->setMulti(5)->getResult($key);
                $key = array_pluck($key, 'word');
                $logKey = implode(' ', $key);
                $key = implode('OR', $key);
                var_dump('-------分词-------', $key);
            }

            $search = $xs->search;

            $search->setFuzzy(true); //开启模糊搜索
            //$search->setScwsMulti(8);//搜索语句的分词等级[与setFuzzy使用相互排斥]

            //排序 表示先以 chrono 正序、再以 pid 逆序(pid 是字符串并不是数值所以 12 会排在 3 之后)
            //$sorts = array('chrono' => true, 'pid' => false);
            //$search->setMultiSort($sorts);

            //经纬度排序 lon 代表经度、lat 代表纬度 必须将经度定义在前纬度在后
            //$geo = array('lon' => 116.45, 'lat' => '39.96');
            //$search->setGeodistSort($geo);

            $words = $search->getHotQuery(50, 'total'); //热门词
            var_dump('-------热门词-------', $words);

            $words = $search->getRelatedQuery($key, 10);//相关搜索
            var_dump('-------相关搜索-------', $words);

            $docs = $search->getExpandedQuery($key); //搜索建议
            var_dump('--------搜索建议------', $docs);


            $docs = $search->terms($key); //高亮搜索词
            var_dump('--------高亮搜索词------', $docs);

            //$search->addWeight('title', $this->params['key']); //增加关键字权重

            $count = $search->count($key);
            var_dump('-------搜索匹配总数-------', $count);

            $docs = $search->search($key); //执行搜索
            $log = $search->getQuery($key); //搜索语句
            var_dump('-------sql-------', $log);

            $search_cost = microtime(true) - $search_begin; //执行结束时间
            var_dump('-------执行时间-------', $search_cost);

            $arr = [];
            if (false === empty($docs)) {
                foreach ($docs as $key => $value) {
                    $arr[] = $value->getFieldsArray();
                }
            }
            var_dump('-------结果-------', $arr);

            //添加搜索记录到缓存去
            $search->addSearchLog($logKey);
            //刷新搜索日志
            $xs->index->flushLogging();

        } catch (\XSException $e) {
            echo $e;               // 直接输出异常描述
            if (defined('DEBUG'))  // 如果是 DEBUG 模式，则输出堆栈情况
            {
                echo "\n" . $e->getTraceAsString() . "\n";
            }
        }

    }


//    public function sendSms()
//    {

//        用法
//
//        use Wenpeng\Qsms\Client;
//        $client = new Client($appID, $appKey);
//        单发短信
//
//        use Wenpeng\Qsms\Request\Single;
//        $sms = new Signle($client, 0);
//        单发普通短信
//        use Wenpeng\Qsms\Request\Single;
//        $sms = new Signle($client, 0);
//        $sms->target('18800001111', '86');
//        $response = $sms->normal('这是测试短信内容');
//        {
//        "result": "0", //0表示成功(计费依据)，非0表示失败
//        "errmsg": "", //result非0时的具体错误信息
//        "ext": "some msg", //用户的session内容，腾讯server回包中会原样返回
//        "sid": "xxxxxxx", //标识本次发送id
//        "fee": 1 //短信计费的条数
//        }

//        单发模板短信
//        $sms->target('18800001111', '86');
//        // 短信正文模板编号 1000, 短信正文参数 ['123456', 30]
//        $response = $sms->template(1000, ['123456', 30]);
//        {
//        "result": "0", //0表示成功(计费依据)，非0表示失败
//        "errmsg": "", //result非0时的具体错误信息
//        "ext": "some msg", //用户的session内容，腾讯server回包中会原样返回
//        "sid": "xxxxxxx", //标识本次发送id
//        "fee": 1 //短信计费的条数
//        }

//        return response_success(['token' => $token]);
//    }

    public function login()
    {
        //生成 token
        $userId = 12345;
        $token = $this->tokenService->createToken($userId, 'web');

        return response_success(['token' => $token]);
    }


    public function user()
    {
        return response_success(['userId' => $this->userId]);
    }

    public function logout()
    {
        $bearerToken = $this->request->server->getHeaders()['AUTHORIZATION'] ?? '';
        $claims = $this->tokenService->getJwtClaims($bearerToken);
        $this->tokenService->revokeToken($claims['jti']);

        return response_success(['msg' => '退出成功']);
    }

    public function sendSms()
    {
        $phone = $this->params['phone'] ?? '';
        if (!$this->yanzhenService::isMobile($phone)) {
            throw new UnprocessableEntityHttpException(850009);
        }

        // $res = $this->smsService::send($phone);
        // 58477	普通短信		你购买了{1}，消费了{2}元
        $res = $this->smsService::send(58477, $phone, ['商品名称','123.56']);

        return response_success($res);

    }

    /**
     * 上传到7牛
     * @return \Illuminate\Http\JsonResponse|Response
     */
    public function qiniu()
    {
        $file = Input::file('file');
        if ($file === null) {
            throw new UnprocessableEntityHttpException(850005);
        }

        //检测是否上传成功
        if (!$file->isValid()) {
            throw new UnprocessableEntityHttpException(850006, [], '', ['msg' => $file->getError()]);
        }

        //大小限制1
        $uploadSize = config('qiniu.upload_size');
        if ($file->getClientSize() > $uploadSize) {
            throw new UnprocessableEntityHttpException(850007);
        }

        //类型限制
        $allowed_extensions = config('qiniu.extensions');
        if (!in_array($file->getClientMimeType(), $allowed_extensions)) {
            throw new UnprocessableEntityHttpException(850008);
        }

        $hz_name = substr($file->getClientOriginalName(), strrpos($file->getClientOriginalName(), ".") + 1);
        $destinationPath = 'uploads/imges/';
        $fileName = str_random(10) . '.' . $hz_name;

        //移动到指定文件夹
        $file->move($destinationPath, $fileName);

        list($qiniuUrl, $res) = QiNiuService::uploadQiniu($fileName, $destinationPath);

        return response_success(['url' => $qiniuUrl, 'file_name' => $res[0]['key']]);
    }

    /**
     * 删除饰品模块缓存
     * @return \Illuminate\Http\JsonResponse
     */
    public function delCache()
    {
        $params = $this->params;
        $key = $params['key'] ?? '';

        if ($key) {
            //删除模块下指定缓存
            Cache::tags(self::CACHE_TAG)->forget($key);

            //删除缓存并发锁
            Redis::DEL($key);
        } else {
            //删除模块下缓存
            Cache::tags(self::CACHE_TAG)->flush();
        }

        //204 返回
        return response_success([], Response::HTTP_NO_CONTENT);
    }

}
