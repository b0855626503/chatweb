<?php

namespace Gametech\Core\Repositories;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Support\Facades\Storage;

class ConfigRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Core\Contracts\Config';
    }

    public function updatenew(array $data, $id, $attribute = "id")
    {
        $order = $this->find($id);

//        dd($order);

        $order->update($data);


        $this->uploadImages($data, $order);


        return $order;
    }

    public function uploadImages( $data, $order, $type = "logo")
    {

        $request = request();
//        dd($request->fileupload);

        $hasfile = is_null($request->fileupload);
        $hasfilenew = is_null($request->fileuploadnew);

        if(!$hasfile){
            $file =  $order->{$type};
            $dir = 'img';

            Storage::putFileAs($dir, $request->fileupload, $file);
            $order->{$type} = $file;
            $order->save();

        }

        if(!$hasfilenew){
            $filenew =  $order->favicon;
            $dirnew = 'img';

            Storage::putFileAs($dirnew, $request->fileuploadnew, $filenew);
            $order->favicon = $filenew;
            $order->save();

        }
    }
}
