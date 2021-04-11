<?php


namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Ktb
{

    public function twocaptcha($path)
    {
        $key = "814d262d4de9050e4585c8386d5ffe8b";
        $cfile = curl_file_create($path, 'image/gif', 'image');
        $postData = array(
            'method' => 'post',
            'key' => $key,
            'file' => $cfile,
            'numeric' => '1',
            'max_len', '4',
        );

        $option = [
            'Content-Type' => 'multipart/form-data'
        ];

        $data['outtext'] = '';
        $response = Http::withHeaders($option)->post('https://2captcha.com/in.php',$postData);
        if($response->successful()){
            $id = explode("|", $response->body())[1];
            $data['outtext'] = '';
            $i = 1;
            while ($data['outtext'] == '') {
                sleep(2);
                $response = Http::withHeaders($option)->get('https://2captcha.com/res.php?key='. $key . '&action=get&id=' . $id);
                if($response->successful()){
                    $result = explode("|", $response->body());
                    if (count($result) > 1) {
                        $data['outtext'] = $result[1];
                        break;
                    }
                    $i++;
                    if ($i >= 5) {
                        break;
                    }
                }
            }

        }
        return $data['outtext'];

    }

    public function clean($text)
    {
        return  Str::of($text)->replace('&nbsp;', '')->trim();

    }


}
