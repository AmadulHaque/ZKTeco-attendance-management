<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laradevsbd\Zkteco\Http\Library\ZktecoLib;
 
class TestController extends Controller
{
 

    public function cdata(Request $request)
    {

        info('cdata', [
            'ip' => $request->ip(),
            'data' => $request->all()
        ]);

       return response()->json([],200);

    }



    public function test()
    {

        $zk = new ZktecoLib('192.168.10.23');

        $zk->connect();

        dd( 
            $zk->getAttendance(),
            $zk->getUser(),
        );








    }





}
