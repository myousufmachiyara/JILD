<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return view('products.index'); // use dot notation instead of slash
    }

    public function create()
    {
        return view('products.create'); // use dot notation instead of slash
    }
}
