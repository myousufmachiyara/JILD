<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index()
    {
        return view('production.index'); // use dot notation instead of slash
    }

    public function create()
    {
        return view('production.create'); // use dot notation instead of slash
    }

    public function receiving()
    {
        return view('production.receiving'); // use dot notation instead of slash
    }    
}
