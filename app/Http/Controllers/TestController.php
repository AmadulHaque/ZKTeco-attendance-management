<?php

namespace App\Http\Controllers;

use Rats\Zkteco\Lib\ZKTeco;

use Illuminate\Http\Request;
use App\Services\ZKTeco\MB360Service;

class TestController extends Controller
{
    public function __construct(
        // protected MB360Service $mb360Service
    )
    {
    }

    public function cdata(Request $request)
    {

        info('cdata', [
            'ip' => $request->ip(),
            'data' => $request->all()
        ]);

       return response()->noContent();

    }



    public function test()
    {
        try {
           
          







        } catch (\Throwable $th) {
            dd($th);
        }
    }





}
