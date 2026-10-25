<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id', 'image'];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    /** The categories this one sits under (none for a main category). */
    public function parents()
    {
        return $this->belongsToMany(Category::class, 'category_parent', 'category_id', 'parent_id');
    }

    /** The categories directly under this one. */
    public function children()
    {
        return $this->belongsToMany(Category::class, 'category_parent', 'parent_id', 'category_id');
    }

    /**
     * Ids of every category below this one, at any depth.
     */
    public function descendantIds(): array
    {
        $found = [];
        $frontier = [$this->id];
        while ($frontier) {
            $next = DB::table('category_parent')
                ->whereIn('parent_id', $frontier)
                ->pluck('category_id')
                ->diff($found)
                ->diff([$this->id])
                ->all();
            $found = array_merge($found, $next);
            $frontier = $next;
        }
        return $found;
    }
}
