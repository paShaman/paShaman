<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class HomeController extends BaseController
{
    public function __invoke(Request $request)
    {
        // Режим full — показываем скрытые проекты (аналог ?full=true из референса)
        $isFullRoute = $request->route()->getName() === 'home.full';
        $cookieFull = $request->cookie('full');
        $showHidden = $isFullRoute || $cookieFull;

        // Устанавливаем куку full при заходе на /full
        if ($isFullRoute) {
            Cookie::queue('full', '1', 60*10); // 10 минут
        }

        $projects = new Project()->getList($showHidden);

        foreach ($projects as &$project) {
            $project['tags'] = explode(' ', $project['tags']);
            $project['image'] = 'https://' . $request->getHost() . $project['image'];
        }

        $tagsPre = new Project()->getTags();
        $tags = [];

        foreach ($tagsPre as $key => $count) {
            $tags[] = [
                'name'  => $key,
                'count' => $count,
            ];
        }

        // Счётчики
        $startYear = config('site.startYear', 2006);
        $counters = [
            'projects'   => Project::where('active', '!=', 0)->count(),
            'experience' => date('Y') - $startYear,
            'cups'       => (int)(((date('Y') - $startYear) * 365 * 100 + date('z') * rand(100, 200)) / 100),
            'countries'  => config('site.countries', 27),
        ];

        return Inertia::render('Home', [
            'projects'   => $projects,
            'tags'       => $tags,
            'counters'   => $counters,
            'showHidden' => $showHidden,
        ]);
    }
}