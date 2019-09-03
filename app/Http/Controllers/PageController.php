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
        if ($page == 'full') {
            setcookie('full', true, time() + 60*10); //куку на 10 минут
            $_COOKIE['full'] = true;
            $page = Page::PAGE_DEFAULT;
        }

        Page::where('name', $page)
            ->where('active', 1)
            ->firstOrFail();

        $this->title[] = 'Portfolio';
        $this->page = $page;

        switch ($page) {
            case Page::PAGE_DEFAULT:
                $this->template['projects']     = (new Project())->getList();
                $this->template['tags']         = (new Project())->getTags();

                $startYear = 2006;
                $this->template['experience']   = date('Y') - $startYear;
                $this->template['cups']         = (int)(((date('Y') - $startYear) * 365 * 150 + date('z') * rand(100, 300)) / 100);
                $this->template['countries']    = 20;
                break;
        }

        return $this->render();
    }
}
