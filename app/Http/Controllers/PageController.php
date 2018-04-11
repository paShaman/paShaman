<?php

namespace App\Http\Controllers;

use App\Page;
use App\Project;

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
        Page::where('name', $page)
            ->where('active', 1)
            ->firstOrFail();

        $this->title[] = 'Portfolio';
        $this->page = $page;

        switch ($page) {
            case Page::PAGE_DEFAULT:
                $this->template['projects'] = (new Project())->getList();
                $this->template['tags'] = (new Project())->getTags();
                break;
        }

        return $this->render();
    }
}
