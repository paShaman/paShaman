<?php

namespace App\Http\Controllers;

use App\Page;

class PageController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function page($page = Page::PAGE_DEFAULT)
    {
        $page = Page::where('name', $page)->firstOrFail();
    }
}
