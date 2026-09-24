<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use App\User;

class SubscribeSpecificationItem extends Model
{
    protected $table = 'subscribe_specification_items';
    public $timestamps = false;
    protected $guarded = ['id'];


    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function course()
    {
        return $this->belongsTo(Webinar::class, 'course_id');
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class, 'bundle_id');
    }
}
