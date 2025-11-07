<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArtisanController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->input('key') !== env('ARTISAN_KEY')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($request->has('command')) {
            $command = $request->input('command');
            Artisan::call($command);
            $output = Artisan::output();
            return response()->json(['output' => $output]);
        }
    }
}
