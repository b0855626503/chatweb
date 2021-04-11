<?php

namespace Gametech\Game\Repositories;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Support\Facades\Storage;


class GameRepository extends Repository
{

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Game\Contracts\Game';
    }

    public function getGameUserById($id, $update = true)
    {
        $results = $this->orderBy('sort')->with(['gameUser' => function ($query) use ($id) {
            $query->with('promotion')->where('member_code', $id)->active();

        }])->findWhere(['status_open' => 'Y', 'enable' => 'Y', ['filepic', '<>', '']]);

        if ($update) {

            foreach ($results as $i => $result) {
                if ($result->gameUser) {
                    $response = app('Gametech\Game\Repositories\GameUserRepository')->checkBalance($result->id, $result->gameUser->user_name);
                    if ($response['success'] == true) {
                        $result->gameUser->balance = $response['score'];
                        $result->gameUser->save();
                    }
                }
            }
        }

        return $results;
    }

    public function getGameUserFreeById($id, $update = true)
    {
        $results = $this->orderBy('sort')->with(['gameUserFree' => function ($query) use ($id) {
            $query->where('member_code', $id)->active();

        }])->findWhere(['status_open' => 'Y', 'enable' => 'Y', ['filepic', '<>', '']]);


        if ($update) {

            foreach ($results as $i => $result) {
                if ($result->gameUserFree) {
                    $response = app('Gametech\Game\Repositories\GameUserFreeRepository')->checkBalance($result->id, $result->gameUserFree->user_name);
                    if ($response['success'] == true) {
                        $result->gameUserFree->balance = $response['score'];
                        $result->gameUserFree->save();
                    }
                }
            }

        }

        return $results;
    }

    public function updatenew(array $data, $id, $attribute = "id")
    {
        $order = $this->find($id);

        $order->update($data);


        $this->uploadImages($data, $order);


        return $order;
    }


    public function uploadImages($data, $order, $type = "filepic")
    {

        $request = request();

        $hasfile = is_null($request->fileupload);

        if (!$hasfile) {
            $file = $order->filepic;
            $dir = 'game_img';

            Storage::putFileAs($dir, $request->fileupload, $file);
            $order->{$type} = $file;
            $order->save();

        }
    }

}
