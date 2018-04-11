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

    public function users()
    {
        return $this->hasMany('App\User');
    }

    /**
     * получаем список
     */
    public function getList()
    {
        $projects = self::where('active', '!=', 0)
            ->orderBy(DB::raw('STR_TO_DATE( date, "%m/%Y" )'), 'desc')
            ->get()
        ;

        foreach ($projects as &$project) {
            $project->image = '/images/projects/' . $project->link .'/preview.jpg';
            $project->link = '/projects/'. $project->link .'/';

            if ($project->active == 2) {
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

        return $projects;
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
}
