<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Project extends Model {

    protected $fillable = [
        'name', 'site', 'info', 'link', 'active', 'tags', 'date'
    ];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships

    public function authors()
    {
        return $this->belongsToMany('App\User', 'users_to_projects')->withPivot('role');
    }

    /**
     * получаем список
     */
    public function getList()
    {
        $projects = self::where('active', '!=', 0)
            ->orderBy(DB::raw('STR_TO_DATE( date, "%m/%Y" )'), 'desc')
            ->orderBy('id', 'desc')
            ->get()
        ;

        foreach ($projects as &$project) {
            $project->image = '/images/projects/' . $project->link .'/preview.jpg';
            $project->link = '/projects/'. $project->link;

            if ($project->active == 2 && empty($_COOKIE['full'])) {
                //create blurred image

                $link = md5($project->link);

                $path =  $_SERVER['DOCUMENT_ROOT'];
                $folder = DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'blur' . DIRECTORY_SEPARATOR;

                if (!file_exists($path . $folder . $link . '.jpg')) {

                    Image::blur($path . $project->image, $path . $folder . $link . '.jpg');

                }

                $project->image =  $folder . $link . '.jpg';
                $project->link = '#';
                $project->name = 'Hidden';
            }
        }

        return $projects->toArray();
    }

    /**
     * получаем список тегов
     */
    public function getTags()
    {
        $tagsData = DB::table(self::getTable())
            ->select('tags', 'date')
            ->where('active', '!=', 0)
            ->get()
        ;

        $result = [];

        foreach ($tagsData as $tags) {
            $tags = explode(' ', $tags->tags);

            foreach($tags as $tag)	{
                if(!isset($result[$tag])){
                    $result[$tag] = 1;
                }else{
                    $result[$tag]++;
                }
            }
        }

        arsort($result);

        return $result;
    }

    /**
     * полусаем детальную инфу по проекту
     *
     * @param $project
     * @return object|bool
     */
    public function getProjectDetail($project)
    {
        if (empty($project)) {
            return false;
        }

        $project = self::where('link', $project)
            ->whereIn('active', (empty($_COOKIE['full']) ? [1] : [1,2]))
            ->first()
        ;

        if (empty($project)) {
            return false;
        }

        $project->image = '/images/projects/' . $project->link .'/main.jpg';
        $project->image_full = '/images/projects/' . $project->link .'/main.jpg';

        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/projects/' . $project->link .'/full.jpg')) {
            $project->image_full = '/images/projects/' . $project->link .'/full.jpg';
        }

        foreach ($project->authors as &$author) {
            $author->image = '/images/authors/' . $author->id .'.jpg';
        }

        //prev next
        $projects = self::whereIn('active', (empty($_COOKIE['full']) ? [1] : [1,2]))
            ->orderBy(DB::raw('STR_TO_DATE( date, "%m/%Y" )'), 'desc')
            ->get()
            ->toArray()
        ;

        //проекты есть, так что отбрасываем проверку на существование
        $prev = $projects[count($projects)-1]['link'];
        $next = $projects[0]['link'];

        for ($i = 0; $i < count($projects); $i++) {
            if ($projects[$i]['link'] == $project->link) {
                if ($i > 0) {
                    $prev = $projects[$i - 1]['link'];
                }
                if ($i < count($projects) - 1) {
                    $next = $projects[$i + 1]['link'];
                }
                break;
            }
        }

        $project->prev = '/projects/' . $prev;
        $project->next = '/projects/' . $next;
        $works = [];

        //check work
        if (mb_strpos($project->tags, 'работа') !== false) {
            foreach ($projects as $item) {
                if (mb_strpos($item['tags'], 'работа') !== false) {
                    $tags = explode(" ", $item['tags']);
                    $years = [];

                    foreach ($tags as $tag) {
                        if (intval($tag) != 0) {
                            $years[] = $tag;
                        }
                    }

                    rsort($years);

                    $works[] = [
                        'years' => $years,
                        'name'  => $item['name'],
                        'link'  => '/projects/' . $item['link'],
                    ];
                }
            }
        }

        $project->works = $works;

        //check versions
        $version = explode("_ver", $project->link);
        $version = reset($version);

        $versionsData = self::where('active', '!=', 0)
            ->where('link', 'like', $version . '_ver%')
            ->orWhere('link', $version)
            ->get()
        ;

        $versions = [];

        foreach ($versionsData as $version) {
            $number = preg_replace("/(.*)_ver(\d+)/","$2", $version->link);

            $versions[] = [
                'version' => (is_numeric($number) ? $number : 1) . '.0',
                'current' => $version->link == $project->link,
                'link'    => '/projects/' . $version->link
            ];
        }

        $project->versions = $versions;
        $project->link = '/projects/' . $project->link;

        return $project->toArray();
    }
}
