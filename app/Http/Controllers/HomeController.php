<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class HomeController extends BaseController
{
    private const PER_PAGE = 12;

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

        // Счётчики
        $startYear = config('site.startYear', 2006);
        $counters = [
            'projects'   => Project::where('active', '!=', 0)->count(),
            'experience' => date('Y') - $startYear,
            'cups'       => (int)(((date('Y') - $startYear) * 365 * 100 + date('z') * rand(100, 200)) / 100),
            'countries'  => config('site.countries', 27),
        ];

        return Inertia::render('Home', [
            'counters'    => $counters,
            'showHidden'  => $showHidden,
            'initialTag'  => $request->query('tag', ''),
        ]);
    }

    /**
     * AJAX: список проектов с пагинацией, поиском и фильтрацией по тегам
     */
    public function projectsApi(Request $request)
    {
        $showHidden = (bool) $request->cookie('full');
        $page = max(1, (int) $request->query('page', 1));
        $search = $request->query('search', '');
        $tags = array_filter(explode(',', $request->query('tags', '')));

        $data = new Project()->getList($showHidden, $page, self::PER_PAGE, $search, $tags);

        // Постобработка: добавляем хосты и парсим теги
        $projects = array_map(function ($project) use ($request) {
            $project['tags'] = explode(' ', $project['tags']);

            return $project;
        }, $data['projects']);

        return response()->json([
            'projects'   => $projects,
            'page'       => $data['page'],
            'totalPages' => $data['totalPages'],
            'hasMore'    => $data['hasMore'],
            'total'      => $data['total'],
        ]);
    }

    /**
     * AJAX: список тегов с количеством
     */
    public function tagsApi()
    {
        $tagsPre = (new Project())->getTags();
        $tags = [];

        foreach ($tagsPre as $key => $count) {
            $tags[] = [
                'name'  => $key,
                'count' => $count,
            ];
        }

        return response()->json(['tags' => $tags]);
    }
}