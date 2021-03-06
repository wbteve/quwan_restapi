<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/17
 * Time: 16:10
 */

namespace App\Services;


use App\Exceptions\UnprocessableEntityHttpException;
use App\Models\Attractions;
use App\Models\CidMap;
use App\Models\Destination;
use App\Models\DestinationJoin;
use App\Models\Hall;
use App\Models\Hotel;
use App\Models\Img;
use App\Models\Route;

class MudiService
{
    protected $destinationJoin;
    protected $destination;
    protected $img;
    protected $attractions;
    protected $cidMap;
    protected $route;
    protected $hall;
    protected $hotel;

    public function __construct(
        Hotel $hotel,
        Hall $hall,
        Route $route,
        CidMap $cidMap,
        DestinationJoin $destinationJoin,
        Destination $destination,
        Img $img,
        Attractions $attractions
    )
    {


        $this->hotel = $hotel;
        $this->hall = $hall;
        $this->route = $route;
        $this->cidMap = $cidMap;
        $this->destinationJoin = $destinationJoin;
        $this->destination = $destination;
        $this->img = $img;
        $this->attractions = $attractions;

    }

    public function getData($destinationId)
    {
        list($data, $hall, $hotel,$route, $attractions) = $this->getMuDiInfo($destinationId);

        //所有景点图片
        $data['img'] = $this->img->getImgs($attractions, $this->img::IMG_TYPE_A);

        //所有线路分类
        $data['cid'] = $this->cidMap->getMudiLists($route, $this->cidMap::CID_MAP_TYPE_3);

        //2个销量最好的景点
        $data['attractions'] = $this->attractions->getMudiLists($attractions);

        //个使用最多的线路
        $data['route'] = $this->route->getMudiList($route);

        //两个评价最多的酒店
        $data['hotel'] = $this->hotel->getMudiList($hotel);

        //两个评价最多的餐厅
        $data['hall'] = $this->hall->getMudiList($hall);

        return $data;
    }



    public function getDataList($destinationId,$key)
    {
        list($data, $hall, $hotel,$route, $attractions) = $this->getMuDiInfo($destinationId);



        $res = [];
        switch ($key)
        {
            case 'attractions':
                //景点
                $res = $this->attractions->getMudiLists($attractions,0);
                break;
            case 'route':
                //线路
                $res = $this->route->getMudiList($route,0);
                break;
            case 'hotel':
                //酒店
                $res = $this->hotel->getMudiList($hotel,0);
                break;
            case 'hall':
                //餐厅
                $res = $this->hall->getMudiList($hall,0);
                break;

            default:
                break;
        }

        return $res;
    }

    /**
     * @param $destinationId
     *
     * @return array
     */
    public function getMuDiInfo($destinationId): array
    {
        $data = $this->destination->getInfo($destinationId);
        if (!$data)
        {
            throw new UnprocessableEntityHttpException(850004);
        }
        $data = $data->toArray();

        //获取目的地下关联数据
        $joinData = $this->destinationJoin->getJoinData($destinationId);

        if (true === empty($joinData))
        {
            throw new UnprocessableEntityHttpException(850004);
        }

        $attractions = $route = $hotel = $hall = [];

        foreach ($joinData as $key => $value)
        {
            //1景点,2路线,3酒店,4餐厅
            switch ((int)$value['destination_join_type'])
            {
                case $this->destinationJoin::DESTINATION_JOIN_TYPE_A:
                    $attractions[] = $value['join_id'];
                    break;
                case $this->destinationJoin::DESTINATION_JOIN_TYPE_B:
                    $route[] = $value['join_id'];
                    break;
                case $this->destinationJoin::DESTINATION_JOIN_TYPE_C:
                    $hotel[] = $value['join_id'];
                    break;
                case $this->destinationJoin::DESTINATION_JOIN_TYPE_D:
                    $hall[] = $value['join_id'];
                    break;
                default:
                    break;
            }
        }
        return array($data, $hall, $hotel,$route, $attractions);
    }
}